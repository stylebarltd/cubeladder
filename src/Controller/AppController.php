<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use App\Service\LastGamesTrait;
use App\Model\Entity\Player;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Cookie\SameSiteEnum;
use Cake\Utility\Security;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{

    use LastGamesTrait;

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();



        $this->loadComponent('Flash');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        $this->loadComponent('FormProtection');
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        $player = $this->resolvePlayer();

        if ($player) {
            $this->request = $this->request->withAttribute('identity', $player);
        }

        $this->set('authPlayer', $player);
    }


    protected function resolvePlayer(): ?Player
    {
        $Players = $this->fetchTable('Players');

        // 1️⃣ Try cookie FIRST
        $player = $this->playerFromCookie($Players);

        if ($player) {
            return $player;
        }

        // 2️⃣ Then try IP detection
        $player = $this->playerFromIp($Players);

        if ($player) {
            return $player;
        }

        return null;
    }




    protected function playerFromCookie($Players): ?Player
    {
        $playerId = $this->request->getCookie('selected_player_id');

        if (!$playerId) {
            return null;
        }

        $player = $Players->find()
            ->where(['id' => $playerId])
            ->first();

        if (!$player) {
            return $this->expirePlayerCookie();
        }

        return $player;
    }



    protected function playerFromIp($Players): ?Player
    {
        $ip = $this->request->clientIp();

        $players = $Players->find()
            ->where(['ip' => $ip])
            ->toArray();

        if (count($players) === 1) {
            $player = $players[0];
            $this->writePlayerCookie($player->id);
            return $player;
        }

        if (count($players) > 1) {
            $this->set('multiplePlayers', $players);
        }

        return null;
    }


    /**
     * The visitor may edit this player's profile: it is their identity
     * (cookie / IP detection) AND they are on the IP the player last
     * played from. A cookie alone is not enough.
     */
    protected function ownsPlayer(?Player $player): bool
    {
        $identity = $this->request->getAttribute('identity');

        if ($player === null || $identity === null || $identity->id !== $player->id) {
            return false;
        }
        // Local dev (debug): requests come from the Docker network and can
        // never match a real player IP - the identity alone is enough there.
        if (Configure::read('debug')) {
            return true;
        }

        return !empty($player->ip) && $player->ip === $this->request->clientIp();
    }

    /**
     * The visitor is a site admin (Ladder.admins) and owns that identity
     * (same IP as the admin's player - see ownsPlayer()).
     */
    protected function isAdmin(): bool
    {
        $identity = $this->request->getAttribute('identity');

        return $identity !== null
            && in_array($identity->id, (array)Configure::read('Ladder.admins'), true)
            && $this->ownsPlayer($identity);
    }

    public function writePlayerCookie(string $playerId): void
    {
        $cookie = (new Cookie('selected_player_id', $playerId))
            ->withExpiry(new \DateTime('+30 days'))
            ->withPath('/')
            ->withSecure($this->request->is('https'))
            ->withHttpOnly(true)
            ->withSameSite(SameSiteEnum::LAX);

        $this->response = $this->response->withCookie($cookie);
    }

    protected function expirePlayerCookie(): ?Player
    {
        $this->response = $this->response->withExpiredCookie(
            new Cookie('selected_player_id')
        );

        return null;
    }


    public function beforeRender(EventInterface $event): void
    {

        $this->set('maxGamesToRank', Configure::read('Ladder.maxGamesToRank'));
        // initial state of the "on air" dot (state files only, no polling;
        // the layout refreshes it from /live/onair)
        $this->set('liveNow', (new \App\Service\LiveStatusService())->onAir(false));

        $player = $this->viewBuilder()->getVar('authPlayer');

        if ($player) {
            $unread = $this->fetchTable('Messages')
                ->find()
                ->where([
                    'receiver_id' => $player->id,
                    'is_read' => 0
                ])
                ->count();

            $this->set('inboxUnread', $unread);
        } else {
            // Visitors see no badge
            $this->set('inboxUnread', 0);
        }
    }

}
