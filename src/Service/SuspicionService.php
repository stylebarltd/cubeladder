<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * Players whose numbers look like cheating (Admin > Suspicious): unusual
 * against everyone else, unusual against their own past, and the server's
 * own warnings (CheatSignals). Nothing here proves anything - it's a list to
 * look at, the admin decides (player_reviews).
 *
 * Stats are over counted games with known minutes; percentiles are among
 * players with minMinutes or more (limit()).
 */
class SuspicionService
{
    /**
     * The limits, from config/anticheat.php (not in git, so the real values
     * aren't public - see config()); these defaults only make it run.
     *   minMinutes      minutes played to be compared with everyone
     *   newGames        new profiles: fewer games than this ...
     *   minNewMinutes   ... but this many minutes
     *   minSniperKills  sniper kills (headshot + punctured) to judge a headshot rate
     *   recentGames     "jump": the last N games against the ones before (at least as many)
     *   jumpRatio       ... kills per minute this many times higher
     *   topPercentile   a stat counts from this percentile of everyone on
     *   minScore        score from which a player is listed
     *   lagPing         blocked warnings count half from this ping, a quarter from twice it
     */
    public const DEFAULTS = [
        'minMinutes' => 600, 'newGames' => 20, 'minNewMinutes' => 120, 'minSniperKills' => 200,
        'recentGames' => 25, 'jumpRatio' => 2.0, 'topPercentile' => 0.99, 'minScore' => 3.0, 'lagPing' => 200,
    ];

    public static function limit(string $key): int|float
    {
        return self::config()[$key] ?? self::DEFAULTS[$key];
    }

    /**
     * The real limits: config/anticheat.php (git-ignored, returns an array like
     * DEFAULTS + CheatSignals::DEFAULTS), or Ladder.anticheat in app_local.php.
     */
    public static function config(): array
    {
        static $config = null;
        if ($config === null) {
            $file = CONFIG . 'anticheat.php';
            $config = (array)(\Cake\Core\Configure::read('Ladder.anticheat') ?? []) + (is_file($file) ? (array)include $file : []);
        }

        return $config;
    }

