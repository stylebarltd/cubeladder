<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Collection\Collection;

class GamesController extends AppController
{
    public function index()
    {
        // Fetch last 100 games with players
        $games = $this->Games->find('all')
            ->contain([
                'Maps',
                'Players' => function ($q) {
                    return $q->select([
                        'Players.id',
                        'Players.name',
                        'Players.country',
                        'PlayerStatsPerGame.kills',
                        'PlayerStatsPerGame.kd_ratio',
                        'PlayerStatsPerGame.total_score',
                        'PlayerStatsPerGame.gibbed',
                        'PlayerStatsPerGame.slashed',
                        'PlayerStatsPerGame.scored_with_the_flag',
                        'PlayerStatsPerGame.headshot',
                        'PlayerStatsPerGame.teamkills',
                    ])->order(['PlayerStatsPerGame.total_score' => 'DESC'])->where(['track'=>1]);
                }
            ])
            ->orderByDesc('ended_at')
            ->limit(Configure::read('Ladder.maxGamesToRank'))
            ->toArray();

        foreach ($games as $game) {
            $leaders = [
                'total_score' => null,
                'kills' => null,
                'kd_ratio' => null,
                'gibbed' => null,
                'slashed' => null,
                'scored_with_the_flag' => null,
                'headshot' => null,
                'teamkills' => null,
            ];

            $max = [
                'total_score' => 0,
                'kd_ratio' => 0,
                'kills' => 0,
                'gibbed' => 0,
                'slashed' => 0,
                'scored_with_the_flag' => 0,
                'headshot' => 0,
                'teamkills' => 0,
            ];

            foreach ($game->players as $player) {
                $stats = $player->PlayerStatsPerGame;

                foreach ($max as $key => $value) {
                    if (!empty($stats[$key]) && $stats[$key] > $max[$key]) {
                        $max[$key] = $stats[$key];
                        $leaders[$key] = $player->id;
                    }
                }
            }

            // Attach leaders to the game entity
            $game->stat_leaders = $leaders;
        }


        $lastGameDateRange = $this->getGameDateRange();

        $this->set(compact('games', 'lastGameDateRange'));
    }


    public function view(string $id)
    {
        $game = $this->Games->get($id, [
            'contain' => [
                'Maps',
                'PlayerStatsPerGame' => ['Players'],
            ],
        ]);

        $rankedStats = [];
        $rank = 1;
        $sorted = collection($game->player_stats_per_game)
            ->sortBy('total_score', SORT_DESC)
            ->toList();

        foreach ($sorted as $stat) {
            if($stat->player->track!=1) continue;
            $rankedStats[] = [
                'rank'        => $rank,
                'is_mvp'      => $rank === 1,
                'player'      => $stat->player,
                'kills'       => $stat->kills,
                'deaths'      => $stat->deaths,
                'kd_ratio'    => $stat->kd_ratio,
                'score'       => $stat->total_score,
                'headshots'   => $stat->headshot,
                'gibbed'   => $stat->gibbed,
                'slashed'   => $stat->slashed,
                'suicides'    => $stat->suicided+$stat->teamkills,
                'objectives'  =>
                    $stat->stole_the_flag +
                    $stat->returned_the_flag +
                    $stat->scored_with_the_flag,
            ];
            $rank++;
        }

        $this->set(compact('game', 'rankedStats'));
    }


}
