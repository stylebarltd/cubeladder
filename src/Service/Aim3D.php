<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * Run & Gun (/run; the page is webroot/map3d/aim3d.js and templates/Run):
 * ROUND_MS on a real map. Collect the map's fixed pickup course in order and
 * shoot the players standing at the map's spawn points (two at a time, the
 * same order for everyone), with AC's assault rifle.
 *
 * Score: body 10, head 20, +10 for every 5 hits in a row (a miss ends the
 * row), PICKUP points per course pickup in order, and when the course is done
 * before the time runs out, BONUS_PER_S for every second left - the round
 * ends there. So it pays to be fast and to shoot well.
 *
 * The page sends every shot as [t ms of game time, target index or -1, head
 * 0/1] and its path (as the flag run). The server replays everything: the
 * targets (0 and 1 up from the start, each hit brings the next index
 * RESPAWN_MS later; a hit counts only on a target that is up), the rifle's
 * rate (attackdelay, magazine, reload), human reaction times, the path
 * (walkable speed), the pickups (passed in order on the path, AC's reach) and
 * the game clock against the server's. The score is computed here, never
 * taken from the page. Just for fun, not part of the ladder; only recognised
 * players are on the board.
 */
class Aim3D
{
    public const MODE = 'rungun';
    public const ROUND_MS = 90000;
    public const COUNTDOWN_MS = 3000;
    public const ALIVE = 5;
    public const RESPAWN_MS = 300;
    /** server.h guns[GUN_ASSAULT] */
    public const ATTACKDELAY = 120;
    public const MAGSIZE = 20;
    public const RELOAD_MS = 2000;
    public const POINTS_BODY = 10;
    public const POINTS_HEAD = 20;
    public const POINTS_STREAK = 10;
    public const STREAK = 5;
    public const POINTS_PICKUP = 25;
    public const BONUS_PER_S = 5;
    /** a hit faster than this after the target came up is luck; a few are allowed */
    public const MIN_REACTION = 120;
    public const LUCKY = 3;
    private const TOKEN_TTL = 900;

    public function start(string $map, ?string $playerId): array
    {
        if (!FlagRun::onTab(self::MODE, $map)) {
            return ['error' => 'Unknown map.'];
        }
        $token = bin2hex(random_bytes(16));
        $conn = ConnectionManager::get('default');
        $conn->execute('INSERT INTO aim3d_rounds (token, map, mode, player_id, started_ms) VALUES (?, ?, ?, ?, ?)',
            [$token, $map, self::MODE, $playerId, self::nowMs()]);
        $conn->execute('DELETE FROM aim3d_rounds WHERE finished_at IS NULL AND started_ms < ?', [self::nowMs() - 86400 * 1000]);

        return ['token' => $token, 'map' => $map, 'mode' => self::MODE, 'countdown' => self::COUNTDOWN_MS, 'round' => self::ROUND_MS, 'sample' => FlagRun::SAMPLE_MS];
    }

    public function finish(string $token, int $endMs, array $shots, array $trace): array
    {
        $conn = ConnectionManager::get('default');
        $round = $conn->execute('SELECT id, map, player_id, started_ms, finished_at FROM aim3d_rounds WHERE token = ?', [$token])->fetch('assoc');
        if (!$round || $round['finished_at'] !== null) {
            return ['error' => 'This round is unknown or already over.'];
        }
        $map = FlagRun::maps()[$round['map']] ?? null;
        if (!$map) {
            return ['error' => 'Unknown map.'];
        }
        $server = self::nowMs() - (int)$round['started_ms'] - self::COUNTDOWN_MS;
        if ($server > self::TOKEN_TTL * 1000) {
            return ['error' => 'This round took too long.'];
        }
        if ($endMs <= 0 || $endMs > self::ROUND_MS + 50) {
            return ['error' => 'This round makes no sense.'];
        }
        if ($endMs > $server + FlagRun::CLOCK_SLACK_MS || $endMs < ($server - FlagRun::CLOCK_SLACK_MS) * FlagRun::CLOCK_MIN_RATIO) {
            return ['error' => 'The game clock did not keep up with real time (the game ran too fast or too slow).'];
        }
        [$pathError, $pts] = FlagRun::path($trace, $endMs);
        if ($pathError !== null) {
            return ['error' => $pathError];
        }
        // the course: pickups passed in order on the path; the round may end early only when all are done
        $items = array_values($map['parkour']['items'] ?? []);
        [$pickups, $doneAt] = FlagRun::passInOrder($pts, $items);
        if ($endMs < self::ROUND_MS - 2 * FlagRun::SAMPLE_MS && ($pickups < count($items) || $doneAt === null)) {
            return ['error' => 'This round ended before the time was up, but the course was not done.'];
        }
        $r = self::replay($shots, $endMs);
        if (isset($r['error'])) {
            return $r;
        }
        $done = $items && $pickups === count($items);
        $bonus = $done ? intdiv(max(0, self::ROUND_MS - $endMs), 1000) * self::BONUS_PER_S : 0;
        $r['pickups'] = $pickups;
        $r['course'] = count($items);
        $r['bonus'] = $bonus;
        $r['shooting'] = $r['score'];
        $r['score'] += $pickups * self::POINTS_PICKUP + $bonus;
        $r['end_ms'] = $done ? $endMs : null;
        $conn->execute('UPDATE aim3d_rounds SET finished_at = ?, score = ?, shots = ?, hits = ?, headshots = ?, pickups = ?, end_ms = ?, avg_reaction_ms = ?
            WHERE id = ? AND finished_at IS NULL',
            [gmdate('Y-m-d H:i:s'), $r['score'], $r['shots'], $r['hits'], $r['headshots'], $pickups, $r['end_ms'], $r['avg_reaction_ms'], $round['id']]);
        $out = $r + ['map' => $round['map'], 'mode' => self::MODE, 'player' => $round['player_id'] !== null];
        if ($round['player_id'] !== null) {
            $out['best'] = (int)$conn->execute('SELECT MAX(score) FROM aim3d_rounds WHERE player_id = ? AND map = ? AND mode = ? AND finished_at IS NOT NULL',
                [$round['player_id'], $round['map'], self::MODE])->fetchColumn(0);
            $out['rank'] = $this->rank((string)$round['player_id'], $round['map'], FlagRun::weekStart());
            $out['allTimeRank'] = $this->rank((string)$round['player_id'], $round['map'], '1970-01-01');
        }

        return $out;
    }

    /**
     * Replays the shots up to the end of the round (see the class comment).
     *
     * @return array{error?:string, score?:int, shots?:int, hits?:int, headshots?:int, avg_reaction_ms?:?int}
     */
    public static function replay(array $shots, int $endMs = self::ROUND_MS): array
    {
        $up = [];                                   // target index -> time it came up
        for ($k = 0; $k < self::ALIVE; $k++) {
            $up[$k] = 0;
        }
        $next = self::ALIVE;
        $score = $streak = $hits = $heads = $lucky = 0;
        $reactions = [];
        $last = -1e9;
        $inMag = 0;
        if (count($shots) > (int)($endMs / self::ATTACKDELAY) + 1) {
            return ['error' => 'This round makes no sense.'];
        }
        foreach ($shots as $s) {
            if (!is_array($s) || count($s) !== 3) {
                return ['error' => 'This round makes no sense.'];
            }
            [$t, $k, $head] = [(int)$s[0], (int)$s[1], (int)$s[2]];
            if ($t < 0 || $t > $endMs + 50) {
                return ['error' => 'This round makes no sense.'];
            }
            // the rifle's rate: attackdelay between shots, a reload after a full magazine
            $gap = $t - $last;
            if ($gap >= self::RELOAD_MS - 50) {
                $inMag = 0;
            }
            if ($gap < self::ATTACKDELAY - 10 || $inMag >= self::MAGSIZE) {
                return ['error' => 'This round is not humanly possible (fire rate).'];
            }
            $inMag++;
            $last = $t;
            if ($k < 0) {
                $streak = 0;
                continue;
            }
            if (!isset($up[$k]) || $t < $up[$k]) {
                return ['error' => 'This round hit a target that was not there.'];
            }
            $reaction = $t - $up[$k];
            if ($reaction < self::MIN_REACTION && ++$lucky > self::LUCKY) {
                return ['error' => 'This round is not humanly possible (reaction times).'];
            }
            $reactions[] = $reaction;
            unset($up[$k]);
            $up[$next++] = $t + self::RESPAWN_MS;
            $hits++;
            $heads += $head ? 1 : 0;
            $score += $head ? self::POINTS_HEAD : self::POINTS_BODY;
            if (++$streak % self::STREAK === 0) {
                $score += self::POINTS_STREAK;
            }
        }

        return ['score' => $score, 'shots' => count($shots), 'hits' => $hits, 'headshots' => $heads,
            'avg_reaction_ms' => $reactions ? (int)round(array_sum($reactions) / count($reactions)) : null];
    }

    /** Each player's best round on a map since a date, highest first. */
    public function board(string $map, ?string $since, int $limit = 10): array
    {
        $rows = ConnectionManager::get('default')->execute("
            SELECT player_id, name, country, score, shots, hits, pickups, end_ms, finished_at FROM (
                SELECT r.player_id, p.name, p.country, r.score, r.shots, r.hits, r.pickups, r.end_ms, r.finished_at,
                       ROW_NUMBER() OVER (PARTITION BY r.player_id ORDER BY r.score DESC, r.finished_at ASC) AS rn
                FROM aim3d_rounds r INNER JOIN players p ON p.id = r.player_id
                WHERE r.finished_at IS NOT NULL AND r.map = ? AND r.mode = ? AND r.finished_at >= ? AND p.track = 1 AND p.cheater = 0
            ) best WHERE rn = 1
            ORDER BY score DESC, finished_at ASC LIMIT " . (int)$limit,
            [$map, self::MODE, $since ?? '1970-01-01'])->fetchAll('assoc');

        return array_map(fn($r) => [
            'player_id' => $r['player_id'], 'name' => $r['name'], 'country' => $r['country'], 'score' => (int)$r['score'],
            'accuracy' => (int)$r['shots'] ? (int)round(100 * $r['hits'] / $r['shots']) : 0,
            'pickups' => (int)$r['pickups'], 'finished_at' => (string)$r['finished_at'],
        ], $rows);
    }

    private function rank(string $playerId, string $map, string $since): int
    {
        $conn = ConnectionManager::get('default');
        $mine = (int)$conn->execute('SELECT MAX(score) FROM aim3d_rounds WHERE player_id = ? AND map = ? AND mode = ? AND finished_at >= ?',
            [$playerId, $map, self::MODE, $since])->fetchColumn(0);

        return 1 + (int)$conn->execute(
            'SELECT COUNT(*) FROM (SELECT r.player_id FROM aim3d_rounds r INNER JOIN players p ON p.id = r.player_id
              WHERE r.map = ? AND r.mode = ? AND r.finished_at >= ? AND p.track = 1 AND p.cheater = 0 AND r.player_id <> ?
              GROUP BY r.player_id HAVING MAX(r.score) > ?) better', [$map, self::MODE, $since, $playerId, $mine])->fetchColumn(0);
    }

    private static function nowMs(): int
    {
        return (int)floor(microtime(true) * 1000);
    }
}
