<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * Flag run (/run): run from your flag to the enemy flag and bring it home, in
 * the browser, on the real maps with AssaultCube's own movement (the page
 * ports physics.cpp). The best time per map is on the board.
 *
 * A run starts with a one-time token when the countdown begins (the server
 * notes the time). At the end the page sends its time and the path it ran:
 * one sample every SAMPLE_MS of game time, [t ms, x, y, z] in tenths of a
 * cube. The run counts when
 * - the server's own clock agrees with the time (a slowed or skipped game
 *   clock does not pay off),
 * - the path starts at the own flag, touches the enemy flag and ends at the
 *   own flag (AC's pickup distance, entities.cpp),
 * - no step between two samples is faster than a player can move,
 * - the time is not below the straight line flag to flag and back at top speed
 *   (not the walk grid's route: players cut corners and jump off roofs, and a
 *   valid 8.x s run on ac_favela was refused by that, 2026-11-10),
 * - no sample stands in a solid cube and no step passes through one (noclip,
 *   teleports): flagrun-walls.json, tools/flagrun/walls.mjs.
 * Modes: `flag` (to the enemy flag and back) and `parkour` (the map's fixed
 * course of pickups, in its order, then home: the same course for everyone,
 * so times compare). A parkour path must pass every pickup in order (AC's
 * pickup reach, entities.cpp checkitems).
 * Flags, routes and courses come from webroot/map3d/maps/flagrun.json
 * (tools/flagrun/routes.mjs). Just for fun: not part of the ladder, and only
 * recognised players are on the board, as with the aim trainer.
 */
class FlagRun
{
    public const COUNTDOWN_MS = 3000;
    public const SAMPLE_MS = 100;
    /** AC picks up a flag within 2.5 of the eye (entities.cpp); a sample every 100 ms can miss that by a step */
    public const REACH = 2.5 + 1.0;
    /** Horizontal speed between two samples, cubes per second: AC runs 16, strafe double jumps add 25 % each */
    public const MAX_SPEED = 32.0;
    /** The fastest a route can be run on average, cubes per second (top speed with jumps) */
    public const ROUTE_SPEED = 24.0;
    /** How far one step's straight line may run inside solid cubes: the player keeps 1.1 off walls, so a real step only grazes a corner */
    public const WALL_DEPTH = 1.0;
    /** Server clock vs. the run's time: network and a stalled frame or two */
    public const CLOCK_SLACK_MS = 3000;
    /** A game clock slower than this share of real time is slow motion, not a stall */
    public const CLOCK_MIN_RATIO = 0.75;
    private const TOKEN_TTL = 900;
    private const MAX_SAMPLES = 6000;
    public const MODES = ['flag', 'parkour'];

    /**
     * The tabs of /run and their maps (in this order). flag: the flag run;
     * rungun: Run & Gun (Aim3D); qr: the QR hunt (free walking, no board).
     */
    public const TABS = [
        'flag' => ['ac_werk', 'ac_casa', 'ac_favela', 'ac_desert3'],
        'rungun' => ['ac_village', 'ac_werk', 'ac_dusk', 'ac_ingress'],
        'qr' => ['cl_angkorwat', 'cl_lost', 'cl_cube'],
    ];

    /** Is the map on the tab, and does it have what the tab needs (flags, a course)? */
    public static function onTab(string $tab, string $map): bool
    {
        $m = self::maps()[$map] ?? null;
        if (!$m || !in_array($map, self::TABS[$tab] ?? [], true)) {
            return false;
        }

        return match ($tab) {
            'flag' => !empty($m['flags']),
            'rungun' => !empty($m['parkour']['items']),
            default => true,
        };
    }

    /** @return array<string, array{title:string, flags:array, route:float}> the maps with flags (flagrun.json) */
    public static function maps(): array
    {
        static $maps = null;
        if ($maps === null) {
            $file = WWW_ROOT . 'map3d/maps/flagrun.json';
            $maps = is_file($file) ? (array)json_decode((string)file_get_contents($file), true) : [];
        }

        return $maps;
    }

    /** Does the map have this mode (parkour needs a course)? */
    public static function has(string $map, string $mode): bool
    {
        return $mode === 'flag' && self::onTab('flag', $map);
    }

    public function start(string $map, string $mode, ?string $playerId): array
    {
        if (!self::has($map, $mode)) {
            return ['error' => 'Unknown map or mode.'];
        }
        $token = bin2hex(random_bytes(16));
        $conn = ConnectionManager::get('default');
        $conn->execute('INSERT INTO flag_runs (token, map, mode, player_id, started_ms) VALUES (?, ?, ?, ?, ?)',
            [$token, $map, $mode, $playerId, self::nowMs()]);
        $conn->execute('DELETE FROM flag_runs WHERE finished_at IS NULL AND started_ms < ?', [self::nowMs() - 86400 * 1000]);

        return ['token' => $token, 'map' => $map, 'mode' => $mode, 'countdown' => self::COUNTDOWN_MS, 'sample' => self::SAMPLE_MS];
    }

    /**
     * @param list<array{0:int,1:int,2:int,3:int}> $trace
     * @return array{error?:string, time_ms?:int, ...}
     */
    public function finish(string $token, int $timeMs, array $trace): array
    {
        $conn = ConnectionManager::get('default');
        $run = $conn->execute('SELECT id, map, mode, player_id, started_ms, finished_at FROM flag_runs WHERE token = ?', [$token])->fetch('assoc');
        if (!$run || $run['finished_at'] !== null) {
            return ['error' => 'This run is unknown or already over.'];
        }
        $map = self::maps()[$run['map']] ?? null;
        if (!$map) {
            return ['error' => 'Unknown map.'];
        }
        $server = self::nowMs() - (int)$run['started_ms'] - self::COUNTDOWN_MS;
        if ($server > self::TOKEN_TTL * 1000) {
            return ['error' => 'This run took too long.'];
        }
        // faster than real time is a speed hack; a bit slower is a stall (the game skips time it could not
        // catch up), much slower is slow motion
        if ($timeMs <= 0 || $timeMs > $server + self::CLOCK_SLACK_MS || $timeMs < ($server - self::CLOCK_SLACK_MS) * self::CLOCK_MIN_RATIO) {
            return ['error' => 'The game clock did not keep up with real time (the game ran too fast or too slow).'];
        }
        $check = self::checkTrace($trace, $timeMs, $map, (string)$run['mode'], (string)$run['map']);
        if ($check !== null) {
            return ['error' => $check];
        }
        $conn->execute('UPDATE flag_runs SET finished_at = ?, time_ms = ?, samples = ? WHERE id = ? AND finished_at IS NULL',
            [gmdate('Y-m-d H:i:s'), $timeMs, count($trace), $run['id']]);

        $out = ['time_ms' => $timeMs, 'map' => $run['map'], 'mode' => $run['mode'], 'player' => $run['player_id'] !== null];
        if ($run['player_id'] !== null) {
            $out['best'] = (int)$conn->execute('SELECT MIN(time_ms) FROM flag_runs WHERE player_id = ? AND map = ? AND mode = ? AND finished_at IS NOT NULL',
                [$run['player_id'], $run['map'], $run['mode']])->fetchColumn(0);
            $out['rank'] = $this->rank((string)$run['player_id'], $run['map'], $run['mode'], self::weekStart());
            $out['allTimeRank'] = $this->rank((string)$run['player_id'], $run['map'], $run['mode'], '1970-01-01');
        }

        return $out;
    }

    /**
     * The path as [t, x, y, z] samples (tenths of a cube), or an error text:
     * one sample per SAMPLE_MS up to the end, no step faster than a player.
     *
     * @return array{0: ?string, 1: list<array{0:int,1:float,2:float,3:float}>}
     */
    private static function parsePath(array $trace, int $timeMs): array
    {
        $n = count($trace);
        if ($n < 2 || $n > self::MAX_SAMPLES) {
            return ['This run makes no sense.', []];
        }
        $pts = [];
        foreach ($trace as $s) {
            if (!is_array($s) || count($s) !== 4) {
                return ['This run makes no sense.', []];
            }
            $pts[] = [(int)$s[0], $s[1] / 10, $s[2] / 10, $s[3] / 10];
        }
        if ($n < $timeMs / self::SAMPLE_MS * 0.8 || abs($pts[$n - 1][0] - $timeMs) > 2 * self::SAMPLE_MS) {
            return ['This run makes no sense.', []];
        }
        for ($i = 1; $i < $n; $i++) {
            $dt = $pts[$i][0] - $pts[$i - 1][0];
            if ($dt <= 0) {
                return ['This run makes no sense.', []];
            }
            if (hypot($pts[$i][1] - $pts[$i - 1][1], $pts[$i][2] - $pts[$i - 1][2]) > self::MAX_SPEED * $dt / 1000 + 0.5) {
                return ['This run is not humanly possible.', []];
            }
        }

        return [null, $pts];
    }

    /** A path walkable at player speed (Run & Gun shares this): [error or null, samples]. */
    public static function path(array $trace, int $timeMs): array
    {
        return self::parsePath($trace, $timeMs);
    }

    /**
     * How many of the targets (pickups, flags) the path passes in their order,
     * and when it passed the last of them (null when not all).
     *
     * @return array{0:int, 1:?int}
     */
    public static function passInOrder(array $pts, array $targets): array
    {
        $next = 0;
        $at = null;
        foreach ($pts as $p) {
            if ($next < count($targets) && self::near($p, $targets[$next])) {
                $next++;
                if ($next === count($targets)) {
                    $at = $p[0];
                }
            }
        }

        return [$next, $at];
    }

    private static function near(array $p, array $f): bool
    {
        return hypot($p[1] - $f['x'], $p[2] - $f['y']) <= self::REACH && abs($p[3] - $f['z']) <= 6;
    }

    /** Why a path is not a run, or null when it is (see the class comment). */
    public static function checkTrace(array $trace, int $timeMs, array $map, string $mode = 'flag', ?string $name = null): ?string
    {
        [$error, $pts] = self::parsePath($trace, $timeMs);
        if ($error !== null) {
            return $error;
        }
        $n = count($pts);
        $own = $map['flags']['cla'];
        $enemy = $map['flags']['rvsf'];
        // the shortest conceivable way: straight lines from target to target (each reached at pickup
        // distance), at top speed. The walk grid's route is longer than what good players run.
        $stops = array_merge([$own], $mode === 'parkour' ? array_values($map['parkour']['items'] ?? []) : [$enemy], [$own]);
        $least = 0.0;
        for ($i = 1; $i < count($stops); $i++) {
            $least += max(0.0, hypot($stops[$i]['x'] - $stops[$i - 1]['x'], $stops[$i]['y'] - $stops[$i - 1]['y']) - 2 * self::REACH);
        }
        if ($timeMs < $least / self::ROUTE_SPEED * 1000) {
            return 'This run is faster than the shortest way allows.';
        }
        if ($name !== null && ($wall = self::throughWall($pts, $name)) !== null) {
            return $wall;
        }
        $near = fn(array $p, array $f) => hypot($p[1] - $f['x'], $p[2] - $f['y']) <= self::REACH && abs($p[3] - $f['z']) <= 6;
        if (!$near($pts[0], $own) || !$near($pts[$n - 1], $own)) {
            return 'This run does not start and end at your flag.';
        }
        // the targets in order: the enemy flag, or the course's pickups
        $targets = $mode === 'parkour' ? array_values($map['parkour']['items']) : [$enemy];
        $next = 0;
        for ($i = 1; $i < $n && $next < count($targets); $i++) {
            if ($near($pts[$i], $targets[$next])) {
                $next++;
            }
        }
        if ($next < count($targets)) {
            return $mode === 'parkour' ? 'This run missed a pickup of the course (or took them out of order).' : 'This run never reached the enemy flag.';
        }

        return null;
    }

    /**
     * Why the path is impossible on the map's walls, or null: a sample inside a solid cube,
     * or a step whose straight line runs more than WALL_DEPTH inside solid cubes.
     *
     * @param list<array{0:int,1:float,2:float,3:float}> $pts
     */
    public static function throughWall(array $pts, string $name): ?string
    {
        $walls = self::walls($name);
        if ($walls === null) {
            return null;   // no wall data for this map: the other checks still apply
        }
        $prev = null;
        foreach ($pts as $p) {
            if (self::solidAt($walls, $p[1], $p[2])) {
                return 'This run goes through a wall.';
            }
            if ($prev !== null) {
                $len = hypot($p[1] - $prev[1], $p[2] - $prev[2]);
                $steps = (int)ceil($len / 0.1);
                $inside = 0;
                for ($k = 1; $k < $steps; $k++) {
                    $f = $k / $steps;
                    if (self::solidAt($walls, $prev[1] + ($p[1] - $prev[1]) * $f, $prev[2] + ($p[2] - $prev[2]) * $f)) {
                        if (++$inside * $len / $steps > self::WALL_DEPTH) {
                            return 'This run goes through a wall.';
                        }
                    } else {
                        $inside = 0;
                    }
                }
            }
            $prev = $p;
        }

        return null;
    }

    /** @return array{0:int, 1:string}|null [sfactor, one bit per cube] from flagrun-walls.json */
    private static function walls(string $name): ?array
    {
        static $all = null;
        if ($all === null) {
            $file = WWW_ROOT . 'map3d/maps/flagrun-walls.json';
            $all = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
        }
        if (!isset($all[$name]['solid'])) {
            return null;
        }

        return [(int)$all[$name]['sfactor'], (string)base64_decode($all[$name]['solid'])];
    }

    private static function solidAt(array $walls, float $x, float $y): bool
    {
        [$sf, $bits] = $walls;
        $cx = (int)floor($x);
        $cy = (int)floor($y);
        if ($cx < 0 || $cy < 0 || $cx >= (1 << $sf) || $cy >= (1 << $sf)) {
            return true;
        }
        $i = ($cy << $sf) + $cx;

        return ((ord($bits[$i >> 3]) >> ($i & 7)) & 1) === 1;
    }

    public static function weekStart(): string
    {
        return gmdate('Y-m-d 00:00:00', strtotime('monday this week 00:00 UTC'));
    }

    /**
     * Each player's best time on a map since a date, fastest first.
     *
     * @return list<array{player_id:string, name:string, country:?string, time_ms:int, finished_at:string}>
     */
    public function board(string $map, string $mode, ?string $since, int $limit = 10): array
    {
        $rows = ConnectionManager::get('default')->execute("
            SELECT player_id, name, country, time_ms, finished_at FROM (
                SELECT r.player_id, p.name, p.country, r.time_ms, r.finished_at,
                       ROW_NUMBER() OVER (PARTITION BY r.player_id ORDER BY r.time_ms ASC, r.finished_at ASC) AS rn
                FROM flag_runs r INNER JOIN players p ON p.id = r.player_id
                WHERE r.finished_at IS NOT NULL AND r.map = ? AND r.mode = ? AND r.finished_at >= ? AND p.track = 1 AND p.cheater = 0
            ) best WHERE rn = 1
            ORDER BY time_ms ASC, finished_at ASC LIMIT " . (int)$limit,
            [$map, $mode, $since ?? '1970-01-01'])->fetchAll('assoc');

        return array_map(fn($r) => [
            'player_id' => $r['player_id'], 'name' => $r['name'], 'country' => $r['country'],
            'time_ms' => (int)$r['time_ms'], 'finished_at' => (string)$r['finished_at'],
        ], $rows);
    }

    /** A player's place on a map since a date (1 = fastest) */
    private function rank(string $playerId, string $map, string $mode, string $since): int
    {
        $conn = ConnectionManager::get('default');
        $mine = (int)$conn->execute('SELECT MIN(time_ms) FROM flag_runs WHERE player_id = ? AND map = ? AND mode = ? AND finished_at >= ?',
            [$playerId, $map, $mode, $since])->fetchColumn(0);

        return 1 + (int)$conn->execute(
            'SELECT COUNT(*) FROM (SELECT r.player_id FROM flag_runs r INNER JOIN players p ON p.id = r.player_id
              WHERE r.map = ? AND r.mode = ? AND r.finished_at >= ? AND p.track = 1 AND p.cheater = 0 AND r.player_id <> ?
              GROUP BY r.player_id HAVING MIN(r.time_ms) < ?) better', [$map, $mode, $since, $playerId, $mine])->fetchColumn(0);
    }

    private static function nowMs(): int
    {
        return (int)floor(microtime(true) * 1000);
    }
}