    /**
     * Suspicious players, most suspicious first.
     *
     * @return list<array{player_id: string, name: string, country: ?string, score: float, reasons: list<array>, stats: array, review: ?array}>
     */
    public function players(bool $includeCleared = false): array
    {
        $conn = ConnectionManager::get('default');

        $stats = $conn->execute(
            'SELECT s.player_id, COUNT(*) games, SUM(s.minutes_played) mins, SUM(s.kills) kills, SUM(s.deaths) deaths,
                    SUM(s.headshot) hs, SUM(s.punctured) punct, SUM(s.scored_with_the_flag) flags
             FROM player_stats_per_game s
             INNER JOIN games g ON g.id = s.game_id
             WHERE g.inaccurate = 0 AND s.minutes_played > 0
             GROUP BY s.player_id
             HAVING mins >= ?', [self::limit('minNewMinutes')])->fetchAll('assoc');

        $byId = [];
        foreach ($stats as $r) {
            $mins = (int)$r['mins'];
            $sniper = (int)$r['hs'] + (int)$r['punct'];
            $byId[$r['player_id']] = [
                'games' => (int)$r['games'],
                'mins' => $mins,
                'kills' => (int)$r['kills'],
                'kpm' => (int)$r['kills'] / $mins,
                'kd' => (int)$r['kills'] / max(1, (int)$r['deaths']),
                'hs_rate' => $sniper >= self::limit('minSniperKills') ? (int)$r['hs'] / $sniper : null,
                'sniper' => $sniper,
            ];
        }

        // everyone with enough minutes is the yardstick
        $field = array_filter($byId, fn($p) => $p['mins'] >= self::limit('minMinutes'));
        $sorted = [];
        foreach (['kpm', 'kd', 'hs_rate'] as $k) {
            $values = array_values(array_filter(array_column($field, $k), fn($v) => $v !== null));
            sort($values);
            $sorted[$k] = $values;
        }
        $pct = function (string $k, ?float $v) use ($sorted): ?float {
            $values = $sorted[$k];
            if ($v === null || !$values) {
                return null;
            }
            // share of the field below this value
            $lo = 0;
            $hi = count($values);
            while ($lo < $hi) {
                $mid = intdiv($lo + $hi, 2);
                $values[$mid] < $v ? $lo = $mid + 1 : $hi = $mid;
            }

            return $lo / count($values);
        };
        $avg = fn(string $k) => $sorted[$k] ? array_sum($sorted[$k]) / count($sorted[$k]) : 0;

        $jumps = $this->jumps($conn);
        $signals = $this->signals($conn);
        $voted = $conn->execute(
            "SELECT target_id, JSON_VALUE(details, '$.kind') kind, COUNT(*) n FROM events
             WHERE type = 'vote' AND target_id IS NOT NULL AND JSON_VALUE(details, '$.result') = 'passed'
             GROUP BY target_id, kind")->fetchAll('assoc');
        $votedOut = [];
        foreach ($voted as $v) {
            $votedOut[$v['target_id']][$v['kind']] = (int)$v['n'];
        }

        $list = [];
        $ids = array_unique(array_merge(array_keys($byId), array_keys($signals), array_keys($votedOut)));
        foreach ($ids as $id) {
            $p = $byId[$id] ?? null;
            $reasons = [];
            $score = 0.0;
            $add = function (float $points, string $text, string $kind) use (&$score, &$reasons) {
                $score += $points;
                $reasons[] = ['points' => round($points, 1), 'text' => $text, 'kind' => $kind];
            };

            if ($p) {
                $established = $p['mins'] >= self::limit('minMinutes');
                $new = !$established && $p['games'] < self::limit('newGames');
                if ($established || $new) {
                    $top = self::limit('topPercentile');
                    $base = $top - 0.02; // points grow from just below the cut-off
                    $hsPct = $pct('hs_rate', $p['hs_rate']);
                    if ($hsPct !== null && $hsPct >= $top) {
                        $add(($hsPct - $base) * 100, sprintf('Sniper headshots %d%% of %d sniper kills (average %d%%, %s)',
                            round($p['hs_rate'] * 100), $p['sniper'], round($avg('hs_rate') * 100), self::top($hsPct)), 'aim');
                    }
                    $kpmPct = $pct('kpm', $p['kpm']);
                    if ($kpmPct >= $top) {
                        $add(($kpmPct - $base) * 60, sprintf('%.1f kills per minute (average %.1f, %s)', $p['kpm'], $avg('kpm'), self::top($kpmPct)), 'kills');
                    }
                    $kdPct = $pct('kd', $p['kd']);
                    if ($kdPct >= $top) {
                        $add(($kdPct - $base) * 60, sprintf('K/D %.2f (average %.2f, %s)', $p['kd'], $avg('kd'), self::top($kdPct)), 'kills');
                    }
                    if ($new && ($kpmPct >= 0.9 || $kdPct >= 0.9)) {
                        $add(1.0, sprintf('New profile (%d games) already near the top', $p['games']), 'new');
                    }
                }
            }
            if (isset($jumps[$id])) {
                $j = $jumps[$id];
                $add(min(3.0, ($j['ratio'] - 1) * 2), sprintf('Kills per minute jumped from %.1f to %.1f in the last %d games',
                    $j['before'], $j['recent'], self::limit('recentGames')), 'jump');
            }
            if (isset($signals[$id])) {
                $s = $signals[$id];
                $hours = max(1.0, ($p['mins'] ?? 60) / 60);
                $detail = fn(?float $max, ?int $ping) => implode(', ', array_filter([
                    $max ? sprintf('up to %.0f cubes', $max) : null,
                    $ping !== null ? sprintf('ping %d ms on average%s', $ping, $ping >= self::limit('lagPing') ? ' - lag?' : '') : null,
                ]));
                if ($s['flag_blocked'] && $s['flag_weight'] > 0) {
                    $add(min(4.0, $s['flag_weight']), sprintf('%d flag touches from too far away, blocked by the server (%s)',
                        $s['flag_blocked'], $detail($s['flag_max'], $s['flag_ping'])), 'server');
                }
                if ($s['item_blocked'] && $s['item_weight'] > 0) {
                    $add(min(3.0, $s['item_weight'] / $hours * 2), sprintf('%d pickups from too far away, blocked by the server (%s)',
                        $s['item_blocked'], $detail($s['item_max'], $s['item_ping'])), 'server');
                }
                if ($s['far'] >= 5) {
                    $add(min(1.0, $s['far'] / $hours / 4), sprintf('%d flag touches / pickups a bit too far (lag?)', $s['far']), 'server');
                }
                if ($s['bursts']) {
                    // per 1000 kills where the kills are known, else a little each
                    $points = ($p['kills'] ?? 0) >= 500
                        ? $s['bursts'] / $p['kills'] * 1000 * 1.5
                        : $s['bursts'] * 0.3;
                    $add(min(3.0, $points), sprintf('%d kill bursts (%d+ kills in %d seconds, best %d)',
                        $s['bursts'], CheatSignals::limit('burstKills'), CheatSignals::limit('burstSeconds'), $s['burst_max']), 'burst');
                }
            }
            if (isset($votedOut[$id])) {
                $n = array_sum($votedOut[$id]);
                $add(min(2.0, $n * 0.5), 'Voted out: ' . implode(', ', array_map(fn($k, $v) => ($k === 'ban' ? 'banned' : 'kicked') . " {$v}×", array_keys($votedOut[$id]), $votedOut[$id])), 'votes');
            }

            if ($score >= self::limit('minScore')) {
                $list[$id] = ['player_id' => $id, 'score' => round($score, 1), 'reasons' => $reasons, 'stats' => $p, 'signals' => $signals[$id] ?? null];
            }
        }

        if (!$list) {
            return [];
        }
        $players = $conn->execute(
            'SELECT p.id, p.name, p.country, r.status, r.note, r.reviewed_at FROM players p
             LEFT JOIN player_reviews r ON r.player_id = p.id
             WHERE p.id IN (' . implode(',', array_fill(0, count($list), '?')) . ')', array_keys($list))->fetchAll('assoc');
        foreach ($players as $pl) {
            $list[$pl['id']] += [
                'name' => $pl['name'],
                'country' => $pl['country'],
                'review' => $pl['status'] ? ['status' => $pl['status'], 'note' => $pl['note'], 'at' => $pl['reviewed_at']] : null,
            ];
        }
        $list = array_filter($list, fn($p) => isset($p['name']) && ($includeCleared || ($p['review']['status'] ?? null) !== 'ok'));
        usort($list, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_values($list);
    }

    /** "top 2%" from a percentile (0..1) */
    private static function top(float $pct): string
    {
        $top = (1 - $pct) * 100;

        return $top < 1 ? 'top 1%' : 'top ' . (int)ceil($top) . '%';
    }

    /**
     * Players whose kills per minute in their last recentGames are jumpRatio
     * times those of the games before (at least as many before).
     *
     * @return array<string, array{recent: float, before: float, ratio: float}>
     */
    private function jumps($conn): array
    {
        $rows = $conn->execute(
            'SELECT player_id,
                    SUM(IF(rn <= ?, kills, 0)) rk, SUM(IF(rn <= ?, minutes_played, 0)) rm,
                    SUM(IF(rn > ?, kills, 0)) bk, SUM(IF(rn > ?, minutes_played, 0)) bm, MAX(rn) n
             FROM (SELECT s.player_id, s.kills, s.minutes_played,
                          ROW_NUMBER() OVER (PARTITION BY s.player_id ORDER BY g.started_at DESC) rn
                   FROM player_stats_per_game s
                   INNER JOIN games g ON g.id = s.game_id
                   WHERE g.inaccurate = 0 AND s.minutes_played > 0) x
             GROUP BY player_id
             HAVING n >= ? AND rm >= 100 AND bm >= 100',
            array_fill(0, 4, self::limit('recentGames')) + [4 => self::limit('recentGames') * 2])->fetchAll('assoc');
        $jumps = [];
        foreach ($rows as $r) {
            $recent = $r['rk'] / $r['rm'];
            $before = $r['bk'] / $r['bm'];
            if ($before > 0 && $recent / $before >= self::limit('jumpRatio') && $recent >= 3.0) {
                $jumps[$r['player_id']] = ['recent' => $recent, 'before' => $before, 'ratio' => $recent / $before];
            }
        }

        return $jumps;
    }

    /**
     * The server's warnings and kill bursts per player (events 'anticheat').
     * Flag touches / pickups at an unknown position (lag, spawning) don't count;
     * blocked ones weigh less the higher the ping was (*_weight, lagWeight()).
     */
    private function signals($conn): array
    {
        $rows = $conn->execute(
            "SELECT actor_id,
                    SUM(kind = 'flag' AND blocked AND NOT unknown) flag_blocked,
                    SUM(IF(kind = 'flag' AND blocked AND NOT unknown, w, 0)) flag_weight,
                    AVG(IF(kind = 'flag' AND blocked AND NOT unknown, ping, NULL)) flag_ping,
                    MAX(IF(kind = 'flag' AND blocked, dist, NULL)) flag_max,
                    SUM(kind = 'item' AND blocked AND NOT unknown) item_blocked,
                    SUM(IF(kind = 'item' AND blocked AND NOT unknown, w, 0)) item_weight,
                    AVG(IF(kind = 'item' AND blocked AND NOT unknown, ping, NULL)) item_ping,
                    MAX(IF(kind = 'item' AND blocked, dist, NULL)) item_max,
                    SUM(kind IN ('flag', 'item') AND NOT blocked AND NOT unknown) far,
                    SUM(kind = 'burst') bursts, MAX(IF(kind = 'burst', kills, NULL)) burst_max
             FROM (SELECT actor_id, JSON_VALUE(details, '$.kind') kind,
                          JSON_VALUE(details, '$.blocked') IN ('true', '1') blocked, -- MySQL gives '1', MariaDB 'true'
                          JSON_VALUE(details, '$.unknown_position') IS NOT NULL unknown,
                          CAST(JSON_VALUE(details, '$.dist') AS DECIMAL(12,1)) dist,
                          CAST(JSON_VALUE(details, '$.kills') AS UNSIGNED) kills,
                          CAST(JSON_VALUE(details, '$.ping') AS UNSIGNED) ping,
                          " . self::lagWeightSql("CAST(JSON_VALUE(details, '$.ping') AS UNSIGNED)") . " w
                   FROM events WHERE type = 'anticheat' AND actor_id IS NOT NULL) x
             GROUP BY actor_id")->fetchAll('assoc');
        $out = [];
        foreach ($rows as $r) {
            $out[$r['actor_id']] = [
                'flag_blocked' => (int)$r['flag_blocked'], 'flag_weight' => (float)$r['flag_weight'],
                'flag_ping' => $r['flag_ping'] !== null ? (int)round((float)$r['flag_ping']) : null,
                'flag_max' => $r['flag_max'] !== null ? (float)$r['flag_max'] : null,
                'item_blocked' => (int)$r['item_blocked'], 'item_weight' => (float)$r['item_weight'],
                'item_ping' => $r['item_ping'] !== null ? (int)round((float)$r['item_ping']) : null,
                'item_max' => $r['item_max'] !== null ? (float)$r['item_max'] : null,
                'far' => (int)$r['far'], 'bursts' => (int)$r['bursts'], 'burst_max' => (int)$r['burst_max'],
            ];
        }

        return $out;
    }

    /**
     * How much a blocked warning counts at a ping: full below lagPing (or
     * unknown), half up to twice that, a quarter above - lag explains more
     * the higher the ping.
     */

    public static function lagWeight(?int $ping): float
    {
        return $ping === null || $ping < self::limit('lagPing') ? 1.0 : ($ping < 2 * self::limit('lagPing') ? 0.5 : 0.25);
    }

    private static function lagWeightSql(string $ping): string
    {
        return sprintf('CASE WHEN %1$s IS NULL OR %1$s < %2$d THEN 1 WHEN %1$s < %3$d THEN 0.5 ELSE 0.25 END',
            $ping, self::limit('lagPing'), 2 * self::limit('lagPing'));
    }
}
