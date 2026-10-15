<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Signs of cheating in a server log (events type 'anticheat'), shared by
 * AcLogParser (new games) and bin/cake backfill_anticheat (the old logs):
 *
 * - far flag touches / item pickups: the server allows for ping
 *   (9 + ping * 0.02 + packet jitter * 0.025 cubes) and logs anything beyond
 *   it - "touched" up to 1.5x that, "tried to touch" (blocked) further away.
 *   Speed and teleport hacks show up here, and very bad lag.
 *   "distance 14142135296.00" is a position the server didn't know yet.
 * - kill bursts: burstKills kills (grenades left out) within burstSeconds of
 *   the first one - rare in normal play. A burst is one fixed window: the
 *   next one starts after it ends.
 *
 * Each event carries the player's ping from the latest minute scoreboard
 * ('ping', when known): lag explains far touches, a low ping doesn't.
 *
 * Each event has a 'key' that is the same however often a log is read, so
 * re-reading it doesn't count anything twice.
 */
class CheatSignals
{
    /** The real values are in config/anticheat.php (not in git, SuspicionService::config()) - these only make it run */
    public const DEFAULTS = ['burstKills' => 6, 'burstSeconds' => 4];
    /** Distances from here on are an unknown position, not a real distance */
    public const UNKNOWN_DISTANCE = 1e9;

    private const FLAG_REGEX = '~^\[([0-9a-f:\.]+)\]\s+(.+?) (touched|tried to touch) the (CLA|RVSF) flag at distance ([\d.]+) \((\d+)\)$~';
    private const ITEM_REGEX = '~^\[([0-9a-f:\.]+)\]\s+(.+?) (picked|tried to pick) up entity #\d+ \((\w+)\), distance ([\d.]+) \((\d+)\)$~';

    /** killer name => kill times of the last burstSeconds */
    private array $kills = [];
    /** killer name => [start, kills] of their latest burst */
    private array $bursts = [];
    /** player name => ping in the latest minute scoreboard */
    private array $pings = [];

    public static function limit(string $key): int
    {
        return (int)(SuspicionService::config()[$key] ?? self::DEFAULTS[$key]);
    }

    /** A new game: kill windows start over */
    public function reset(): void
    {
        $this->kills = [];
        $this->bursts = [];
        // pings stay: the first minute scoreboard of a game comes after a minute
    }

    /**
     * Remembers the ping of a minute scoreboard row
     * ("cn name ... ping role host" - the ping right before the role).
     */
    public function notePing(string $rest): bool
    {
        if (!preg_match('~^\d+\s+(\S+)\s.*\s(-?\d+)\s+(?:normal|admin)\s+\S+$~', ltrim($rest), $m)) {
            return false;
        }
        $ping = (int)$m[2];
        // 0: not measured yet, 9999: unknown
        $this->pings[$m[1]] = $ping > 0 && $ping < 9999 ? $ping : null;

        return true;
    }

    public function pingOf(string $name): ?int
    {
        return $this->pings[$name] ?? null;
    }

    /**
     * A far flag touch or item pickup in this log line (without timestamp), or null.
     *
     * @return array{name: string, ip: string, key: string, details: array}|null
     */
    public function farPickup(string $rest, int $time): ?array
    {
        if (preg_match(self::FLAG_REGEX, $rest, $m)) {
            [$ip, $name, $blocked, $what, $dist, $n] = [$m[1], $m[2], $m[3] !== 'touched', $m[4] . ' flag', (float)$m[5], (int)$m[6]];
            $kind = 'flag';
        } elseif (preg_match(self::ITEM_REGEX, $rest, $m)) {
            [$ip, $name, $blocked, $what, $dist, $n] = [$m[1], $m[2], $m[3] !== 'picked', $m[4], (float)$m[5], (int)$m[6]];
            $kind = 'item';
        } else {
            return null;
        }

        return [
            'name' => $name,
            'ip' => $ip,
            'key' => date('Y-m-d H:i:s', $time) . '|' . $name . '|' . $rest,
            'details' => array_filter([
                'name' => $name, 'kind' => $kind, 'what' => $what, 'blocked' => $blocked,
                'dist' => $dist >= self::UNKNOWN_DISTANCE ? null : round($dist, 1),
                'unknown_position' => $dist >= self::UNKNOWN_DISTANCE ?: null,
                'n' => $n,
                'ping' => $this->pingOf($name),
            ], fn($v) => $v !== null),
        ];
    }

    /**
     * A kill (not a teamkill): a burst event once the killer has burstKills
     * within burstSeconds - the same key while the burst goes on, so one more
     * kill updates it.
     *
     * @return array{name: string, key: string, details: array}|null
     */
    public function kill(string $killer, string $verb, int $time): ?array
    {
        if (strtolower($verb) === 'gibbed') {
            return null; // one grenade can take several
        }
        $recent = array_values(array_filter($this->kills[$killer] ?? [], fn($t) => $time - $t <= self::limit('burstSeconds')));
        $recent[] = $time;
        $this->kills[$killer] = $recent;

        $burst = $this->bursts[$killer] ?? null;
        if ($burst && $time - $burst[0] <= self::limit('burstSeconds')) {
            // still in the window of the burst: one more kill of it
            $burst = [$burst[0], $burst[1] + 1, $time];
        } else {
            // a new burst only from kills after the last one's window
            $since = $burst ? $burst[0] + self::limit('burstSeconds') : PHP_INT_MIN;
            $recent = array_values(array_filter($recent, fn($t) => $t > $since));
            if (count($recent) < self::limit('burstKills')) {
                return null;
            }
            $burst = [$recent[0], count($recent), $time];
        }
        $this->bursts[$killer] = $burst;

        return [
            'name' => $killer,
            'key' => date('Y-m-d H:i:s', $burst[0]) . '|' . $killer . '|burst',
            'details' => array_filter(['name' => $killer, 'kind' => 'burst', 'kills' => $burst[1], 'seconds' => $burst[2] - $burst[0],
                'ping' => $this->pingOf($killer)], fn($v) => $v !== null),
        ];
    }

    /** events.event_hash of a signal */
    public static function hash(string $server, array $signal): string
    {
        return md5($server . '|anticheat|' . $signal['key']);
    }
}
