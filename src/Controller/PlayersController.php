<?php
declare(strict_types=1);

namespace App\Controller;


use Cake\Core\Configure;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Exception\BadRequestException;
use Cake\Log\Log;
use Cake\Utility\Security;

class PlayersController extends AppController
{
    private function getAllRanks(array $gameIds): array
    {
        $players = $this->Players->find()
            ->select([
                'Players.id',
                'total_score' => 'SUM(PlayerStatsPerGame.total_score)'
            ])
            ->innerJoinWith('PlayerStatsPerGame', function ($q) use ($gameIds) {
                return $q->where([
                    'PlayerStatsPerGame.game_id IN' => $gameIds
                ]);
            })
            ->group(['Players.id'])
            ->order(['total_score' => 'DESC'])
            ->all()
            ->where(['Players.track'=>1])
            ->toArray();

        $rank = 1;
        $rankMap = [];

        foreach ($players as $player) {
            $rankMap[$player->id] = $rank++;
        }

        return $rankMap;
    }
    public function hallOfFame()
    {
        $topHeadshots = $this->getTopPlayers('headshot');
        $topSlashes   = $this->getTopPlayers('slashed');
        $topGibbed    = $this->getTopPlayers('gibbed');
        $topKills     = $this->getTopPlayers('kills');
        $topFlags     = $this->getTopPlayers('scored_with_the_flag');

//        $topSteals    = $this->getTopPlayers('stole_the_flag');
//        $topReturned    = $this->getTopPlayers('returned_the_flag');

        $bestFlagHelpers = $this->getTopPlayers(['stole_the_flag', 'returned_the_flag']);


        $this->set(compact(
            'topHeadshots',
            'topSlashes',
            'topGibbed',
            'topKills',
            'topFlags',
            'bestFlagHelpers'
        ));
    }
    private function getTopPlayers(string|array $fields, int $limit = 10)
    {
        $gameIds = $this->getHallOfFameGameIds();

        if (is_array($fields)) {
            $fieldExpr = implode(' + ', array_map(fn($f) => "PlayerStatsPerGame.$f", $fields));
        } else {
            $fieldExpr = "PlayerStatsPerGame.$fields";
        }

        $sub = $this->Players->PlayerStatsPerGame->find()
            ->select([
                'player_id' => 'PlayerStatsPerGame.player_id',
                'max_value' => "MAX($fieldExpr)"
            ])
            ->where([
                'PlayerStatsPerGame.game_id IN' => $gameIds
            ])
            ->group(['PlayerStatsPerGame.player_id']);

        $query = $this->Players->PlayerStatsPerGame->find()
            ->select([
                'player_id' => 'Players.id',
                'name' => 'Players.name',
                'country' => 'Players.country',
                'picture' => 'Players.picture',
                'value' => $fieldExpr,
                'game_id' => 'Games.id',
                'played_at' => 'Games.started_at',
                'map_name' => 'Maps.name'
            ])
            ->innerJoin(
                ['sub' => $sub],
                [
                    'sub.player_id = PlayerStatsPerGame.player_id',
                    "sub.max_value = $fieldExpr"
                ]
            )
            ->innerJoinWith('Players')
            ->innerJoinWith('Games.Maps')
            ->where([
                'PlayerStatsPerGame.game_id IN' => $gameIds,
                'Players.track' => 1,
            ])
            ->order([
                'value' => 'DESC',
                'Games.started_at' => 'DESC'
            ])
            ->group(['Players.id'])
            ->limit($limit);

        return $query->all()->toArray();
    }


