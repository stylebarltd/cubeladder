<?php
declare(strict_types=1);

namespace App\Controller;


class ApiController extends AppController
{

    public function top100(){

        $this->Players = $this->fetchTable('Players');


        $lastGameIds = $this->getLastGameIds();

        // players who participated in those games + stats from those games
        $players = $this->Players->find()
            ->select([
                'Players.id',
                'Players.name',
                'Players.country',
                'total_score' => 'SUM(PlayerStatsPerGame.total_score)',
            ])
            ->innerJoinWith('PlayerStatsPerGame', function ($q) use ($lastGameIds) {
                return $q->where([
                    'PlayerStatsPerGame.game_id IN' => $lastGameIds
                ]);
            })
            ->where(['track'=>1])
            ->group(['Players.id'])
            ->orderByDesc('total_score')
            ->contain([
                'PlayerStatsPerGame' => function ($q) use ($lastGameIds) {
                    return $q->where([
                        'PlayerStatsPerGame.game_id IN' => $lastGameIds
                    ]);
                }
            ])
            ->all();

        $rank = 1;
        $ladder=[];
        foreach($players as $player){
            $ladder[$rank]=[
                'rank' => $rank,
                'name' => $player->name,
                'country' => $player->country,
                'total_score' => $player->total_score,
            ];
            if($rank==100) break;
            $rank++;
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode( $ladder, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));


    }

}
