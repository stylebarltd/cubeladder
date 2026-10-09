<?php
declare(strict_types=1);

namespace App\Controller;


use Cake\Cache\Cache;
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
        $top = (new \App\Service\HallOfFameService())->topLists();
        $topHeadshots = $top['headshot'];
        $topSlashes   = $top['slashed'];
        $topGibbed    = $top['gibbed'];
        $topKills     = $top['kills'];
        $topFlags     = $top['flags'];
        $topStreaks   = $top['streak'];

//        $topSteals    = $this->getTopPlayers('stole_the_flag');
//        $topReturned    = $this->getTopPlayers('returned_the_flag');

        $bestFlagHelpers = $top['helper'];

        // Weekly achievement medals, keyed by player (same source as Players::index)
        $achievePlayers = $this->Players->Achievements->find()
            ->contain(['Players'])
            ->where([
                'week_end' => date('Y-m-d', strtotime('last week sunday')),
                'event_type IS NOT' => 'best_on_map'
            ]);
        $achievementPlayers = [];
        foreach ($achievePlayers as $player) {
            $achievementPlayers[$player->player_id][$player->event_type] = $player->count;
        }

        $this->set(compact(
            'topHeadshots',
            'topSlashes',
            'topGibbed',
            'topKills',
            'topFlags',
            'topStreaks',
            'bestFlagHelpers',
            'achievementPlayers'
        ));
    }
    /**
     * Hall of Fame categories: template key => field expression over
     * player_stats_per_game. Single source for the HoF page and the
     * personal-records card on the player view.
     */
    /** Hall of Fame: only games since this date count (start of the current ladder rules) */
    public const HOF_SINCE = '2026-03-01';

    public const HOF_CATEGORIES = [
        'kills' => ['title' => 'Most Kills', 'fields' => ['kills']],
        'headshot' => ['title' => 'Most Headshots', 'fields' => ['headshot']],
        'scored_with_the_flag' => ['title' => 'Most Flags scored', 'fields' => ['scored_with_the_flag']],
        'longest_streak' => ['title' => 'Longest Streak', 'fields' => ['longest_streak']],
        'slashed' => ['title' => 'Most Slashes', 'fields' => ['slashed']],
        'gibbed' => ['title' => 'Most Gibbed', 'fields' => ['gibbed']],
        'flag_helper' => ['title' => 'Flag Helper', 'fields' => ['stole_the_flag', 'returned_the_flag']],
    ];

    /**
     * One player's best single-game value per Hall of Fame category, with the
     * game it happened in and the player's rank among all tracked players
     * (same game window and tie rules as the Hall of Fame page).
     */
    private function getPlayerRecords(string $playerId): array
    {
        return Cache::remember('player_records_' . $playerId, function () use ($playerId) {
            $psg = $this->Players->PlayerStatsPerGame;
            $since = self::HOF_SINCE;

            // Every tracked player's per-category maximum in one grouped query.
            $select = ['player_id' => 'PlayerStatsPerGame.player_id'];
            foreach (self::HOF_CATEGORIES as $key => $cat) {
                $expr = implode(' + ', array_map(fn($f) => "PlayerStatsPerGame.$f", $cat['fields']));
                $select[$key] = "MAX($expr)";
            }
            $maxima = $psg->find()
                ->select($select)
                ->innerJoinWith('Players')
                ->innerJoinWith('Games')
                ->where(['Games.started_at >=' => $since, 'Players.track' => 1])
                ->groupBy(['PlayerStatsPerGame.player_id'])
                ->enableHydration(false)
                ->all()
                ->toArray();

            $own = null;
            foreach ($maxima as $row) {
                if ((string)$row['player_id'] === $playerId) {
                    $own = $row;
                    break;
                }
            }
            if (!$own) {
                return [];
            }

            $records = [];
            foreach (self::HOF_CATEGORIES as $key => $cat) {
                $value = (int)$own[$key];
                if ($value <= 0) {
                    continue;
                }
                $expr = implode(' + ', array_map(fn($f) => "PlayerStatsPerGame.$f", $cat['fields']));

                $game = $psg->find()
                    ->select([
                        'game_id' => 'Games.id',
                        'played_at' => 'Games.started_at',
                        'map_name' => 'Maps.name',
                    ])
                    ->innerJoinWith('Games.Maps')
                    ->where(['PlayerStatsPerGame.player_id' => $playerId, 'Games.started_at >=' => $since, "$expr = " . $value])
                    ->orderDesc('Games.started_at')
                    ->enableHydration(false)
                    ->first();

                $rank = 1;
                foreach ($maxima as $row) {
                    if ((int)$row[$key] > $value) {
                        $rank++;
                    }
                }

                $records[$key] = [
                    'title' => $cat['title'],
                    'value' => $value,
                    'rank' => $rank,
                    'players' => count($maxima),
                    'game_id' => $game['game_id'] ?? null,
                    'map_name' => $game['map_name'] ?? null,
                    'played_at' => $game['played_at'] ?? null,
                ];
            }

            return $records;
        }, 'rankings');
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

        // The ranking only changes when new games are imported, so the whole
        // (fairly expensive) aggregate is cached per sort and rebuilt on
        // demand. ProcessLogsCommand clears the 'rankings' cache after ingest.
        $players = Cache::remember('players_index_' . $sort, function () use ($order, $sort) {
            // players who participated in this year's games + aggregated stats.
            //
            // All sums are computed in SQL. We used to eager-load every
            // PlayerStatsPerGame row (~44k entities) just to re-sum them in PHP,
            // which was the main bottleneck on this page. Filtering on
            // Games.started_at directly (instead of a multi-thousand-id IN list
            // built by getThisYearGameIds()) keeps the query index-friendly.
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
                    'deaths' => 'SUM(PlayerStatsPerGame.deaths)',
                    'slashed' => 'SUM(PlayerStatsPerGame.slashed)',
                    'gibbed' => 'SUM(PlayerStatsPerGame.gibbed)',
                    'teamkills' => 'SUM(PlayerStatsPerGame.teamkills)',
                    'scored_with_the_flag' => 'SUM(PlayerStatsPerGame.scored_with_the_flag)',
                    // Weapon breakdown fields consumed by LayoutHelper::weapon()
                    'shredded' => 'SUM(PlayerStatsPerGame.shredded)',
                    'sprayed' => 'SUM(PlayerStatsPerGame.sprayed)',
                    'punctured' => 'SUM(PlayerStatsPerGame.punctured)',
                    'splattered' => 'SUM(PlayerStatsPerGame.splattered)',
                    'picked_off' => 'SUM(PlayerStatsPerGame.picked_off)',
                    'games' => \App\Model\Table\PlayerStatsPerGameTable::countedGamesSql(),
                    'last_seen' => 'MAX(Games.started_at)',
                    // Game id of that most-recent game, so the date can link to it
                    'last_game_id' => '(SELECT ps2.game_id FROM player_stats_per_game ps2 '
                        . 'INNER JOIN games g2 ON g2.id = ps2.game_id '
                        . 'WHERE ps2.player_id = Players.id AND g2.inaccurate = 0 '
                        . 'ORDER BY g2.started_at DESC LIMIT 1)',
                ])
                ->innerJoinWith('PlayerStatsPerGame.Games', function ($q) {
                    return $q->where(['Games.started_at >=' => date('Y') . '-01-01']);
                })
                ->where(['Players.track' => 1])
                ->group(['Players.id'])
                ->having([
                    'SUM(PlayerStatsPerGame.total_score) >=' => 5000
                ])
                ->order($order)
                ->all();

            $players->each(function ($player) {
                $kills  = (int)$player->kills;
                $deaths = (int)$player->deaths;

                $player->stats = [
                    'kills'                => $kills,
                    'deaths'               => $deaths,
                    'kd_ratio'             => $deaths > 0 ? round($kills / $deaths, 2) : $kills,
                    'headshot'             => (int)$player->headshot,
                    'teamkills'            => (int)$player->teamkills,
                    'gibbed'               => (int)$player->gibbed,
                    'slashed'              => (int)$player->slashed,
                    'scored_with_the_flag' => (int)$player->scored_with_the_flag,
                    'shredded'             => (int)$player->shredded,
                    'sprayed'              => (int)$player->sprayed,
                    'punctured'            => (int)$player->punctured,
                    'splattered'           => (int)$player->splattered,
                    'picked_off'           => (int)$player->picked_off,
                ];
            });

            // kd_ratio is derived in PHP, so it can't be ordered in SQL.
            if ($sort === 'kd') {
                return $players
                    ->sortBy(fn($p) => $p->stats['kd_ratio'], SORT_DESC, SORT_NUMERIC)
                    ->toList();
            }

            return $players->toList();
        }, 'rankings');

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

        // Only players that played from this IP can be picked
        if (!$player || $player->ip !== $this->request->clientIp()) {
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
                'games' => \App\Model\Table\PlayerStatsPerGameTable::countedGamesSql(),
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
                        // listed (marked inaccurate), but kept out of charts and sums
                        ->applyOptions(['includeInaccurate' => true])
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
        // Owner (identity + same IP) gets the edit button and still sees
        // their page when they opted out of tracking
        $canEdit = $this->ownsPlayer($player);
        $this->set('canEdit', $canEdit);
        if ($player->track == 0 && !$canEdit) {
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
                        : '/img/bullet.jpg',
                    'score'     => $stat->total_score,
                    'kills'     => $stat->kills,
                    'deaths'    => $stat->deaths,
                    'kd_ratio'  => $stat->kd_ratio,
                ];

                // charts: no inaccurate games and no short appearances
                // (under PlayerStatsPerGameTable::MIN_MINUTES on a team)
                $short = $stat->minutes_played !== null
                    && $stat->minutes_played < \App\Model\Table\PlayerStatsPerGameTable::MIN_MINUTES;
                if (!$stat->game->inaccurate && !$short) {
                    $gamesData[] = $gameData;
                }

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

        }

        // Derived stat
        $kdRatio = $totalDeaths ? $totalKills / $totalDeaths : $totalKills;

        // Optional: sort stats by highest value
        arsort($statSums);

        $lastGameDateRange = $this->getGameDateRange();

        $gameIds = $this->getThisYearGameIds();

        $player->rankTheLast100 = $this->getPlayerRank($player->id, $theLast100GameIds);
        $player->rankAllTime = $this->getPlayerRank($player->id, $gameIds);

        // Nemesis stats (kill_pairs is only filled since Sep 2026, no backfill)
        $conn = \Cake\Datasource\ConnectionManager::get('default');
        $nemeses = $conn->execute(
            'SELECT p.id, p.name, p.country, p.picture, SUM(kp.kills) n FROM kill_pairs kp
             JOIN players p ON p.id = kp.killer_id
             JOIN games g ON g.id = kp.game_id AND g.inaccurate = 0 WHERE kp.victim_id = ?
             GROUP BY p.id, p.name, p.country, p.picture HAVING n > 0 ORDER BY n DESC LIMIT 10',
            [$player->id]
        )->fetchAll('assoc');
        $victims = $conn->execute(
            'SELECT p.id, p.name, p.country, p.picture, SUM(kp.kills) n FROM kill_pairs kp
             JOIN players p ON p.id = kp.victim_id
             JOIN games g ON g.id = kp.game_id AND g.inaccurate = 0 WHERE kp.killer_id = ?
             GROUP BY p.id, p.name, p.country, p.picture HAVING n > 0 ORDER BY n DESC LIMIT 10',
            [$player->id]
        )->fetchAll('assoc');
        $quotes = array_map(
            fn($r) => ['msg' => json_decode($r['details'], true)['msg'] ?? '', 'at' => $r['event_time']],
            $conn->execute(
                "SELECT details, event_time FROM events WHERE type = 'chat' AND actor_id = ? ORDER BY id DESC LIMIT 5",
                [$player->id]
            )->fetchAll('assoc')
        );

        $records = $this->getPlayerRecords((string)$player->id);

        // Most played map (counted games) - the page background
        $favoriteMap = $this->Players->PlayerStatsPerGame->find()
            ->select(['name' => 'Maps.name', 'n' => \App\Model\Table\PlayerStatsPerGameTable::countedGamesSql()])
            ->innerJoinWith('Games.Maps')
            ->where(['PlayerStatsPerGame.player_id' => $player->id])
            ->groupBy(['Maps.id', 'Maps.name'])
            ->orderByDesc('n')
            ->disableHydration()
            ->first();

        // Time played: minutes on a team, summed over all counted games and
        // over the last 100 (games without a known time are left out)
        $minutes = fn(array $where) => (int)($this->Players->PlayerStatsPerGame->find()
            ->select(['m' => 'SUM(PlayerStatsPerGame.minutes_played)'])
            ->where(['PlayerStatsPerGame.player_id' => $player->id] + $where)
            ->disableHydration()
            ->first()['m'] ?? 0);
        $timePlayed = [
            'all' => $minutes([]),
            'last100' => $theLast100GameIds ? $minutes(['PlayerStatsPerGame.game_id IN' => $theLast100GameIds]) : 0,
        ];

        $this->set(compact('nemeses', 'victims', 'quotes', 'records', 'favoriteMap', 'timePlayed'));
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
        if (!$this->ownsPlayer($player)) {
            $this->Flash->error('You can only edit your own profile, from the IP you play from.');
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

        // every avatar nobody else uses, current one first
        $avatars = array_values(array_diff($avatars, $usedAvatars));
        natsort($avatars);
        $avatars = array_values($avatars);
        if ($player->picture && in_array($player->picture, $avatars, true)) {
            $avatars = array_values(array_unique([$player->picture, ...$avatars]));
        }

        $this->set(compact('player', 'avatars'));
    }

    public function avatar(?string $picture = null)
    {
        $this->request->allowMethod(['post']);
        $player = $this->request->getAttribute('identity');
        if (!$this->ownsPlayer($player)) {
            $this->Flash->error('You can only edit your own profile, from the IP you play from.');
            return $this->redirect(['action' => 'index']);
        }

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

        // Normal avatar selection: only a file from img/players itself
        $picture = basename((string)$picture);
        if (!preg_match('/^[\w.-]+\.jpg$/i', $picture) || !is_file(WWW_ROOT . 'img/players/' . $picture)) {
            $this->Flash->error('Invalid avatar');
            return $this->redirect(['action' => 'profile']);
        }

        $player->picture = $picture;
        $this->Players->saveOrFail($player);

        $this->Flash->success('Avatar updated');
        return $this->redirect(['action' => 'profile']);
    }

    /**
     * "Don't track me": players.track = 0 hides the player from every
     * ranking, the Hall of Fame and their public profile page.
     */
    public function privacy()
    {
        $this->request->allowMethod(['post']);
        $player = $this->request->getAttribute('identity');
        if (!$this->ownsPlayer($player)) {
            $this->Flash->error('You can only edit your own profile, from the IP you play from.');
            return $this->redirect(['action' => 'index']);
        }

        $player->track = $this->request->getData('dont_track') ? 0 : 1;
        $this->Players->saveOrFail($player);
        \Cake\Cache\Cache::clear('rankings');

        $this->Flash->success($player->track
            ? 'You are tracked again and show up in the rankings.'
            : 'You are hidden from the rankings, the Hall of Fame and your public profile.');

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
            ->select(['id', 'name', 'latitude', 'longitude', 'country', 'picture'])
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
