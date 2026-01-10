<?php
declare(strict_types=1);

namespace App\Controller;


class PlayersController extends AppController
{

    public function index()
    {

        $sort = $this->request->getQuery('sort', 'points');


        switch ($sort) {
            case 'points':
                $order = ['total_score' => 'DESC'];
                break;

            case 'headshot':
                $order = ['headshot' => 'DESC'];
                break;

            case 'slashed':
                $order = ['slashed' => 'DESC'];
                break;

            case 'gibbed':
                $order = ['gibbed' => 'DESC'];
                break;

            case 'teamkills':
                $order = ['teamkills' => 'DESC'];
                break;

            case 'kd':
                $order = ['kd_ratio' => 'DESC'];
                break;


            case 'scored_with_the_flag':
                $order = ['scored_with_the_flag' => 'DESC'];
                break;

            default:
                $order = ['total_score' => 'DESC'];
        }

        $lastGameIds = $this->getLastGameIds();

        // players who participated in those games + stats from those games
        $players = $this->Players->find()
            ->select([
                'Players.id',
                'Players.name',
                'Players.country',
                'Players.picture',
                'total_score' => 'SUM(PlayerStatsPerGame.total_score)',
                'avg_score' => 'ROUND(AVG(PlayerStatsPerGame.total_score))',
                'headshot' => 'SUM(PlayerStatsPerGame.headshot)',
                'slashed' => 'SUM(PlayerStatsPerGame.slashed)',
                'gibbed' => 'SUM(PlayerStatsPerGame.gibbed)',
                'teamkills' => 'SUM(PlayerStatsPerGame.teamkills)',
                'gibbed' => 'SUM(PlayerStatsPerGame.gibbed)',
                'scored_with_the_flag' => 'SUM(PlayerStatsPerGame.scored_with_the_flag)',
                'games' => 'COUNT(PlayerStatsPerGame.id)',
            ])
            ->innerJoinWith('PlayerStatsPerGame', function ($q) use ($lastGameIds) {
                return $q->where([
                    'PlayerStatsPerGame.game_id IN' => $lastGameIds
                ]);
            })
            ->where(['track'=>1])
            ->group(['Players.id'])
            ->order($order)
            ->contain([
                'PlayerStatsPerGame' => function ($q) use ($lastGameIds) {
                    return $q->where([
                        'PlayerStatsPerGame.game_id IN' => $lastGameIds
                    ]);
                }
            ])
            ->all();

        $players->map(function ($player) {

                $stats = [
                    'kills' => 0,
                    'deaths' => 0,
                    'kd_ratio' => 0,
                    'tk_ratio' => 0,
                    'headshot' => 0,
                    'teamkills' => 0,
                    'gibbed' => 0,
                    'slashed' => 0,
                    'peppered' => 0, //shotgun
                    'shredded' => 0, //rifle
                    'sprayed' => 0, //smg
                    'punctured' => 0, //sniper
                    'splattered' => 0, //shotgun
                    'picked_off' => 0, //carabine
                    'suicided' => 0,
                    'stole_the_flag' => 0,
                    'scored_with_the_flag' => 0
                ];

                foreach ($player->player_stats_per_game as $ps) {
                    $stats['teamkills'] += $ps->teamkills;
                    $stats['kills'] += $ps->kills;
                    $stats['deaths'] += $ps->deaths;
                    $stats['headshot'] += $ps->headshot;
                    $stats['gibbed'] += $ps->gibbed;
                    $stats['slashed'] += $ps->slashed;
                    $stats['shredded'] += $ps->shredded;
                    $stats['peppered'] += $ps->peppered;
                    $stats['sprayed'] += $ps->sprayed;
                    $stats['punctured'] += $ps->punctured;
                    $stats['splattered'] += $ps->splattered;
                    $stats['picked_off'] += $ps->picked_off;
                    $stats['suicided'] += $ps->suicided;
                    $stats['stole_the_flag'] += $ps->stole_the_flag;
                    $stats['scored_with_the_flag'] += $ps->scored_with_the_flag;
                }

            $stats['kd_ratio'] = $stats['deaths'] > 0
                ? round($stats['kills'] / $stats['deaths'], 2)
                : $stats['kills'];


                $player->stats = $stats;

                return $player;
            })
            ->toArray();


        if ($sort === 'kd') {
            $players = $players->sortBy(
                fn($p) => $p->stats['kd_ratio'],
                SORT_DESC,
                SORT_NUMERIC
            );
        }

        $lastGameDateRange = $this->getGameDateRange();

        $achievePlayers = $this->Players->Achievements->find()
            ->contain(['Players'])
            ->where([
                'week_end'=>date('Y-m-d', strtotime('last week sunday')),
                'event_type IS NOT' => 'best_on_map'
                ]);
        $achievementPlayers=[];
        foreach($achievePlayers as $player){
            $achievementPlayers[$player->player_id][$player->event_type]=$player->count;
        }

        $this->set(compact('players', 'sort', 'lastGameDateRange', 'achievementPlayers'));
    }
    public function view(string $id)
    {

        $lastGameIds = $this->getLastGameIds();

        $player = $this->Players->get($id, [
            'contain' => [
                'Achievements',
                'PlayerStatsPerGame' => function ($q) use ($lastGameIds) {
                    return $q
                        ->where([
                            'PlayerStatsPerGame.game_id IN' => $lastGameIds
                        ])
                        ->contain([
                            'Games' => ['Maps']
                        ])
                        ->orderDesc('Games.ended_at');
                }
            ]
        ]);
        $authPlayer=$this->authPlayer();
        if ($authPlayer && $player->id!=$authPlayer->id){
            $player->views++;
            $this->Players->save($player);
        }

        // -----------------------------
        // Aggregate basic totals
        // -----------------------------
        $totalKills  = 0;
        $totalDeaths = 0;
        $totalScore  = 0;
        $gamesData   = [];

        // -----------------------------
        // Aggregate ALL stat fields
        // -----------------------------
        $excludedStatFields = [
            'id',
            'game_id',
            'player_id',
            'kd_ratio',
            'total_score',
            'game',
        ];

        $statSums = [];

        if (!empty($player->player_stats_per_game)) {
            // Detect stat columns dynamically
            $statFields = array_diff(
                array_keys($player->player_stats_per_game[0]->toArray()),
                $excludedStatFields
            );

            // Init sums
            foreach ($statFields as $field) {
                $statSums[$field] = 0;
            }

            foreach ($player->player_stats_per_game as $stat) {
                // Basic totals
                $totalKills  += $stat->kills;
                $totalDeaths += $stat->deaths;
                $totalScore  += $stat->total_score;

                // Dynamic stat sums
                foreach ($statFields as $field) {
                    $statSums[$field] += (int)($stat->{$field} ?? 0);
                }

                // Per-game data for UI
                $gamesData[] = [
                    'game_id' => $stat->game->id,
                    'map_name' => $stat->game->map->name,
                    'played_at' => $stat->game->started_at->format('D, dS M'),
                    'map_image' => file_exists(
                        WWW_ROOT . 'img/maps/' . $stat->game->map->name . '.jpg'
                    )
                        ? '/img/maps/' . $stat->game->map->name . '.jpg'
                        : '/img/maps/placeholder.jpg',
                    'score' => $stat->total_score,
                    'kills' => $stat->kills,
                    'deaths' => $stat->deaths,
                    'kd_ratio' => $stat->kd_ratio,
                ];
            }
        }

        // Derived stat
        $kdRatio = $totalDeaths ? $totalKills / $totalDeaths : $totalKills;

        // Optional: sort stats by highest value
        arsort($statSums);

        $this->set(compact(
            'player',
            'totalKills',
            'totalDeaths',
            'totalScore',
            'kdRatio',
            'gamesData',
            'statSums',
        ));
    }



    public function search()
    {
        $this->request->allowMethod(['get']);

        $query = $this->request->getQuery('q');
        $this->autoRender = false;

        if (!$query || strlen($query) < 2) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([]));
        }

        $players = $this->Players->find()
            ->select(['id', 'name', 'country'])
            ->where(['name LIKE' => '%' . $query . '%', 'track'=>1])
            ->orderAsc('name')
            ->limit(10)
            ->all()
            ->toList();


        return $this->response->withType('application/json')
            ->withStringBody(json_encode($players));
    }


    public function mapData()
    {
        $this->request->allowMethod(['get']);

        $players = $this->Players->find()
            ->select(['name', 'latitude', 'longitude'])
            ->where([
                'latitude IS NOT' => null,
                'longitude IS NOT' => null
            ])
            ->enableHydration(false)
            ->toArray();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($players));
    }



}
