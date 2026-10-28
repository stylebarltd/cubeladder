<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * Ratings of the competitive ladder, rebuilt from all counted comp_games in
 * order (a few hundred games take milliseconds, so it is always exact).
 *
 * Team Elo: everyone starts at START. A team's strength is the average rating
 * of its players who played at least CompetitiveTracker::MIN_SHARE of the
 * game; each of them gains or loses K * (result - expected), result 1 / 0.5 / 0.
 * New players move faster (K_NEW for their first NEW_GAMES games).
 *
 * Individual rating, next to it (the Elo stays team-only): in every rated game
 * each part (IND_WEIGHTS) is compared with the other rated players of that
 * game - flags scored, damage dealt, K/D, the team result, teamkills - per share
 * of the game played, as z-scores. Their weighted sum gives the game's rating
 * on the ladder's 0.1 - 9.9 scale (5.0 = an average game); a player's rating is
 * the average of the last IND_WINDOW games. Games without damage numbers (the
 * server hid them) use frags for the damage part.
 */
class CompetitiveRatings
{
    public const START = 1000.0;
    public const K = 24.0;
    public const K_NEW = 40.0;
    public const NEW_GAMES = 10;

    /** Individual rating: weights of the parts in flag modes and in the other modes */
    public const IND_WEIGHTS = [
        'flags' => ['flags' => 0.45, 'damage' => 0.25, 'kd' => 0.20, 'win' => 0.07, 'discipline' => 0.03],
        'other' => ['flags' => 0.0, 'damage' => 0.45, 'kd' => 0.40, 'win' => 0.12, 'discipline' => 0.03],
    ];
    /** Individual rating: a player's last games averaged */
    public const IND_WINDOW = 50;
    private const FLAG_MODES = ['ctf', 'htf', 'tktf'];

