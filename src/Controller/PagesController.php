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





            $Events = $this->fetchTable('Events');

            $bestPlayersByScore = $this->bestPlayersByScore();
            // Hall of Fame record holder per category (shared, cached lists)
            $hofTitles = [
                'kills' => 'Most Kills', 'headshot' => 'Most Headshots', 'flags' => 'Most Flags scored',
                'streak' => 'Longest Streak', 'slashed' => 'Most Slashes', 'gibbed' => 'Most Gibbed',
                'helper' => 'Flag Helper',
            ];
            $hofRecords = [];
            foreach ((new \App\Service\HallOfFameService())->topLists() as $key => $list) {
                if (!empty($list[0])) {
                    $hofRecords[] = ['title' => $hofTitles[$key] ?? $key, 'player' => $list[0]];
                }
            }
            $achievementTable = $this->fetchTable('Achievements');

            //debug(date('Y-m-d', strtotime('last week sunday')));

            $achievementPlayers = $achievementTable->find()->contain(['Players'])->where(['week_end'=>date('Y-m-d', strtotime('last week sunday'))])->toArray();

//dd($achievements);
            $lastGameDateRange = $this->getGameDateRange();

            // All-time longest kill streak in a single game (banner on the
            // frontpage). Only new games track streaks, so this can be empty
            // for a while - the banner is hidden then. Cached in 'rankings',
            // which ProcessLogsCommand clears after every import.
            $recordStreak = !\Cake\Core\Configure::read('Ladder.streakBanner') ? null
                : \Cake\Cache\Cache::remember('record_streak', function () {
                $row = $this->fetchTable('PlayerStatsPerGame')->find()
                    ->select([
                        'streak' => 'PlayerStatsPerGame.longest_streak',
                        'Players.id', 'Players.name', 'Players.country', 'Players.picture',
                        'Games.started_at',
                    ])
                    ->contain(['Players', 'Games'])
                    ->where(['Players.track' => 1, 'PlayerStatsPerGame.longest_streak >' => 0])
                    ->orderBy(['PlayerStatsPerGame.longest_streak' => 'DESC', 'Games.started_at' => 'ASC'])
                    ->enableHydration(false)
                    ->first();

                return $row ?: null;
            }, 'rankings');

            // Hero numbers (cached with the rankings)
            $heroStats = \Cake\Cache\Cache::remember('hero_stats', fn() => [
                'games' => $this->fetchTable('Games')->find()->where(['inaccurate' => false, 'ended_at IS NOT' => null])->count(),
                'players' => $this->fetchTable('Players')->find()->where(['track' => 1])
                    ->innerJoinWith('PlayerStatsPerGame')->distinct(['Players.id'])->count(),
                'maps' => $this->fetchTable('Games')->find()->select(['map_id'])->distinct(['map_id'])
                    ->where(['inaccurate' => false])->count(),
            ], 'rankings');


            $this->set(compact('lastLogs', 'bestPlayersByScore', 'hofRecords', 'achievementPlayers', 'lastGameDateRange', 'recordStreak', 'heroStats'));
        }


        if ($page === 'about') {
            // "What's new" (config/changelog.php), newest first
            Configure::load('changelog');
            $this->set('changelog', Configure::read('Changelog', []));
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
            ->limit(10)
            ->all()
            ->toArray();

        return $topPlayers;

    }









}
