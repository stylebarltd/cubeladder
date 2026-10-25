<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AimTrainer;
use App\Service\PlayerLink;
use App\View\Helper\LayoutHelper;
use Cake\Event\EventInterface;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Cookie\SameSiteEnum;

/**
 * Aim trainer: /play (the game and the boards), /play/start and
 * /play/finish (JSON, one round each; see AimTrainer).
 */
class PlayController extends AppController
{
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        // JSON posts from the game (CSRF token in the header still applies)
        $this->FormProtection->setConfig('unlockedActions', ['start', 'finish']);
    }

    public function index()
    {
        $trainer = new AimTrainer();
        $week = $trainer->board(AimTrainer::weekStart());
        $allTime = $trainer->board(null);
        // map screenshots as backgrounds, a different one per round
        $files = array_map('basename', glob(WWW_ROOT . 'img/maps/*.jpg') ?: []);
        shuffle($files);
        $maps = array_map(fn($f) => ['name' => basename($f, '.jpg'), 'url' => LayoutHelper::mapUrl($f)], array_slice($files, 0, 12));
        // not recognised: a code to say in a game (PlayerLink), kept in a cookie until it matches
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
        $this->set(compact('week', 'allTime', 'maps', 'linkCode'));
    }

    /**
     * GET /play/link: has the browser's code been said in a game yet? On a match
     * the browser gets the identity cookie (a year: this is proof, not an IP guess).
     */
    public function link()
    {
        $player = (new PlayerLink())->check($this->request->getCookie('link_code'));
        if (!$player) {
            return $this->json(['linked' => false]);
        }
        $this->writePlayerCookie($player['id'], '+365 days');
        $this->response = $this->response->withExpiredCookie(new Cookie('link_code'));

        return $this->json(['linked' => true, 'name' => $player['name']]);
    }

    public function start()
    {
        $this->request->allowMethod(['post']);
        $data = (new AimTrainer())->start($this->request->getAttribute('identity')?->id);

        return $this->json($data);
    }

    public function finish()
    {
        $this->request->allowMethod(['post']);
        $body = (array)$this->request->getData();
        $result = (new AimTrainer())->finish(
            (string)($body['token'] ?? ''),
            (string)($body['events'] ?? ''),
            is_array($body['reactions'] ?? null) ? $body['reactions'] : [],
        );
        if (isset($result['error'])) {
            return $this->json($result, 422);
        }
        $result['week'] = (new AimTrainer())->board(AimTrainer::weekStart());

        return $this->json($result);
    }

    private function json(array $data, int $status = 200)
    {
        return $this->response->withStatus($status)->withType('application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withStringBody((string)json_encode($data));
    }
}