    public function recalculate(): int
    {
        $conn = ConnectionManager::get('default');
        $games = $conn->execute('SELECT id, winner, mode, ended_at FROM comp_games WHERE counted = 1 ORDER BY ended_at, id')->fetchAll('assoc');
        $rows = [];
        foreach ($conn->execute(
            'SELECT s.id, s.comp_game_id, s.player_key, s.player_id, s.name, s.team, s.share,
                    s.flags, s.frags, s.deaths, s.teamkills, s.damage
             FROM comp_player_stats s INNER JOIN comp_games g ON g.id = s.comp_game_id WHERE g.counted = 1')->fetchAll('assoc') as $r) {
            $rows[$r['comp_game_id']][] = $r;
        }

        $players = []; // player_key => rating, peak, games, wins, losses, draws, last, name, player_id, ind (raw scores)
        $updates = []; // stats row id => [rating_before, rating_after, team, other, expected, k, raw individual score]
        foreach ($games as $g) {
            $teams = ['CLA' => [], 'RVSF' => []];
            foreach ($rows[$g['id']] ?? [] as $r) {
                // "unarmed" is the default name of countless players: never one player's rating
                $nobody = strtolower((string)$r['name']) === 'unarmed' && str_starts_with((string)$r['player_key'], 'name:');
                if (!$nobody && (float)$r['share'] >= CompetitiveTracker::MIN_SHARE && isset($teams[$r['team']])) {
                    $teams[$r['team']][] = $r;
                } else {
                    $updates[$r['id']] = [null, null, null, null, null, null, null];
                }
            }
            if (!$teams['CLA'] || !$teams['RVSF']) {
                continue;
            }
            $raw = $this->individualScores($g, array_merge($teams['CLA'], $teams['RVSF']));
            $rating = fn($r) => $players[$r['player_key']]['rating'] ?? self::START;
            $strength = [];
            foreach ($teams as $t => $members) {
                $strength[$t] = array_sum(array_map($rating, $members)) / count($members);
            }
            foreach ($teams as $t => $members) {
                $other = $t === 'CLA' ? 'RVSF' : 'CLA';
                $expected = 1 / (1 + 10 ** (($strength[$other] - $strength[$t]) / 400));
                $result = $g['winner'] === null ? 0.5 : ($g['winner'] === $t ? 1.0 : 0.0);
                foreach ($members as $r) {
                    $p = $players[$r['player_key']] ?? ['rating' => self::START, 'peak' => self::START, 'games' => 0,
                        'wins' => 0, 'losses' => 0, 'draws' => 0, 'ind' => []];
                    $before = $p['rating'];
                    $k = $p['games'] < self::NEW_GAMES ? self::K_NEW : self::K;
                    $p['rating'] = $before + $k * ($result - $expected);
                    $p['peak'] = max($p['peak'], $p['rating']);
                    $p['games']++;
                    $p[$result === 1.0 ? 'wins' : ($result === 0.0 ? 'losses' : 'draws')]++;
                    $p['last'] = $g['ended_at'];
                    $p['name'] = $r['name'];
                    $p['player_id'] = $r['player_id'] ?? ($p['player_id'] ?? null);
                    $p['ind'][] = $r['id'];
                    $players[$r['player_key']] = $p;
                    // the parts of the change, for the game page: team strengths, win chance, K
                    $updates[$r['id']] = [round($before, 1), round($p['rating'], 1), round($strength[$t], 1), round($strength[$other], 1),
                        round($expected, 4), $k, $raw[$r['id']]];
                }
            }
        }

        // individual: the raw scores of all rated games on the 0.1 - 9.9 scale, then each player's average
        $scores = array_values(array_filter(array_column($updates, 6), fn($v) => $v !== null));
        $mean = $scores ? array_sum($scores) / count($scores) : 0.0;
        $sd = $scores ? (sqrt(array_sum(array_map(fn($v) => ($v - $mean) ** 2, $scores)) / count($scores)) ?: 1.0) : 1.0;
        foreach ($updates as &$u) {
            $u[6] = $u[6] === null ? null : round(max(0.1, min(9.9, 5 + 1.5 * ($u[6] - $mean) / $sd)), 1);
        }
        unset($u);
        foreach ($players as &$p) {
            $last = array_slice($p['ind'], -self::IND_WINDOW);
            $p['ind_games'] = count($last);
            $p['ind_rating'] = $last ? round(array_sum(array_map(fn($id) => $updates[$id][6], $last)) / count($last), 1) : null;
        }
        unset($p);

        $conn->transactional(function ($conn) use ($players, $updates) {
            foreach ($updates as $id => $u) {
                $conn->execute('UPDATE comp_player_stats SET rating_before = ?, rating_after = ?, team_rating = ?, opp_rating = ?,
                    expected = ?, k_factor = ?, ind_rating = ? WHERE id = ?', [...$u, $id]);
            }
            $conn->execute('DELETE FROM comp_ratings');
            foreach ($players as $key => $p) {
                $conn->execute(
                    'INSERT INTO comp_ratings (player_key, player_id, name, rating, peak, ind_rating, ind_games, games, wins, losses, draws, last_game_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$key, $p['player_id'], $p['name'], round($p['rating'], 1), round($p['peak'], 1), $p['ind_rating'], $p['ind_games'],
                     $p['games'], $p['wins'], $p['losses'], $p['draws'], $p['last']]);
            }
        });

        return count($players);
    }

    /**
     * Raw individual score of each rated player of one game (by stats row id):
     * every part as a z-score against the game's rated players, weighted.
     * Counts are divided by the share of the game played.
     */
    private function individualScores(array $game, array $rated): array
    {
        $w = self::IND_WEIGHTS[in_array($game['mode'], self::FLAG_MODES, true) ? 'flags' : 'other'];
        $hasDamage = !array_filter($rated, fn($r) => $r['damage'] === null);
        $parts = [];
        foreach ($rated as $r) {
            $share = max((float)$r['share'], 0.01);
            $result = $game['winner'] === null ? 0.5 : ($game['winner'] === $r['team'] ? 1.0 : 0.0);
            $parts[$r['id']] = [
                'flags' => (int)$r['flags'] / $share,
                'damage' => ($hasDamage ? (int)$r['damage'] : (int)$r['frags']) / $share,
                'kd' => (int)$r['frags'] / max(1, (int)$r['deaths']),
                'win' => $result,
                'discipline' => -(int)$r['teamkills'] / $share,
            ];
        }
        $raw = array_fill_keys(array_keys($parts), 0.0);
        foreach ($w as $part => $weight) {
            $vals = array_column($parts, $part);
            $m = array_sum($vals) / count($vals);
            $s = sqrt(array_sum(array_map(fn($v) => ($v - $m) ** 2, $vals)) / count($vals));
            if ($weight <= 0 || $s <= 0) {
                continue;
            }
            foreach ($parts as $id => $p) {
                $raw[$id] += $weight * ($p[$part] - $m) / $s;
            }
        }

        return $raw;
    }
}
