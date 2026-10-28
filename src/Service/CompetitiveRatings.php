<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * Elo of the competitive ladder, rebuilt from all counted comp_games in
 * order (a few hundred games take milliseconds, so it is always exact).
 *
 * Everyone starts at START. A team's strength is the average rating of its
 * players who played at least CompetitiveTracker::MIN_SHARE of the game;
 * each of them gains or loses K * (result - expected), result 1 / 0.5 / 0.
 * New players move faster (K_NEW for their first NEW_GAMES games).
 */
class CompetitiveRatings
{
    public const START = 1000.0;
    public const K = 24.0;
    public const K_NEW = 40.0;
    public const NEW_GAMES = 10;

    public function recalculate(): int
    {
        $conn = ConnectionManager::get('default');
        $games = $conn->execute('SELECT id, winner, ended_at FROM comp_games WHERE counted = 1 ORDER BY ended_at, id')->fetchAll('assoc');
        $rows = [];
        foreach ($conn->execute(
            'SELECT s.id, s.comp_game_id, s.player_key, s.player_id, s.name, s.team, s.share
             FROM comp_player_stats s INNER JOIN comp_games g ON g.id = s.comp_game_id WHERE g.counted = 1')->fetchAll('assoc') as $r) {
            $rows[$r['comp_game_id']][] = $r;
        }

        $players = []; // player_key => rating, peak, games, wins, losses, draws, last, name, player_id
        $updates = [];
        foreach ($games as $g) {
            $teams = ['CLA' => [], 'RVSF' => []];
            foreach ($rows[$g['id']] ?? [] as $r) {
                // "unarmed" is the default name of countless players: never one player's rating
                $nobody = strtolower((string)$r['name']) === 'unarmed' && str_starts_with((string)$r['player_key'], 'name:');
                if (!$nobody && (float)$r['share'] >= CompetitiveTracker::MIN_SHARE && isset($teams[$r['team']])) {
                    $teams[$r['team']][] = $r;
                } else {
                    $updates[] = [null, null, null, null, null, null, $r['id']];
                }
            }
            if (!$teams['CLA'] || !$teams['RVSF']) {
                continue;
            }
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
                        'wins' => 0, 'losses' => 0, 'draws' => 0];
                    $before = $p['rating'];
                    $k = $p['games'] < self::NEW_GAMES ? self::K_NEW : self::K;
                    $p['rating'] = $before + $k * ($result - $expected);
                    $p['peak'] = max($p['peak'], $p['rating']);
                    $p['games']++;
                    $p[$result === 1.0 ? 'wins' : ($result === 0.0 ? 'losses' : 'draws')]++;
                    $p['last'] = $g['ended_at'];
                    $p['name'] = $r['name'];
                    $p['player_id'] = $r['player_id'] ?? ($p['player_id'] ?? null);
                    $players[$r['player_key']] = $p;
                    // the parts of the change, for the game page: team strengths, win chance, K
                    $updates[] = [round($before, 1), round($p['rating'], 1), round($strength[$t], 1), round($strength[$other], 1),
                        round($expected, 4), $k, $r['id']];
                }
            }
        }

        $conn->transactional(function ($conn) use ($players, $updates) {
            foreach ($updates as $u) {
                $conn->execute('UPDATE comp_player_stats SET rating_before = ?, rating_after = ?, team_rating = ?, opp_rating = ?,
                    expected = ?, k_factor = ? WHERE id = ?', $u);
            }
            $conn->execute('DELETE FROM comp_ratings');
            foreach ($players as $key => $p) {
                $conn->execute(
                    'INSERT INTO comp_ratings (player_key, player_id, name, rating, peak, games, wins, losses, draws, last_game_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$key, $p['player_id'], $p['name'], round($p['rating'], 1), round($p['peak'], 1), $p['games'],
                     $p['wins'], $p['losses'], $p['draws'], $p['last']]);
            }
        });

        return count($players);
    }
}
