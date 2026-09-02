<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AcExtInfoService;
use App\Service\AcMasterServerService;
use Cake\Cache\Cache;
use Cake\Core\Configure;

/**
 * Live server status, read straight from the game servers via the
 * AssaultCube extinfo UDP protocol (see AcExtInfoService).
 *
 * Our own servers (Ladder.servers) are always shown; every other public
 * server known to the AC master server (AcMasterServerService) is polled
 * too and listed under "elsewhere" when somebody is playing there.
 */
class LiveController extends AppController
{
    public function index()
    {
        $servers = $this->liveServers();
        $this->set(compact('servers'));
    }

    /**
     * Ladder.servers minus the entries hidden from the live page.
     */
    private function liveServers(): array
    {
        return array_filter(
            Configure::read('Ladder.servers') ?: [],
            fn($cfg) => $cfg['live'] ?? true
        );
    }

    /**
     * JSON polled by the Live page. Cached for a few seconds (cache config
     * "live") so visitors do not hammer the game servers.
     */
    public function status()
    {
        $this->request->allowMethod(['get']);

        $data = Cache::remember('status', fn() => $this->fetchAll(), 'live');

        $this->viewBuilder()->setClassName('Json');
        $this->set('data', $data);
        $this->viewBuilder()->setOption('serialize', 'data');
    }

    /**
     * Live match page for one server: CLA vs RVSF, in-game style.
     */
    public function game(string $key)
    {
        $servers = Configure::read('Ladder.servers') ?: [];
        if (!isset($servers[$key])) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $server = $servers[$key] + ['key' => $key];
        $this->set(compact('server', 'key'));
    }

    /**
     * JSON for the live match page: one server only, cached a few seconds.
     */
    public function gameStatus(string $key)
    {
        $this->request->allowMethod(['get']);
        $cfg = (Configure::read('Ladder.servers') ?: [])[$key] ?? null;
        if ($cfg === null) {
            // "elsewhere" servers: any "ip:port" key from the public master
            // list may be viewed too - but nothing outside that list
            $cfg = (new AcMasterServerService())->servers()[$key] ?? null;
        }
        if ($cfg === null) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $cacheKey = 'game_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        $data = Cache::remember($cacheKey, function () use ($key, $cfg) {
            $info = (new AcExtInfoService())->query($cfg['host'], (int)$cfg['port']);
            $info['key'] = $key;
            $info['name'] = $cfg['name'] ?? (trim((string)($info['description'] ?? '')) ?: $key);
            $info['team_mode'] = in_array((int)$info['mode'], AcExtInfoService::TEAM_MODES, true);
            $info['by_flags'] = in_array((int)$info['mode'], AcExtInfoService::FLAG_MODES, true);
            $info['map_image'] = $info['map'] && is_file(WWW_ROOT . 'img/maps/' . $info['map'] . '.jpg')
                ? '/img/maps/' . $info['map'] . '.jpg'
                : '/img/maps/placeholder.jpg';
            $info['game_id'] = $this->currentGameId($info);
            $list = [$info];
            $this->linkProfiles($list);

            return ['fetched_at' => date('c'), 'server' => $list[0]];
        }, 'live');

        $this->viewBuilder()->setClassName('Json');
        $this->set('data', $data);
        $this->viewBuilder()->setOption('serialize', 'data');
    }

    /**
     * Id of this game on the ladder, when the log parser already created it
     * (latest game of the server, same map + mode).
     */
    private function currentGameId(array $info): ?string
    {
        $mode = AcExtInfoService::MODE_CODES[(int)$info['mode']] ?? null;
        if ($mode === null || empty($info['map'])) {
            return null;
        }
        $game = $this->fetchTable('Games')->find()
            ->select(['Games.id', 'Games.mode', 'Maps.name'])
            ->contain(['Maps'])
            ->where(['Games.server_name' => $info['key']])
            ->orderBy(['Games.started_at' => 'DESC'])
            ->enableHydration(false)
            ->first();
        if (!$game || $game['mode'] !== $mode || ($game['map']['name'] ?? null) !== $info['map']) {
            return null;
        }

        return (string)$game['id'];
    }

    private function fetchAll(): array
    {
        $all = Configure::read('Ladder.servers') ?: [];
        $own = $this->liveServers();
        // servers hidden from the main list ('live' => false) form their own
        // "clan match / inter" group on the live page
        $clan = array_filter($all, fn($cfg) => ($cfg['live'] ?? true) === false);

        // Master list entries are "ip:port"; resolve our own hosts so the
        // same server is not polled twice under two names.
        $targets = $own + $clan;
        $ownAddr = [];
        foreach ($all as $cfg) {
            $ownAddr[gethostbyname($cfg['host']) . ':' . (int)$cfg['port']] = true;
        }
        $master = (new AcMasterServerService())->servers();
        foreach ($master as $key => $cfg) {
            if (!isset($ownAddr[$key]) && !isset($targets[$key])) {
                $targets[$key] = $cfg;
            }
        }

        $ext = new AcExtInfoService();
        $servers = [];
        $clanServers = [];
        $elsewhere = [];
        $polled = $online = $players = 0;
        foreach ($ext->queryMany($targets) as $key => $info) {
            $info['key'] = $key;
            if (isset($own[$key])) {
                $info['own'] = true;
                $info['name'] = $own[$key]['name'] ?? $key;
                $servers[] = $info;
                continue;
            }
            if (isset($clan[$key])) {
                $info['own'] = false;
                $info['name'] = $clan[$key]['name'] ?? $key;
                $clanServers[] = $info;
                continue;
            }
            $polled++;
            if (!$info['online']) {
                continue;
            }
            $online++;
            $players += $info['numplayers'];
            if ($info['numplayers'] > 0) {
                $info['own'] = false;
                $info['name'] = trim((string)$info['description']) ?: $key;
                $elsewhere[] = $info;
            }
        }
        usort($elsewhere, fn($a, $b) => $b['numplayers'] <=> $a['numplayers']);

        $this->linkProfiles($servers);
        $this->linkProfiles($elsewhere);

        return [
            'fetched_at' => date('c'),
            'servers' => $servers,
            'clan' => ['servers' => $clanServers],
            'elsewhere' => [
                'servers' => $elsewhere,
                'polled' => $polled,
                'online' => $online,
                'players' => $players,
            ],
        ];
    }

    /**
     * Link live players to their ladder profile (by current name).
     */
    private function linkProfiles(array &$list): void
    {
        $names = [];
        foreach ($list as $srv) {
            foreach ($srv['players'] as $pl) {
                $names[$pl['name']] = true;
            }
        }
        $profiles = [];
        if ($names) {
            $rows = $this->fetchTable('Players')->find()
                ->select(['id', 'name', 'picture', 'country'])
                ->where(['name IN' => array_keys($names), 'track' => 1])
                ->enableHydration(false);
            foreach ($rows as $r) {
                $profiles[$r['name']] = $r;
            }
        }
        foreach ($list as &$srv) {
            foreach ($srv['players'] as &$pl) {
                $p = $profiles[$pl['name']] ?? null;
                $pl['player_id'] = $p['id'] ?? null;
                $pl['country'] = $p['country'] ?? null;
                $pl['picture'] = !empty($p['picture']) ? '/img/players/' . $p['picture'] : null;
            }
            unset($pl);
        }
        unset($srv);
    }
}
