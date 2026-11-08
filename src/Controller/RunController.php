<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Aim3D;
use App\Service\FlagRun;
use App\Service\QrHunt;
use App\Service\PlayerLink;
use Cake\Event\EventInterface;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Cookie\SameSiteEnum;

/**
 * Flag run: /run (the game, full window, its own page without the site
 * layout), JSON: /run/start, /run/finish, /run/board?map=. See FlagRun for
 * the rules and the checks.
 */
class RunController extends AppController
{
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        // JSON posts from the game (CSRF token in the header still applies)
        $this->FormProtection->setConfig('unlockedActions', ['start', 'finish']);
    }

    public function index()
    {
        $this->viewBuilder()->disableAutoLayout();
        // per tab, the maps that have what it needs (flagrun.json, tools/flagrun/routes.mjs)
        $all = FlagRun::maps();
        $tabs = [];
        foreach (FlagRun::TABS as $tab => $names) {
            foreach ($names as $name) {
                if (FlagRun::onTab($tab, $name)) {
                    $tabs[$tab][$name] = $all[$name]['title'];
                }
            }
        }
        $player = $this->request->getAttribute('identity');
        $qrLevel = $player ? (new QrHunt())->level((string)$player->id) : 0;
        $qrUnlocks = QrHunt::UNLOCKS;
        // not recognised: a code to say in a game (PlayerLink, as on /play), kept in a cookie until it matches
        $linkCode = null;
        if (!$this->request->getAttribute('identity')) {
            $link = new PlayerLink();
            $linkCode = $this->request->getCookie('link_code');
            if (!$link->open($linkCode)) {
                $linkCode = $link->issue();
                $this->response = $this->response->withCookie((new Cookie('link_code', $linkCode))
                    ->withExpiry(new \DateTime('+' . PlayerLink::VALID_HOURS . ' hours'))->withPath('/')
                    ->withSecure($this->request->is('https'))->withHttpOnly(true)->withSameSite(SameSiteEnum::LAX));
            }
        }
        $this->set(compact('tabs', 'linkCode', 'qrLevel', 'qrUnlocks'));
    }

    public function start()
    {
        $this->request->allowMethod(['post']);
        $mode = (string)($this->request->getData('mode') ?: 'flag');
        $player = $this->request->getAttribute('identity')?->id;
        $result = $mode === Aim3D::MODE
            ? (new Aim3D())->start((string)$this->request->getData('map'), $player)
            : (new FlagRun())->start((string)$this->request->getData('map'), $mode, $player);

        return $this->json($result, isset($result['error']) ? 422 : 200);
    }

    public function finish()
    {
        $this->request->allowMethod(['post']);
        $body = (array)$this->request->getData();
        if (($body['mode'] ?? '') === Aim3D::MODE) {
            $aim = new Aim3D();
            $result = $aim->finish((string)($body['token'] ?? ''), (int)($body['end_ms'] ?? 0),
                is_array($body['shots'] ?? null) ? $body['shots'] : [], is_array($body['trace'] ?? null) ? $body['trace'] : []);
            if (isset($result['error'])) {
                return $this->json($result, 422);
            }
            $result['week'] = $aim->board($result['map'], FlagRun::weekStart());
            $result['allTime'] = $aim->board($result['map'], null);

            return $this->json($result);
        }
        $run = new FlagRun();
        $result = $run->finish((string)($body['token'] ?? ''), (int)($body['time_ms'] ?? 0),
            is_array($body['trace'] ?? null) ? $body['trace'] : []);
        if (isset($result['error'])) {
            return $this->json($result, 422);
        }
        $result['week'] = $run->board($result['map'], $result['mode'], FlagRun::weekStart());
        $result['allTime'] = $run->board($result['map'], $result['mode'], null);

        return $this->json($result);
    }

    /** GET /run/board?map=&mode=: this week's and the all-time board of a map and mode */
    public function board()
    {
        $map = (string)$this->request->getQuery('map');
        $mode = (string)($this->request->getQuery('mode') ?: 'flag');
        if ($mode === Aim3D::MODE && FlagRun::onTab(Aim3D::MODE, $map)) {
            $aim = new Aim3D();

            return $this->json(['map' => $map, 'mode' => $mode, 'week' => $aim->board($map, FlagRun::weekStart()), 'allTime' => $aim->board($map, null)]);
        }
        if (!FlagRun::has($map, $mode)) {
            return $this->json(['error' => 'Unknown map or mode.'], 404);
        }
        $run = new FlagRun();

        return $this->json(['map' => $map, 'mode' => $mode, 'week' => $run->board($map, $mode, FlagRun::weekStart()), 'allTime' => $run->board($map, $mode, null)]);
    }

    /**
     * GET /run/halls: what cl_cube's halls show (the viewer draws it on the map's display panels):
     * the Hall of Fame's top three of every list, and the visitor's own card when we know who they are.
     */
    public function halls()
    {
        $this->request->allowMethod(['get']);
        $pic = fn(?string $p) => $p ? '/img/players/' . rawurlencode($p) : null;
        $top = (new \App\Service\HallOfFameService())->topLists();
        $hall = [];
        foreach (\App\Service\HallOfFameService::CATEGORIES as $key => $c) {
            $rows = array_slice(\App\Service\HallOfFameService::rows($top[$key] ?? []), 0, 3);
            $hall[] = ['key' => $key, 'title' => $c['title'], 'unit' => $c['unit'],
                'top' => array_map(fn($r) => ['pos' => $r['pos'], 'name' => $r['name'], 'value' => $r['value'],
                    'map' => $r['map'], 'picture' => $pic($r['picture'] ?: null)], $rows)];
        }
        $me = null;
        $player = $this->request->getAttribute('identity');
        if ($player) {
            $id = (string)$player->id;
            $conn = $this->fetchTable('Players')->getConnection();
            $sum = $conn->execute('SELECT COUNT(*) games, COALESCE(SUM(kills),0) kills, COALESCE(SUM(deaths),0) deaths,
                    COALESCE(SUM(headshot),0) headshots, COALESCE(SUM(scored_with_the_flag),0) flags,
                    COALESCE(MAX(longest_streak),0) streak, COALESCE(SUM(minutes_played),0) minutes
                FROM player_stats_per_game WHERE player_id = ?', [$id])->fetch('assoc') ?: [];
            $fav = $conn->execute('SELECT m.name, COUNT(*) n FROM player_stats_per_game s JOIN games g ON g.id = s.game_id
                JOIN maps m ON m.id = g.map_id WHERE s.player_id = ? GROUP BY m.name ORDER BY n DESC LIMIT 1', [$id])->fetch('assoc');
            $since = $conn->execute('SELECT MIN(g.started_at) t FROM player_stats_per_game s JOIN games g ON g.id = s.game_id
                WHERE s.player_id = ?', [$id])->fetch('assoc');
            $rating = $this->fetchTable('PlayerRatings')->find()->where(['player_id' => $id])->disableHydration()->first();
            $records = [];
            foreach (\App\Service\HallOfFameService::CATEGORIES as $key => $c) {
                foreach (\App\Service\HallOfFameService::rows($top[$key] ?? []) as $r) {
                    if ($r['player_id'] === $id) {
                        $records[] = ['title' => $c['title'], 'pos' => $r['pos'], 'value' => $r['value'], 'unit' => $c['unit']];
                    }
                }
            }
            $qr = new QrHunt();
            $level = $qr->level($id);
            $me = ['name' => (string)$player->name, 'picture' => $pic($player->picture ?: null),
                'level' => $level, 'levelName' => QrHunt::levelName($level), 'codes' => $qr->total(),
                'games' => (int)($sum['games'] ?? 0), 'kills' => (int)($sum['kills'] ?? 0), 'deaths' => (int)($sum['deaths'] ?? 0),
                'headshots' => (int)($sum['headshots'] ?? 0), 'flags' => (int)($sum['flags'] ?? 0), 'streak' => (int)($sum['streak'] ?? 0),
                'hours' => round((int)($sum['minutes'] ?? 0) / 60), 'favouriteMap' => $fav['name'] ?? null,
                'since' => !empty($since['t']) ? date('M Y', strtotime((string)$since['t'])) : null,
                'rating' => $rating ? (int)round((float)$rating['rating']) : null, 'rank' => $rating ? (int)$rating['rank'] : null,
                'type' => $rating ? \App\Command\CalculateRatingsCommand::typeLabel((string)$rating['type'], (int)$rating['attack_pct'],
                    (int)$rating['defense_pct'], (int)$rating['combat_pct']) : null,
                'records' => $records];
        }

        return $this->json(['hall' => $hall, 'me' => $me]);
    }

    /**
     * GET /run/stars: cl_cube's star chamber (the viewer's stars.js draws a dome of them): one star for every
     * player who has played a game on our servers, grouped by country (constellations). Per star only its
     * country, games and rating, in a fixed order that tells nothing about the player; names only for the ten
     * highest ranked (public on /players anyway) and, for the visitor, their own star.
     */
    public function stars()
    {
        $this->request->allowMethod(['get']);
        $sky = \Cake\Cache\Cache::remember('run_stars_v1', function () {
            $conn = $this->fetchTable('Players')->getConnection();
            $rows = $conn->execute('SELECT p.id, p.name, COALESCE(p.country, \'\') country, COUNT(*) games,
                    r.rating, r.`rank`
                FROM players p JOIN player_stats_per_game s ON s.player_id = p.id
                LEFT JOIN player_ratings r ON r.player_id = p.id
                GROUP BY p.id, p.name, p.country, r.rating, r.`rank`')->fetchAll('assoc');
            usort($rows, fn($a, $b) => crc32((string)$a['id']) <=> crc32((string)$b['id']));
            $count = [];
            foreach ($rows as $r) {
                $c = strtoupper((string)$r['country']);
                $count[$c] = ($count[$c] ?? 0) + 1;
            }
            arsort($count);
            $countries = [];
            $index = [];
            foreach ($count as $c => $n) {
                $index[$c] = count($countries);
                $countries[] = ['code' => (string)$c, 'name' => $c === '' ? '' : \App\Utility\Continents::countryName((string)$c), 'n' => $n];
            }
            $stars = [];
            $ids = [];
            $named = [];
            foreach ($rows as $i => $r) {
                $stars[] = [$index[strtoupper((string)$r['country'])], (int)$r['games'], $r['rating'] !== null ? (int)round((float)$r['rating'] * 10) : 0];
                $ids[(string)$r['id']] = $i;
                if ($r['rank'] !== null && (int)$r['rank'] <= 10) {
                    $named[] = ['i' => $i, 'name' => (string)$r['name'], 'rating' => (float)$r['rating'], 'rank' => (int)$r['rank']];
                }
            }
            usort($named, fn($a, $b) => $a['rank'] <=> $b['rank']);

            return ['countries' => $countries, 'stars' => $stars, 'named' => $named, 'ids' => $ids];
        }, 'rankings');                                   // cleared when new games come in
        $me = null;
        $player = $this->request->getAttribute('identity');
        if ($player && isset($sky['ids'][(string)$player->id])) {
            $i = $sky['ids'][(string)$player->id];
            $c = $sky['countries'][$sky['stars'][$i][0]];
            $me = ['i' => $i, 'name' => (string)$player->name, 'country' => $c['name'], 'countryStars' => $c['n'],
                'games' => $sky['stars'][$i][1], 'rating' => $sky['stars'][$i][2] / 10];
        }
        unset($sky['ids']);

        return $this->json($sky + ['me' => $me]);
    }

    private function json(array $data, int $status = 200)
    {
        return $this->response->withStatus($status)->withType('application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withStringBody((string)json_encode($data));
    }
}
