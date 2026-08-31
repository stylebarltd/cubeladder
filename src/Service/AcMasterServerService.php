<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;

/**
 * Public server list from the AssaultCube master server.
 *
 * The game client does GET /retrieve.do?action=list&name=..&version=..&build=..
 * and simply executes the cubescript that comes back; the lines we care about are
 *
 *     addserver <ip> <port> [weight]
 *
 * Note the master answers with an empty 200 unless name AND version are given.
 * The list is cached (cache config "master") and refreshed every Ladder.master.ttl
 * seconds; when the master is unreachable the last good list keeps being served.
 */
class AcMasterServerService
{
    public const DEFAULT_URL = 'http://ms.cubers.net/retrieve.do';
    public const CACHE_KEY = 'servers';
    public const AC_VERSION = 1303;

    /**
     * @return array<string, array{host:string, port:int}> keyed "ip:port" (game port)
     */
    public function servers(): array
    {
        $cfg = Configure::read('Ladder.master') ?: [];
        if (isset($cfg['enabled']) && !$cfg['enabled']) {
            return [];
        }

        $cached = Cache::read(self::CACHE_KEY, 'master');
        $ttl = (int)($cfg['ttl'] ?? 600);
        if (is_array($cached) && time() - ($cached['fetched'] ?? 0) < $ttl) {
            return $cached['servers'];
        }

        $fresh = $this->fetch($cfg['url'] ?? self::DEFAULT_URL);
        if ($fresh === null) {
            $stale = is_array($cached) ? $cached['servers'] : [];
            Log::warning(sprintf('AC master server unreachable, using last known list (%d servers)', count($stale)));
            // back off for a minute instead of hammering a dead master on every poll
            Cache::write(self::CACHE_KEY, ['fetched' => time() - $ttl + 60, 'servers' => $stale], 'master');
            return $stale;
        }

        Cache::write(self::CACHE_KEY, ['fetched' => time(), 'servers' => $fresh], 'master');
        return $fresh;
    }

    /**
     * Ask the master for its list. Null when it cannot be reached or answers
     * with nothing usable (the empty-body case).
     */
    public function fetch(string $url): ?array
    {
        try {
            $res = (new Client(['timeout' => 5]))->get($url, [
                'action' => 'list',
                'name' => 'cubeladder',
                'version' => self::AC_VERSION,
                'build' => 1 << 16,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AC master server request failed: ' . $e->getMessage());
            return null;
        }
        if (!$res->isOk()) {
            return null;
        }
        $list = self::parse((string)$res->getBody());
        return $list ?: null;
    }

    /**
     * Pick the `addserver ip port` lines out of the master's cubescript reply.
     *
     * @return array<string, array{host:string, port:int}>
     */
    public static function parse(string $body): array
    {
        $out = [];
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (!preg_match('/^\s*addserver\s+([\w.\-]+)\s+(\d{1,5})\b/i', $line, $m)) {
                continue;
            }
            $port = (int)$m[2];
            if ($port < 1 || $port > 65534) {
                continue;
            }
            $out["{$m[1]}:{$port}"] = ['host' => $m[1], 'port' => $port];
        }
        return $out;
    }
}
