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

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;

/**
 * Static content controller
 *
 * This controller will render views from templates/Pages/
 *
 * @link https://book.cakephp.org/5/en/controllers/pages-controller.html
 */
class PagesController extends AppController
{
    /**
     * Displays a view
     *
     * @param string ...$path Path segments.
     * @return \Cake\Http\Response|null
     * @throws \Cake\Http\Exception\ForbiddenException When a directory traversal attempt.
     * @throws \Cake\View\Exception\MissingTemplateException When the view file could not
     *   be found and in debug mode.
     * @throws \Cake\Http\Exception\NotFoundException When the view file could not
     *   be found and not in debug mode.
     * @throws \Cake\View\Exception\MissingTemplateException In debug mode.
     */
    public function display(string ...$path): ?Response
    {
        if (!$path) {
            return $this->redirect('/');
        }
        if (in_array('..', $path, true) || in_array('.', $path, true)) {
            throw new ForbiddenException();
        }
        $page = $subpage = null;

        if (!empty($path[0])) {
            $page = $path[0];
        }
        if (!empty($path[1])) {
            $subpage = $path[1];
        }

        if ($page === 'home') {
            $LogOffsets = $this->fetchTable('LogOffsets');

            $lastLogs = $LogOffsets->find()
                ->orderByDesc('modified');


//            $gameTable = $this->fetchTable('Games');
//            $games = $gameTable->find()->where(['ended_at IS NULL']);
//            //dd($games->toArray());
//            foreach($games as $game) {
//                //$this->gamesDeleted++;
//                $gameTable->delete($game);
//            }



            $Events = $this->fetchTable('Events');

//            $lastKills = $Events->find()
//                ->where(['type IN' => ['kill', 'stole_the_flag', 'lost_the_flag', 'return_the_flag', 'teamkill']])       // Adjust if your column is different!
//                ->orderByDesc('event_time')
//                ->limit(100)
//                ->toArray();
//dd($lastKills);
            $bestPlayersByScore = $this->bestPlayersByScore();
            $bestPlayersByMap = $this->bestPlayersByMap();
            $achievementTable = $this->fetchTable('Achievements');

            //debug(date('Y-m-d', strtotime('last week sunday')));

            $achievementPlayers = $achievementTable->find()->contain(['Players'])->where(['week_end'=>date('Y-m-d', strtotime('last week sunday'))])->toArray();

//dd($achievements);
            $lastGameDateRange = $this->getGameDateRange();

            $this->set(compact('lastLogs', 'bestPlayersByScore', 'bestPlayersByMap', 'achievementPlayers', 'lastGameDateRange'));
        }


        $this->set(compact('page', 'subpage'));

        try {
            return $this->render(implode('/', $path));
        } catch (MissingTemplateException $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }
            throw new NotFoundException();
        }
    }

    public function bestPlayersByMap()
    {

        $theLast100GameIds = $this->getLastGameIds();

        // SAFETY: if no games, prevent empty IN()
        if (empty($theLast100GameIds)) {
            $this->set('topMaps', []);
            return parent::display(...$path);
        }

        // 2) Count how many times each map appears in those 100 games
        $mapCounts = $this->fetchTable('Games')
            ->find()
            ->select([
                'map_id',
                'times_played' => 'COUNT(Games.id)',
            ])
            ->where(['Games.id IN' => $theLast100GameIds])
            ->groupBy('map_id')
            ->orderByDesc('times_played')
            ->limit(3)
            ->enableHydration(false)
            ->all()
            ->toList();

        $topMaps = [];

        // 3) For each top map: get map details + best player
        foreach ($mapCounts as $row) {
            $mapId = $row['map_id'];

            // load the map
            $map = $this->fetchTable('Maps')->get($mapId);

            // BEST PLAYER ON THIS MAP FOR LAST 100 GAMES
            $bestPlayer = $this->fetchTable('Players')
                ->find()
                ->select([
                    'Players.id',
                    'Players.name',
                    'Players.country',
                    'Players.picture',
                    'total_score' => 'MAX(PlayerStatsPerGame.total_score)'
                ])
                ->innerJoinWith('PlayerStatsPerGame', function ($q) use ($theLast100GameIds) {
                    return $q->where([
                        'PlayerStatsPerGame.game_id IN' => $theLast100GameIds
                    ]);
                })
                ->innerJoinWith('PlayerStatsPerGame.Games', function ($q) use ($mapId) {
                    return $q->where([
                        'Games.map_id' => $mapId
                    ]);
                })
                ->groupBy('Players.id')
                ->where(['Players.name IS NOT' => "unarmed"])
                ->orderByDesc('total_score')
                ->first();


            $topMaps[] = [
                'map'            => $map,
                'times_played'   => $row['times_played'],
                'best_player'    => $bestPlayer,
            ];
        }

        return $topMaps;
    }



    public function bestPlayersByScore()
    {
        // Last 100 games
        //$theLast100GameIds = $this->getLastGameIds();

        $gameIds = $this->getThisYearGameIds();

        // Best 3 players from the last 100 games
        $topPlayers = $this->fetchTable('Players')
            ->find()
            ->select([
                'Players.id',
                'Players.name',
                'Players.country',
                'Players.picture',
                'total_score' => 'SUM(PlayerStatsPerGame.total_score)'
            ])
            ->innerJoinWith('PlayerStatsPerGame', function ($q) use ($gameIds) {
                return $q->where(['PlayerStatsPerGame.game_id IN' => $gameIds]);
            })
            ->groupBy(['Players.id'])
            ->orderByDesc('total_score')
            ->limit(6)
            ->all()
            ->toArray();

        return $topPlayers;

    }









}
