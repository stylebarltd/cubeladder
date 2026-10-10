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

        // CTF rating, rank and type of everyone on the lists
        $ids = [];
        foreach ($top as $list) {
            foreach ($list as $row) {
                $ids[(string)$row->player_id] = true;
            }
        }
        $playerRatings = [];
        if ($ids) {
            foreach ($this->fetchTable('PlayerRatings')->find()->where(['player_id IN' => array_keys($ids)])->disableHydration() as $r) {
                $r['label'] = \App\Command\CalculateRatingsCommand::typeLabel($r['type'], (int)$r['attack_pct'], (int)$r['defense_pct'], (int)$r['combat_pct']);
                $playerRatings[$r['player_id']] = $r;
            }
        }

        $this->set(compact(
            'topHeadshots',
            'topSlashes',
            'topGibbed',
            'topKills',
            'topFlags',
            'topStreaks',
            'bestFlagHelpers',
            'achievementPlayers',
            'playerRatings'
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

        $sort = $this->request->getQuery('sort', 'rating');

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

        // CTF rating (bin/cake CalculateRatings) - looked up per request, as
        // it is recalculated after the import that clears the cache above
        $this->attachRatings($players, $sort);

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
    /**
     * CTF rating and type (bin/cake CalculateRatings) for the ranking boards -
     * looked up per request, as they are recalculated after the import that
     * clears the cached rankings. Sorting by rating puts unrated players
     * (fewer than 20 CTF games) last, by points.
     */
    private function attachRatings(array &$players, string $sort): void
    {
        $ratings = $this->fetchTable('PlayerRatings')->find()
            ->select(['player_id', 'rating', 'trend', 'type', 'attack_pct', 'defense_pct', 'combat_pct'])
            ->disableHydration()->all()->indexBy('player_id')->toArray();
        foreach ($players as $player) {
            $r = $ratings[$player->id] ?? null;
            $player->rating = $r ? (float)$r['rating'] : null;
            $player->rating_trend = isset($r['trend']) ? (float)$r['trend'] : null;
            $player->player_type = $r['type'] ?? null;
            $player->player_type_label = $r
                ? \App\Command\CalculateRatingsCommand::typeLabel($r['type'], (int)$r['attack_pct'], (int)$r['defense_pct'], (int)$r['combat_pct'])
                : null;
        }
        if ($sort === 'rating') {
            usort($players, fn($a, $b) => [$b->rating ?? -1, (int)$b->total_score] <=> [$a->rating ?? -1, (int)$a->total_score]);
        }
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

        $sort = $this->request->getQuery('sort', 'rating');

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
                // last seen anywhere (as on the All Time Ranking), linking to that game
                'last_seen' => '(SELECT MAX(g2.started_at) FROM player_stats_per_game ps2 '
                    . 'INNER JOIN games g2 ON g2.id = ps2.game_id '
                    . 'WHERE ps2.player_id = Players.id AND g2.inaccurate = 0)',
                'last_game_id' => '(SELECT ps2.game_id FROM player_stats_per_game ps2 '
                    . 'INNER JOIN games g2 ON g2.id = ps2.game_id '
                    . 'WHERE ps2.player_id = Players.id AND g2.inaccurate = 0 '
                    . 'ORDER BY g2.started_at DESC LIMIT 1)',
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


        $players = is_array($players) ? array_values($players) : $players->toList();
        // kd_ratio is worked out in PHP, so it is sorted here
        if ($sort === 'kd') {
            usort($players, fn($a, $b) => $b->stats['kd_ratio'] <=> $a->stats['kd_ratio']);
        }
        $this->attachRatings($players, $sort);

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
                'Achievements' => ['Maps'],
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
                    'map_image' => ($picture = \App\View\Helper\LayoutHelper::mapPicture($stat->game->map->name, (string)$stat->game->id)) !== null
                        ? \App\View\Helper\LayoutHelper::mapUrl($picture)
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

        // CTF rating and player type (bin/cake CalculateRatings)
        $PlayerRatings = $this->fetchTable('PlayerRatings');
        $rating = $PlayerRatings->find()->where(['player_id' => $player->id])->first();
        $ratedPlayers = $rating ? $PlayerRatings->find()->count() : 0;

        // Last seen: the player's latest game (inaccurate ones too - they were there)
        $lastSeen = $conn->execute(
            'SELECT g.id, g.started_at FROM player_stats_per_game p
             INNER JOIN games g ON g.id = p.game_id
             WHERE p.player_id = ? ORDER BY g.started_at DESC LIMIT 1',
            [$player->id]
        )->fetch('assoc') ?: null;

        // Milestones (bin/cake CalculateMilestones) and fun facts
        $milestones = $this->fetchTable('PlayerMilestones')->find()
            ->where(['player_id' => $player->id])
            ->orderBy(['reached_at' => 'ASC'])
            ->all()->toList();
        $progressRow = $this->fetchTable('PlayerMilestoneProgress')->find()->where(['player_id' => $player->id])->first();
        $milestoneProgress = $progressRow ? (json_decode($progressRow->progress, true) ?: []) : [];
        $funFacts = $milestoneProgress ? $this->funFacts((string)$player->id) : [];
        $weapons = $this->weaponsOfChoice((string)$player->id);

        $this->set(compact('nemeses', 'victims', 'quotes', 'records', 'favoriteMap', 'timePlayed', 'rating', 'ratedPlayers',
            'milestones', 'milestoneProgress', 'funFacts', 'lastSeen', 'weapons'));
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

    /** Weapon names for the weapon icons (webroot/img/weapons/<key>.svg) */
    public const WEAPON_NAMES = [
        'rifle' => 'Assault Rifle', 'smg' => 'Submachine Gun', 'sniper' => 'Sniper Rifle', 'shotgun' => 'Shotgun',
        'carabine' => 'Carbine', 'pistol' => 'Pistol', 'knife' => 'Knife', 'grenade' => 'Grenade',
    ];

    /**
     * The player's weapons of choice over all counted games, picked like the
     * icons in the rankings (LayoutHelper::weapon): 'choice' => [[key, name,
     * kills, pct]], 'all' => [key => kills] (most first), 'kills' => total.
     */
    private function weaponsOfChoice(string $playerId): array
    {
        $cols = ['shredded', 'sprayed', 'punctured', 'headshot', 'splattered', 'peppered', 'picked_off', 'busted', 'slashed', 'gibbed'];
        $sums = $this->Players->PlayerStatsPerGame->find()
            ->select(array_combine($cols, array_map(fn($c) => $this->Players->PlayerStatsPerGame->find()->func()->sum('PlayerStatsPerGame.' . $c), $cols)))
            ->where(['PlayerStatsPerGame.player_id' => $playerId])
            ->disableHydration()
            ->first() ?: [];
        $sums = array_map('intval', $sums);
        $all = [
            'rifle' => $sums['shredded'] ?? 0, 'smg' => $sums['sprayed'] ?? 0,
            'sniper' => ($sums['punctured'] ?? 0) + ($sums['headshot'] ?? 0),
            'shotgun' => ($sums['splattered'] ?? 0) + ($sums['peppered'] ?? 0),
            'carabine' => $sums['picked_off'] ?? 0, 'pistol' => $sums['busted'] ?? 0,
            'knife' => $sums['slashed'] ?? 0, 'grenade' => $sums['gibbed'] ?? 0,
        ];
        $kills = array_sum($all);
        if ($kills === 0) {
            return ['choice' => [], 'all' => [], 'kills' => 0];
        }
        arsort($all);
        $choice = [];
        foreach ((new \App\View\Helper\LayoutHelper(new \Cake\View\View()))->weapon($sums)['weapons'] as $key) {
            $choice[] = ['key' => $key, 'name' => self::WEAPON_NAMES[$key], 'kills' => $all[$key], 'pct' => (int)round($all[$key] * 100 / $kills)];
        }

        return ['choice' => $choice, 'all' => array_filter($all), 'kills' => $kills];
    }

    /**
     * Fun facts for the player page (inaccurate games left out), cached
     * until the next import clears 'rankings'.
     */
    private function funFacts(string $playerId): array
    {
        return Cache::remember('fun_facts_' . $playerId, function () use ($playerId) {
            $conn = \Cake\Datasource\ConnectionManager::get('default');
            $min = \App\Model\Table\PlayerStatsPerGameTable::MIN_MINUTES;
            $counted = "(p.minutes_played IS NULL OR p.minutes_played >= $min)";

            $people = $conn->execute(
                "SELECT COUNT(DISTINCT p2.player_id) AS players, COUNT(DISTINCT NULLIF(pl.country, '')) AS countries
                 FROM player_stats_per_game p
                 INNER JOIN games g ON g.id = p.game_id AND g.inaccurate = 0
                 INNER JOIN player_stats_per_game p2 ON p2.game_id = p.game_id AND p2.player_id <> p.player_id
                 INNER JOIN players pl ON pl.id = p2.player_id
                 WHERE p.player_id = ? AND $counted",
                [$playerId]
            )->fetch('assoc');

            $teammate = $conn->execute(
                "SELECT pl.id, pl.name, COUNT(*) AS n
                 FROM player_stats_per_game p
                 INNER JOIN games g ON g.id = p.game_id AND g.inaccurate = 0
                 INNER JOIN player_stats_per_game p2 ON p2.game_id = p.game_id AND p2.player_id <> p.player_id AND p2.team = p.team
                 INNER JOIN players pl ON pl.id = p2.player_id AND pl.track = 1
                 WHERE p.player_id = ? AND p.team IN ('CLA', 'RVSF') AND $counted
                 GROUP BY pl.id, pl.name ORDER BY n DESC LIMIT 1",
                [$playerId]
            )->fetch('assoc') ?: null;

            $server = $conn->execute(
                "SELECT g.server_name, COUNT(*) AS n FROM player_stats_per_game p
                 INNER JOIN games g ON g.id = p.game_id AND g.inaccurate = 0
                 WHERE p.player_id = ? AND $counted GROUP BY g.server_name ORDER BY n DESC LIMIT 1",
                [$playerId]
            )->fetch('assoc') ?: null;

            $weekday = $conn->execute(
                "SELECT DAYNAME(g.started_at) AS day, COUNT(*) AS n FROM player_stats_per_game p
                 INNER JOIN games g ON g.id = p.game_id AND g.inaccurate = 0
                 WHERE p.player_id = ? AND $counted GROUP BY day ORDER BY n DESC LIMIT 1",
                [$playerId]
            )->fetch('assoc') ?: null;

            $since = $conn->execute(
                'SELECT MIN(g.started_at) FROM player_stats_per_game p
                 INNER JOIN games g ON g.id = p.game_id AND g.inaccurate = 0 WHERE p.player_id = ?',
                [$playerId]
            )->fetchColumn(0);

            $gg = (int)$conn->execute(
                "SELECT COUNT(*) FROM events WHERE type = 'chat' AND actor_id = ?
                 AND LOWER(JSON_VALUE(details, '$.msg')) REGEXP '(^|[^a-z])gg([^a-z]|$)'",
                [$playerId]
            )->fetchColumn(0);

            $names = (int)$conn->execute('SELECT COUNT(DISTINCT alias) FROM player_aliases WHERE player_id = ?', [$playerId])->fetchColumn(0);

            return [
                'players' => (int)($people['players'] ?? 0),
                'countries' => (int)($people['countries'] ?? 0),
                'teammate' => $teammate,
                'server' => $server ? [
                    'name' => Configure::read('Ladder.servers.' . $server['server_name'] . '.name') ?: $server['server_name'],
                    'n' => (int)$server['n'],
                ] : null,
                'weekday' => $weekday,
                'since' => $since ?: null,
                'gg' => $gg,
                'names' => $names,
            ];
        }, 'rankings');
    }

    /**
     * Link preview picture of a player page (og:image): GET /players/card/{id}.
     * Drawn by PlayerCardImage and cached on disk until something on it changes.
     */
    public function card(string $id)
    {
        $player = $this->Players->find()->where(['id' => $id, 'track' => 1])->first();
        if (!$player) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $PlayerRatings = $this->fetchTable('PlayerRatings');
        $rating = $PlayerRatings->find()->where(['player_id' => $id])->first();
        $progressRow = $this->fetchTable('PlayerMilestoneProgress')->find()->where(['player_id' => $id])->first();
        $progress = $progressRow ? (json_decode($progressRow->progress, true) ?: []) : [];
        $map = $this->Players->PlayerStatsPerGame->find()
            ->select(['name' => 'Maps.name', 'n' => \App\Model\Table\PlayerStatsPerGameTable::countedGamesSql()])
            ->innerJoinWith('Games.Maps')
            ->where(['PlayerStatsPerGame.player_id' => $id])
            ->groupBy(['Maps.id', 'Maps.name'])
            ->orderByDesc('n')
            ->disableHydration()
            ->first();

        $card = [
            'name' => (string)$player->name,
            'country' => (string)$player->country,
            'subtitle' => $progress
                ? sprintf('%s h played  ·  %s games', number_format((float)($progress['hours'] ?? 0)), number_format((int)($progress['games'] ?? 0)))
                : '',
            'background' => !empty($map['name']) && is_file(WWW_ROOT . 'img/maps/' . $map['name'] . '.jpg')
                ? WWW_ROOT . 'img/maps/' . $map['name'] . '.jpg'
                : WWW_ROOT . 'img/bullet.jpg',
            'avatar' => !empty($player->picture) && is_file(WWW_ROOT . 'img/players/' . $player->picture)
                ? WWW_ROOT . 'img/players/' . $player->picture
                : WWW_ROOT . 'img/acl.png',
            'rating' => $rating ? [
                'rating' => (float)$rating->rating, 'type' => $rating->type, 'weapon' => $rating->weapon,
                'type_label' => \App\Command\CalculateRatingsCommand::typeLabel($rating->type, (int)$rating->attack_pct, (int)$rating->defense_pct, (int)$rating->combat_pct),
                // trend arrow from +-TREND_ARROW (last 10 CTF games vs the last 100)
                'trend' => $rating->trend !== null && abs((float)$rating->trend) >= \App\Command\CalculateRatingsCommand::TREND_ARROW
                    ? ((float)$rating->trend > 0 ? 'up' : 'down') : null,
                'rank' => (int)$rating->rank, 'rated' => $PlayerRatings->find()->count(),
                'win_rate' => $rating->win_rate !== null ? (float)$rating->win_rate : null,
                'attack_pct' => (int)$rating->attack_pct, 'defense_pct' => (int)$rating->defense_pct, 'combat_pct' => (int)$rating->combat_pct,
            ] : null,
            'stats' => $progress ? array_filter([
                'kills' => number_format((int)($progress['kills'] ?? 0)),
                'flags scored' => number_format((int)($progress['flags'] ?? 0)),
                'wins' => number_format((int)($progress['wins'] ?? 0)),
                'times MVP' => number_format((int)($progress['mvp'] ?? 0)),
            ], fn($v) => $v !== '0') : [],
            'weapons' => array_map(fn($w) => [$w['key'], $w['name'], $w['pct']], $this->weaponsOfChoice($id)['choice']),
        ];

        $dir = CACHE . 'cards' . DS;
        // avatar and drawing code dates too, so a new picture or layout redraws the card
        $file = $dir . $id . '-' . md5(json_encode($card) . filemtime($card['avatar']) . filemtime(ROOT . '/src/Service/PlayerCardImage.php')) . '.jpg';
        if (!is_file($file)) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            foreach (glob($dir . $id . '-*.jpg') ?: [] as $old) {
                @unlink($old);
            }
            file_put_contents($file, (new \App\Service\PlayerCardImage())->render($card));
        }

        return $this->response
            ->withType('jpg')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withFile($file);
    }
}
