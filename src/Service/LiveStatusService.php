<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Core\Configure;

/**
 * "On air": is a game going on anywhere - our servers (own + clan / inter,
 * Ladder.servers) first, then every other public AssaultCube server (like
 * "Elsewhere" on the Live page)? Drives the green dot on "Live" in the nav.
 *
 * Cheapest source first: the state files the discord_live cron rewrites
 * every few seconds (both feeds). When they are stale (cron not running,
 * e.g. locally) our servers are polled directly; other servers come from
 * the master server list. Polls are cached in "live_slow" (30 s).
 */
class LiveStatusService
{
    /** Players needed on one server for it to count as a live game */
    public const MIN_PLAYERS = 1;

    /** A state file older than this is not trusted */
    private const STATE_TTL = 120;

    /**
     * @param bool $poll Poll the servers when the state files are stale
     *   (false for page renders - never wait for UDP there)
     * @return array{live: bool, players: int, server: ?string}
     */
    public function onAir(bool $poll = true): array
    {
        $counts = $this->countsFromState();
        if ($counts === null) {
            if (!$poll) {
                return ['live' => false, 'players' => 0, 'server' => null];
            }
            $counts = Cache::remember('onair_counts', fn() => $this->countsFromServers(), 'live_slow');
        }

        arsort($counts);
        $key = array_key_first($counts);
        $players = $key !== null ? (int)$counts[$key] : 0;
        if ($players >= self::MIN_PLAYERS) {
            $servers = Configure::read('Ladder.servers') ?: [];

            return ['live' => true, 'players' => $players, 'server' => $servers[$key]['name'] ?? $key];
        }

        // nothing on our servers: any other public server?
        if ($poll) {
            $other = Cache::remember('onair_elsewhere', fn() => $this->busiestElsewhere(), 'live_slow');
            if ($other && $other['players'] >= self::MIN_PLAYERS) {
                return ['live' => true] + $other;
            }
        }

        return ['live' => false, 'players' => 0, 'server' => null];
    }

    /**
     * Busiest other public server from the master list (ours excluded).
     *
     * @return array{players: int, server: string}|null
     */
    private function busiestElsewhere(): ?array
    {
        $ownAddr = [];
        foreach (Configure::read('Ladder.servers') ?: [] as $cfg) {
            $ownAddr[gethostbyname($cfg['host']) . ':' . (int)$cfg['port']] = true;
        }
        $targets = array_diff_key((new AcMasterServerService())->servers(), $ownAddr);

        $best = null;
        foreach ((new AcExtInfoService())->queryMany($targets) as $key => $info) {
            $n = !empty($info['online']) ? (int)$info['numplayers'] : 0;
            if ($n > ($best['players'] ?? 0)) {
                $best = ['players' => $n, 'server' => trim((string)($info['description'] ?? '')) ?: $key];
            }
        }

        return $best;
    }

    /**
     * Player counts per server from the fresh cron state files, or null.
     */
    private function countsFromState(): ?array
    {
        $paths = [Configure::read('Ladder.discord.state') ?: TMP . 'discord_live.json'];
        foreach (Configure::read('Ladder.discord.feeds') ?: [] as $name => $feed) {
            $paths[] = $feed['state'] ?? TMP . 'discord_live_' . $name . '.json';
        }

        $counts = null;
        foreach ($paths as $path) {
            if (!is_file($path) || time() - filemtime($path) > self::STATE_TTL) {
                continue;
            }
            $state = json_decode((string)file_get_contents($path), true) ?: [];
            $counts = ($counts ?? []) + array_map('intval', $state['counts'] ?? []);
        }

        return $counts;
    }

    private function countsFromServers(): array
    {
        $counts = [];
        $targets = Configure::read('Ladder.servers') ?: [];
        foreach ((new AcExtInfoService())->queryMany($targets) as $key => $info) {
            $counts[$key] = !empty($info['online']) ? (int)$info['numplayers'] : 0;
        }

        return $counts;
    }
}
