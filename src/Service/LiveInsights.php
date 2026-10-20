<?php
declare(strict_types=1);

namespace App\Service;

use App\Command\CalculateRatingsCommand;
use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;

/**
 * The extras on a live match page, from one extinfo poll of a server plus
 * what the ladder already knows about its players:
 *
 *  - badges: each linked player's rating, rank and type (player_ratings)
 *  - win chance (CTF): from the teams' average rating and the flag lead
 *  - highlights: hat-tricks, map flag records, career milestones in reach
 *
 * Win chance = logistic(RATING_W * d * left / length + LEAD_W * lead / (left + LEAD_C))
 * with d = average rating CLA - RVSF (unrated players 5.0), lead = flags
 * CLA - RVSF, left = minutes remaining. Fitted on 6,764 CTF games (rating
 * part) and the minute scoreboards of 149 of them (lead part), 2026-10-20;
 * calibrated: when it said 80-90 %, the favourite won 83 % of the time.
 * Early on a lead means little (+3 flags with 12 minutes left: 56 %), in the
 * last minute it decides (+3: 87 %).
 */
class LiveInsights
{
    public const RATING_W = 1.454;
    public const LEAD_W = 0.932;
    public const LEAD_C = 0.5;
    /** Rating of a player without one (the scale's average) */
    public const UNRATED = 5.0;

    /** Career milestones are mentioned this close to the next tier */
    private const NEAR_KILLS = 25;
    private const NEAR_FLAGS = 3;
    /** Map record mentioned this many flags before it */
    private const NEAR_RECORD = 2;

    /**
     * @param array $info AcExtInfoService result with linked players (player_id)
     * @param string|null $runningGameId the running game when the log import has it already
     * @param int $length game length in minutes
     * @return array{badges: array, win: ?array, notes: list<array>}
     */
    public function forServer(array $info, ?string $runningGameId, int $length): array
    {
        $out = ['badges' => [], 'win' => null, 'notes' => []];
        $players = array_values(array_filter($info['players'] ?? [], fn($p) => empty($p['is_spectator'])));
        if (!$info['online'] || !$players) {
            return $out;
        }
        $ids = array_values(array_unique(array_filter(array_column($players, 'player_id'))));
        $ctf = (AcExtInfoService::MODE_CODES[(int)$info['mode']] ?? null) === 'ctf';

        $ratings = $this->ratings($ids);
        foreach ($ratings as $id => $r) {
            $out['badges'][$id] = [
                'rating' => (float)$r['rating'],
                'rank' => (int)$r['rank'],
                'type' => CalculateRatingsCommand::typeLabel(
                    (string)$r['type'], (int)$r['attack_pct'], (int)$r['defense_pct'], (int)$r['combat_pct']),
            ];
        }

        if ($ctf && !empty($info['team_mode'])) {
            $out['win'] = $this->winChance($players, $ratings, (int)($info['minremain'] ?? 0), max($length, (int)($info['minremain'] ?? 0), 1));
            $out['notes'] = array_merge(
                $this->gameNotes($players),
                $this->recordNotes($players, (string)$info['map']),
            );
        }
        $out['notes'] = array_merge($out['notes'], $this->milestoneNotes($players, $ids, $runningGameId, $ctf));

        return $out;
    }

    /**
     * Chance of each team to win, now and before the first flag (for "upset").
     */
    public function winChance(array $players, array $ratings, int $left, int $length): ?array
    {
        $avg = $flags = [];
        foreach (['CLA', 'RVSF'] as $team) {
            $members = array_filter($players, fn($p) => str_starts_with(strtoupper((string)($p['team'] ?? '')), $team));
            if (!$members) {
                return null;
            }
            $avg[$team] = array_sum(array_map(
                fn($p) => isset($ratings[$p['player_id'] ?? '']) ? (float)$ratings[$p['player_id']]['rating'] : self::UNRATED,
                $members)) / count($members);
            $flags[$team] = array_sum(array_map(fn($p) => (int)($p['flags'] ?? 0), $members));
        }
        $d = $avg['CLA'] - $avg['RVSF'];
        $lead = $flags['CLA'] - $flags['RVSF'];
        $left = max(0, $left);
        $now = self::logistic(self::RATING_W * $d * $left / $length + self::LEAD_W * $lead / ($left + self::LEAD_C));
        $before = self::logistic(self::RATING_W * $d);

        return [
            'CLA' => round($now, 3), 'RVSF' => round(1 - $now, 3),
            'before' => ['CLA' => round($before, 3), 'RVSF' => round(1 - $before, 3)],
            'avg' => ['CLA' => round($avg['CLA'], 1), 'RVSF' => round($avg['RVSF'], 1)],
        ];
    }

    private static function logistic(float $z): float
    {
        return 1 / (1 + exp(-max(-30, min(30, $z))));
    }

    /** player_id => player_ratings row */
    private function ratings(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $rows = ConnectionManager::get('default')->execute(
            'SELECT player_id, rating, `rank`, type, attack_pct, defense_pct, combat_pct FROM player_ratings
             WHERE player_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids)->fetchAll('assoc');

        return array_column($rows, null, 'player_id');
    }

    /** Hat-tricks of this game (flags are exact in CTF) */
    private function gameNotes(array $players): array
    {
        $notes = [];
        foreach ($players as $p) {
            $f = (int)($p['flags'] ?? 0);
            if ($f >= 5) {
                $notes[] = ['icon' => '🪄', 'text' => sprintf('%s: super hat-trick, %d flags', $p['name'], $f), 'player_id' => $p['player_id'] ?? null];
            } elseif ($f >= 3) {
                $notes[] = ['icon' => '🎩', 'text' => sprintf('%s: hat-trick, %d flags', $p['name'], $f), 'player_id' => $p['player_id'] ?? null];
            }
        }

        return $notes;
    }

