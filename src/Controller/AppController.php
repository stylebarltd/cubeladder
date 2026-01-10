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

        // Detect IP
        $ip = $this->request->clientIp();

        // TODO: remove after testing

        // Find matching player
        $player = $this->fetchTable('Players')
            ->find()
            ->where(['ip' => $ip])
            ->first();

        // Attach identity into request (NOT Authentication plugin)
        if ($player) {
            $this->request = $this->request->withAttribute('identity', $player);
        }

        // Make available in ALL templates
        $this->set('authPlayer', $player);
    }

    /**
     * Helper to get authenticated player object
     */
    public function authPlayer()
    {
        return $this->request->getAttribute('identity');
    }


    public function beforeRender(EventInterface $event): void
    {

        $this->set('maxGamesToRank', Configure::read('Ladder.maxGamesToRank'));

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
