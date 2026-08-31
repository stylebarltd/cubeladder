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
        $servers = Configure::read('Ladder.servers') ?: [];
        $this->set(compact('servers'));
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

    private function fetchAll(): array
    {
        $own = Configure::read('Ladder.servers') ?: [];

        // Master list entries are "ip:port"; resolve our own hosts so the
        // same server is not polled twice under two names.
        $targets = $own;
        $ownAddr = [];
        foreach ($own as $cfg) {
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