    /** The map's flag record (CTF, one game): broken or in reach */
    private function recordNotes(array $players, string $map): array
    {
        $record = $map !== '' ? $this->flagRecord($map) : null;
        if (!$record) {
            return [];
        }
        $best = null;
        foreach ($players as $p) {
            if ($best === null || (int)$p['flags'] > (int)$best['flags']) {
                $best = $p;
            }
        }
        $f = (int)($best['flags'] ?? 0);
        if ($f > $record['flags']) {
            return [['icon' => '🏆', 'player_id' => $best['player_id'] ?? null, 'text' => sprintf(
                'New %s record: %s, %d flags (was %d by %s)', $map, $best['name'], $f, $record['flags'], $record['name'])]];
        }
        if ($f > 0 && $record['flags'] - $f <= self::NEAR_RECORD) {
            $need = $record['flags'] - $f;

            return [['icon' => '🏆', 'player_id' => $best['player_id'] ?? null, 'text' => $need === 0
                ? sprintf('%s equals the %s record: %d flags (%s)', $best['name'], $map, $f, $record['name'])
                : sprintf('%s is %d flag%s from the %s record (%d by %s)', $best['name'], $need, $need === 1 ? '' : 's', $map, $record['flags'], $record['name'])]];
        }

        return [];
    }

    /** Most flags in one CTF game on a map (as on the map page); cached until the next import */
    private function flagRecord(string $map): ?array
    {
        $key = 'live_flag_record_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $map);

        $record = Cache::remember($key, function () use ($map) {
            $row = ConnectionManager::get('default')->execute(
                "SELECT p.scored_with_the_flag AS flags, pl.name
                 FROM player_stats_per_game p
                 INNER JOIN games g ON g.id = p.game_id
                 INNER JOIN maps m ON m.id = g.map_id
                 INNER JOIN players pl ON pl.id = p.player_id
                 WHERE m.name = ? AND g.mode = 'ctf' AND g.ended_at IS NOT NULL AND pl.track = 1
                 ORDER BY p.scored_with_the_flag DESC, g.started_at DESC LIMIT 1", [$map])->fetch('assoc');

            // no record is cached too (flags 0), a null would be asked again every time
            return ['flags' => (int)($row['flags'] ?? 0), 'name' => (string)($row['name'] ?? '')];
        }, 'rankings');
        $record = is_array($record) ? $record : null;

        return $record && $record['flags'] > 0 ? $record : null;
    }

    /**
     * Career milestones in reach. The totals are from the last import; what
     * that import already counted of the running game is taken off, the live
     * numbers added (flags only - live frags are not kills: a gib is 2 frags).
     */
    private function milestoneNotes(array $players, array $ids, ?string $runningGameId, bool $ctf): array
    {
        if (!$ids) {
            return [];
        }
        $conn = ConnectionManager::get('default');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $progress = [];
        foreach ($conn->execute("SELECT player_id, progress FROM player_milestone_progress WHERE player_id IN ($in)", $ids)->fetchAll('assoc') as $r) {
            $progress[$r['player_id']] = json_decode((string)$r['progress'], true) ?: [];
        }
        $imported = [];
        if ($runningGameId !== null) {
            $rows = $conn->execute(
                "SELECT player_id, SUM(kills) kills, SUM(scored_with_the_flag) flags FROM player_stats_per_game
                 WHERE game_id = ? AND player_id IN ($in) GROUP BY player_id", array_merge([$runningGameId], $ids))->fetchAll('assoc');
            $imported = array_column($rows, null, 'player_id');
        }
        $tiers = MilestoneService::MILESTONES;

        $notes = [];
        foreach ($players as $p) {
            $id = $p['player_id'] ?? null;
            if ($id === null || !isset($progress[$id])) {
                continue;
            }
            $kills = (int)($progress[$id]['kills'] ?? 0) - (int)($imported[$id]['kills'] ?? 0);
            $next = self::nextTier($tiers['kills']['tiers'], $kills);
            if ($next !== null && $next - $kills <= self::NEAR_KILLS) {
                $notes[] = ['icon' => '🎯', 'player_id' => $id, 'text' => sprintf(
                    '%s started this game %d kills short of %s career kills', $p['name'], $next - $kills, number_format($next))];
            }
            if ($ctf) {
                $flags = (int)($progress[$id]['flags'] ?? 0) - (int)($imported[$id]['flags'] ?? 0);
                $next = self::nextTier($tiers['flags']['tiers'], $flags);
                $now = $flags + (int)($p['flags'] ?? 0);
                if ($next !== null && $now >= $next) {
                    $notes[] = ['icon' => '🎉', 'player_id' => $id, 'text' => sprintf('%s just scored career flag no. %s', $p['name'], number_format($next))];
                } elseif ($next !== null && $next - $now <= self::NEAR_FLAGS) {
                    $need = $next - $now;
                    $notes[] = ['icon' => '🚩', 'player_id' => $id, 'text' => sprintf(
                        '%s is %d flag%s from %s career flags', $p['name'], $need, $need === 1 ? '' : 's', number_format($next))];
                }
            }
        }

        return $notes;
    }

    private static function nextTier(array $tiers, int $value): ?int
    {
        foreach ($tiers as $t) {
            if ($value < $t) {
                return $t;
            }
        }

        return null;
    }
}