    public function index()
    {

        $sort = $this->request->getQuery('sort', 'points');

        switch ($sort) {
            case 'points':
                $order = ['total_score' => 'DESC'];
                break;
            case 'kills':
                $order = ['kills' => 'DESC'];
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

        $gameIds = $this->getThisYearGameIds();

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
                'kills' => 'SUM(PlayerStatsPerGame.kills)',
                'slashed' => 'SUM(PlayerStatsPerGame.slashed)',
                'gibbed' => 'SUM(PlayerStatsPerGame.gibbed)',
                'teamkills' => 'SUM(PlayerStatsPerGame.teamkills)',
                'scored_with_the_flag' => 'SUM(PlayerStatsPerGame.scored_with_the_flag)',
                'games' => 'COUNT(PlayerStatsPerGame.id)',
            ])
            ->innerJoinWith('PlayerStatsPerGame', function ($q) use ($gameIds) {
                return $q->where([
                    'PlayerStatsPerGame.game_id IN' => $gameIds
                ]);
            })
            ->where(['track'=>1])
            ->group(['Players.id'])
            ->having([
                'SUM(PlayerStatsPerGame.total_score) >=' => 1500
            ])
            ->order($order)
            ->contain([
                'PlayerStatsPerGame' => function ($q) use ($gameIds) {
                    return $q->where([
                        'PlayerStatsPerGame.game_id IN' => $gameIds
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

        //$lastGameDateRange = $this->getGameDateRange(800);

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

        $this->set(compact('players', 'sort',  'achievementPlayers'));
    }
    private function getPlayerRank(string $playerId, array $gameIds): int
    {
        $ranking = $this->getPlayerRanking($gameIds);

        $rank = 1;

        foreach ($ranking as $player) {
            if ($player->id === $playerId) {
                return $rank;
            }
            $rank++;
        }

        return 0; // not found
    }


    private function getPlayerRanking(array $gameIds)
    {
        $query = $this->Players->find();

        $query
            ->select([
                'Players.id',
                'Players.name',
                'total_score' => $query->func()->sum('PlayerStatsPerGame.total_score')
            ])
            ->innerJoinWith('PlayerStatsPerGame', function ($q) use ($gameIds) {
                return $q->where([
                    'PlayerStatsPerGame.game_id IN' => $gameIds
                ]);
            })
            ->groupBy(['Players.id'])
            ->orderDesc('total_score');

        return $query->all();
    }
    public function select()
    {
        $this->request->allowMethod(['post']);

        $playerId = (string)$this->request->getData('player_id');

        if (!$playerId) {
            throw new BadRequestException('Missing player_id');
        }

        // Optional: verify player actually exists
        $Players = $this->fetchTable('Players');

        $player = $Players->find()
            ->where(['id' => $playerId])
            ->first();

        if (!$player) {
            throw new BadRequestException('Invalid player');
        }

        // Write encrypted cookie (middleware will encrypt it)
        $this->writePlayerCookie($player->id);

        return $this->redirect($this->referer());
    }


    public function thelast100()
    {

        $sort = $this->request->getQuery('sort', 'points');

        switch ($sort) {
            case 'points':
                $order = ['total_score' => 'DESC'];
                break;

            case 'headshot':
                $order = ['headshot' => 'DESC'];
                break;

            case 'kills':
                $order = ['kills' => 'DESC'];
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

        $theLast100GameIds = $this->getLastGameIds();

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
                'kills' => 'SUM(PlayerStatsPerGame.kills)',
                'slashed' => 'SUM(PlayerStatsPerGame.slashed)',
                'gibbed' => 'SUM(PlayerStatsPerGame.gibbed)',
                'teamkills' => 'SUM(PlayerStatsPerGame.teamkills)',
                'gibbed' => 'SUM(PlayerStatsPerGame.gibbed)',
                'scored_with_the_flag' => 'SUM(PlayerStatsPerGame.scored_with_the_flag)',
                'games' => 'COUNT(PlayerStatsPerGame.id)',
            ])
            ->innerJoinWith('PlayerStatsPerGame', function ($q) use ($theLast100GameIds) {
                return $q->where([
                    'PlayerStatsPerGame.game_id IN' => $theLast100GameIds
                ]);
            })
            ->where(['track'=>1])
            ->group(['Players.id'])
            ->order($order)
            ->contain([
                'PlayerStatsPerGame' => function ($q) use ($theLast100GameIds) {
                    return $q->where([
                        'PlayerStatsPerGame.game_id IN' => $theLast100GameIds
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

        $theLast100GameIds = $this->getLastGameIds();

        $player = $this->Players->get($id, [
            'contain' => [
                'Achievements',
                'PlayerStatsPerGame' => function ($q) use ($theLast100GameIds) {
                    return $q
//                        ->where([
//                            'PlayerStatsPerGame.game_id IN' => $theLast100GameIds
//                        ])
                        ->contain([
                            'Games' => ['Maps']
                        ])
                        ->limit(100)
                        ->where(['Games.ended_at IS NOT NULL'])
                        ->orderDesc('Games.started_at');
                },
            ]
        ]);
        if($player->track==0){
            $this->Flash->error('No profile found!');
            return $this->redirect(['action' => 'index']);
        }
        $authPlayer = $this->request->getAttribute('identity');
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
        $gamesDataGlobal=[];

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
            $gamesData = [];              // all games
$gamesDataGlobal = [];        // games inside lastGameIds
            $lastGameLookup = array_flip($theLast100GameIds);

            //debug($player->player_stats_per_game);
            foreach ($player->player_stats_per_game as $stat) {


                $gameData = [
                    'game_id'   => $stat->game->id,
                    'map_name'  => $stat->game->map->name,
                    'played_at' => $stat->game->started_at->format('D, dS M'),
                    'map_image' => file_exists(
                        WWW_ROOT . 'img/maps/' . $stat->game->map->name . '.jpg'
                    )
                        ? '/img/maps/' . $stat->game->map->name . '.jpg'
                        : '/img/maps/placeholder.jpg',
                    'score'     => $stat->total_score,
                    'kills'     => $stat->kills,
                    'deaths'    => $stat->deaths,
                    'kd_ratio'  => $stat->kd_ratio,
                ];

                // always push to full list
                $gamesData[] = $gameData;

                // if game is inside global 100 → push to second list
//                if (in_array($stat->game_id, $theLast100GameIds, true)) {
//                    $gamesDataGlobal[] = $gameData;
//                }

                if (isset($lastGameLookup[$stat->game_id])) {

//                // Basic totals
                    $totalKills  += $stat->kills;
                    $totalDeaths += $stat->deaths;
                    $totalScore  += $stat->total_score;

                    $gamesDataGlobal[$stat->game_id] = $gameData;
                                    // Dynamic stat sums
                    foreach ($statFields as $field) {
                        $statSums[$field] += (int)($stat->{$field} ?? 0);
                    }
                }

            }

//            foreach ($player->player_stats_per_game as $stat) {
//                // Basic totals
//                $totalKills  += $stat->kills;
//                $totalDeaths += $stat->deaths;
//                $totalScore  += $stat->total_score;
//
//                // Dynamic stat sums
//                foreach ($statFields as $field) {
//                    $statSums[$field] += (int)($stat->{$field} ?? 0);
//                }
//
//                // Per-game data for UI
//                $gamesData[] = [
//                    'game_id' => $stat->game->id,
//                    'map_name' => $stat->game->map->name,
//                    'played_at' => $stat->game->started_at->format('D, dS M'),
//                    'map_image' => file_exists(
//                        WWW_ROOT . 'img/maps/' . $stat->game->map->name . '.jpg'
//                    )
//                        ? '/img/maps/' . $stat->game->map->name . '.jpg'
//                        : '/img/maps/placeholder.jpg',
//                    'score' => $stat->total_score,
//                    'kills' => $stat->kills,
//                    'deaths' => $stat->deaths,
//                    'kd_ratio' => $stat->kd_ratio,
//                ];
//            }
        }

        // Derived stat
        $kdRatio = $totalDeaths ? $totalKills / $totalDeaths : $totalKills;

        // Optional: sort stats by highest value
        arsort($statSums);

        $lastGameDateRange = $this->getGameDateRange();

        $gameIds = $this->getThisYearGameIds();

        $player->rankTheLast100 = $this->getPlayerRank($player->id, $theLast100GameIds);
        $player->rankAllTime = $this->getPlayerRank($player->id, $gameIds);

        $this->set(compact(
            'player',
            'totalKills',
            'totalDeaths',
            'totalScore',
            'kdRatio',
            'gamesData',
            'statSums',
            'gamesDataGlobal',
            'lastGameDateRange'
        ));
    }
    public function profile()
    {
        $player = $this->request->getAttribute('identity');
        if(!$player){
            $this->Flash->error('No profile found!');
            return $this->redirect(['action' => 'index']);
        }
        // all avatar files
        $avatars = glob(WWW_ROOT . 'img/players/*.jpg');
        $avatars = array_map('basename', $avatars);

        // avatars already used by OTHER players
        $usedAvatars = $this->Players
            ->find()
            ->select(['picture'])
            ->where([
                'picture IS NOT' => null,
                'id !=' => $player->id
            ])
            ->enableHydration(false)
            ->all()
            ->extract('picture')
            ->toList();

        // remove used avatars
        $avatars = array_values(array_diff($avatars, $usedAvatars));

        // random 20
        shuffle($avatars);
        $avatars = array_slice($avatars, 0, 39);

        $this->set(compact('player', 'avatars'));
    }

    public function avatar(?string $picture = null)
    {
        $player = $this->request->getAttribute('identity');

        $used = $this->Players->exists([
            'picture' => $picture,
            'id !=' => $player->id
        ]);

        if ($used) {
            $this->Flash->error('Avatar already taken');
            return $this->redirect(['action' => 'profile']);
        }


        // Reset avatar
        if ($picture === 'none') {
            $player->picture = null;
            $this->Players->saveOrFail($player);
            $this->Flash->success('Avatar removed');
            return $this->redirect(['action' => 'profile']);
        }

        // Normal avatar selection
        $file = WWW_ROOT . 'img/players/' . $picture;
        if (!file_exists($file)) {
            $this->Flash->error('Invalid avatar');
            return $this->redirect(['action' => 'profile']);
        }

        $player->picture = $picture;
        $this->Players->saveOrFail($player);

        $this->Flash->success('Avatar updated');
        return $this->redirect(['action' => 'profile']);
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
            ->where(['track'=>1, ['OR'=>[['name LIKE' => '%' . $query . '%',],['country' => $query,] ] ] ])
            ->orderAsc('name')
            ->limit(30)
            ->all()
            ->toList();


        return $this->response->withType('application/json')
            ->withStringBody(json_encode($players));
    }


    public function mapData()
    {
        $this->request->allowMethod(['get']);

        $players = $this->Players->find()
            ->select(['id', 'name', 'latitude', 'longitude', 'country'])
            ->where([
                'latitude IS NOT' => null,
                'longitude IS NOT' => null,
                'track' => 1,
                'name IS NOT' => 'unarmed',
            ])
            ->enableHydration(false)
            ->toArray();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($players));
    }


    public function map()
    {

    }

}
