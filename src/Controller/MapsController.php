<?php


declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;
use Cake\Core\Configure;

class MapsController extends AppController
{
    public function index()
    {
//        $maps = $this->Maps->find()
//            ->contain(['Games'])
//            ->orderDesc('Maps.times_played')
//            ->all();

        $maps = $this->Maps->find()
            ->matching('Games.PlayerStatsPerGame')
            ->distinct(['Maps.id'])
            ->orderByDesc('Maps.times_played')
            ->all();


        $PlayerStatsPerGame = $this->fetchTable('PlayerStatsPerGame');
        $achievementTable = $this->fetchTable('Achievements');

        foreach ($maps as $map) {

            $topPlayers = $PlayerStatsPerGame->find()
                ->select([
                    'player_id',
                    'score' => $PlayerStatsPerGame->find()->func()->sum('total_score'),
                    'Players.id',
                    'Players.name',
                    'Players.country',
                ])
                ->innerJoinWith('Games', function ($q) use ($map) {
                    return $q->where([
                        'Games.map_id' => $map->id
                    ]);
                })
                ->contain(['Players'])
                ->group(['player_id'])
                ->orderDesc('score')
                ->limit(5)
                ->all();

            $map->top_players = $topPlayers;
        }


        $achievementMaps = $achievementTable->find()->where(['event_type' => 'best_on_map', 'week_end'=>date('Y-m-d', strtotime('last week sunday'))])->contain(['Players', 'Maps'])->toArray();


        $lastGameDateRange = $this->getGameDateRange();

        $this->set(compact('maps', 'lastGameDateRange', 'achievementMaps'));
    }

}


//declare(strict_types=1);
//
//namespace App\Controller;
//
//use Cake\Http\Exception\NotFoundException;
//
//class MapsController extends AppController
//{
//    public function index()
//    {
//
//        $lastGameIds = $this->getLastGameIds();
//
//// Step 2: Get maps with last 100 games only
//        $maps = $this->Maps->find()
//            ->select([
//                'Maps.id',
//                'Maps.name',
//                // count of times played in last 100 games
//                'times_played' => $this->Maps->Games->find()
//                    ->select(['count' => 'COUNT(Games.id)'])
//                    ->where([
//                        'Games.map_id = Maps.id',
//                        'Games.id IN' => $lastGameIds
//                    ])
//            ])
//            ->contain(['Games' => function ($q) use ($lastGameIds) {
//                return $q
//                    ->where(['Games.id IN' => $lastGameIds])
//                    ->contain(['Players' => function ($q2) {
//                        return $q2
//                            ->select(['Players.id', 'Players.name', 'Players.country'])
//                            ->contain(['PlayerStatsPerGame']);
//                    }])
//                    ->order(['Games.id' => 'DESC']);
//            }])
//            ->order(['times_played' => 'DESC'])
//            ->toArray();
//
//
//        // Compute top 10 players per map
//        foreach ($maps as $map) {
//            $playerScores = [];
//
//            foreach ($map->games as $game) {
//                foreach ($game->players as $player) {
//                    $score = $player->_joinData->total_score ?? 0;
//
//                    // Aggregate best score per player
//                    if (!isset($playerScores[$player->id]) || $score > $playerScores[$player->id]['score']) {
//                        $playerScores[$player->id] = [
//                            'name' => $player->name,
//                            'id' => $player->id,
//                            'country' => $player->country,
//                            'score' => $score
//                        ];
//                    }
//                }
//            }
//
//            // Sort players by score descending
//            usort($playerScores, function ($a, $b) {
//                return $b['score'] <=> $a['score'];
//            });
//
//            // Keep top 10
//            $map->top_players = array_slice($playerScores, 0, 10);
//        }
//
//        $lastGameDateRange = $this->getGameDateRange();
//
//        $achievementTable = $this->fetchTable('Achievements');
//        $achievementMaps = $achievementTable->find()->where(['event_type' => 'best_on_map', 'week_end'=>date('Y-m-d', strtotime('last week sunday'))])->contain(['Players', 'Maps'])->toArray();
//
//
//        $this->set(compact('maps', 'lastGameDateRange', 'achievementMaps'));
//    }
//
//
//
//
//    public function view($id = null)
//    {
//        if (!$id) {
//            throw new NotFoundException(__('Map not found'));
//        }
//
//        $map = $this->Maps->get($id, [
//            'contain' => ['Games', 'Players']
//        ]);
//
//        $this->set(compact('map'));
//    }
//}
