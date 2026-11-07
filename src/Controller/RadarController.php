<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\PlayerLink;
use App\Service\Radar;
use Cake\Event\EventInterface;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Cookie\SameSiteEnum;

/**
 * Radar, the map quiz: /radar (the game and the boards), JSON for one round:
 * /radar/start, /radar/question, /radar/answer, /radar/finish, and the
 * pieces as images: /radar/piece/<token>/<question>/<step | full>.
 * See the Radar service for the rules and fair play.
 */
class RadarController extends AppController
{
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        // JSON posts from the game (CSRF token in the header still applies)
        $this->FormProtection->setConfig('unlockedActions', ['start', 'question', 'answer', 'finish']);
    }

    public function index()
    {
        $radar = new Radar();
        $week = $radar->board(Radar::weekStart());
        $allTime = $radar->board(null);
        $pool = $radar->pool();
        $ready = $radar->ready();
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
        $this->set(compact('week', 'allTime', 'pool', 'ready', 'linkCode'));
    }

    public function start()
    {
        $this->request->allowMethod(['post']);
        $result = (new Radar())->start($this->request->getAttribute('identity')?->id);

        return $this->json($result, isset($result['error']) ? 503 : 200);
    }

    public function question()
    {
        $this->request->allowMethod(['post']);
        $body = (array)$this->request->getData();
        $result = (new Radar())->question((string)($body['token'] ?? ''), (int)($body['q'] ?? -1));

        return $this->json($result, isset($result['error']) ? 422 : 200);
    }

    /** GET /radar/piece/<token>/<q>/<step>: the piece as PNG, 404 when it may not be seen (yet) */
    public function piece(string $token = '', string $q = '', string $step = '')
    {
        $this->request->allowMethod(['get']);
        $png = ctype_digit($q) && ($step === 'full' || ctype_digit($step))
            ? (new Radar())->piece($token, (int)$q, $step) : null;
        if ($png === null) {
            return $this->response->withStatus(404)->withHeader('Cache-Control', 'no-store');
        }

        return $this->response->withType('png')->withHeader('Cache-Control', 'private, no-store')->withStringBody($png);
    }

    public function answer()
    {
        $this->request->allowMethod(['post']);
        $body = (array)$this->request->getData();
        $choice = $body['choice'] ?? null;
        $result = (new Radar())->answer((string)($body['token'] ?? ''), (int)($body['q'] ?? -1),
            is_string($choice) && $choice !== '' ? $choice : null);

        return $this->json($result, isset($result['error']) ? 422 : 200);
    }

    public function finish()
    {
        $this->request->allowMethod(['post']);
        $body = (array)$this->request->getData();
        $radar = new Radar();
        $result = $radar->finish((string)($body['token'] ?? ''));
        if (isset($result['error'])) {
            return $this->json($result, 422);
        }
        $result['week'] = $radar->board(Radar::weekStart());
        $result['allTime'] = $radar->board(null);

        return $this->json($result);
    }

    private function json(array $data, int $status = 200)
    {
        return $this->response->withStatus($status)->withType('application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withStringBody((string)json_encode($data));
    }
}
