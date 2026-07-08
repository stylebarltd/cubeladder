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

        // NOTE: Maps.times_played is a denormalized counter incremented at parse
        // time and never corrected when games are merged/deleted, so it no longer
        // reflects reality (e.g. ac_shine had counter=3 vs 281 real games). Count
        // the actual games (with stats) live instead.
        $maps = $this->Maps->find()
            ->select([
                'Maps.id',
                'Maps.name',
                'games_count' => 'COUNT(DISTINCT Games.id)',
            ])
            ->innerJoinWith('Games.PlayerStatsPerGame')
            ->groupBy(['Maps.id', 'Maps.name'])
            ->orderByDesc('games_count')
            ->limit(50)
            ->all();


        // Batch everything the slideshow needs for all maps at once. This page
        // used to run ~8 queries per map (top players + best-on-map + 6 stat
        // leaders) = ~400 round trips. The three helpers below each fold that
        // into a single window-function query keyed by map_id.
        $mapIds = collection($maps)->extract('id')->toList();

        $topPlayers = $this->getTopPlayersByMap($mapIds);
        $bestOnMap  = $this->getBestOnMapByMap($mapIds);
        $leaders    = $this->getMapLeaders($mapIds);

        foreach ($maps as $map) {
            $map->top_players = $topPlayers[$map->id] ?? [];
            // Last 10 weekly "best on map" winners for this map (newest first)
            $map->best_on_map = $bestOnMap[$map->id] ?? [];
            // Per-map stat leaders shown above the top-players list
            $map->leaders = $leaders[$map->id] ?? [];
        }


        $achievementMaps = $this->fetchTable('Achievements')->find()->where(['event_type' => 'best_on_map', 'week_end'=>date('Y-m-d', strtotime('last week sunday'))])->contain(['Players', 'Maps'])->toArray();


        $lastGameDateRange = $this->getGameDateRange();

        $this->set(compact('maps', 'lastGameDateRange', 'achievementMaps'));
    }

    /**
     * Top 8 players (by summed total_score) for each of the given maps, keyed
     * by map id. One window-function query instead of one query per map.
     *
     * @return array<string, array<int, \Cake\ORM\Entity>>
     */
    private function getTopPlayersByMap(array $mapIds): array
    {
        if (empty($mapIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($mapIds), '?'));
        $sql = "SELECT map_id, player_id, player_name, player_country, score FROM (
                    SELECT g.map_id AS map_id,
                           p.player_id AS player_id,
                           pl.name AS player_name,
                           pl.country AS player_country,
                           SUM(p.total_score) AS score,
                           ROW_NUMBER() OVER (
                               PARTITION BY g.map_id
                               ORDER BY SUM(p.total_score) DESC
                           ) AS rn
                    FROM player_stats_per_game p
                    INNER JOIN games g ON g.id = p.game_id
                    INNER JOIN players pl ON pl.id = p.player_id
                    WHERE g.map_id IN ($placeholders)
                    GROUP BY g.map_id, p.player_id, pl.name, pl.country
                ) t
                WHERE t.rn <= 8
                ORDER BY t.map_id, t.score DESC";

        $rows = $this->fetchTable('PlayerStatsPerGame')->getConnection()
            ->execute($sql, $mapIds)->fetchAll('assoc');

        $byMap = [];
        foreach ($rows as $row) {
            $byMap[$row['map_id']][] = new \Cake\ORM\Entity([
                'player_id' => $row['player_id'],
                'score' => $row['score'],
                'player' => new \Cake\ORM\Entity([
                    'id' => $row['player_id'],
                    'name' => $row['player_name'],
                    'country' => $row['player_country'],
                ]),
            ]);
        }

        return $byMap;
    }

    /**
     * Last 10 weekly "best on map" winners for each map (newest first), keyed
     * by map id. One window-function query instead of one query per map.
     *
     * @return array<string, array<int, \Cake\ORM\Entity>>
     */
    private function getBestOnMapByMap(array $mapIds): array
    {
        if (empty($mapIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($mapIds), '?'));
        $sql = "SELECT map_id, achievement_count, week_end,
                       player_id, player_name, player_country FROM (
                    SELECT a.map_id AS map_id,
                           a.count AS achievement_count,
                           a.week_end AS week_end,
                           pl.id AS player_id,
                           pl.name AS player_name,
                           pl.country AS player_country,
                           ROW_NUMBER() OVER (
                               PARTITION BY a.map_id
                               ORDER BY a.week_end DESC
                           ) AS rn
                    FROM achievements a
                    INNER JOIN players pl ON pl.id = a.player_id
                    WHERE a.event_type = 'best_on_map'
                      AND a.map_id IN ($placeholders)
                ) t
                WHERE t.rn <= 10
                ORDER BY t.map_id, t.week_end DESC";

        $rows = $this->fetchTable('Achievements')->getConnection()
            ->execute($sql, $mapIds)->fetchAll('assoc');

        $byMap = [];
        foreach ($rows as $row) {
            $byMap[$row['map_id']][] = new \Cake\ORM\Entity([
                'count' => $row['achievement_count'],
                'week_end' => $row['week_end']
                    ? new \Cake\I18n\DateTime($row['week_end'])
                    : null,
                'player' => new \Cake\ORM\Entity([
                    'id' => $row['player_id'],
                    'name' => $row['player_name'],
                    'country' => $row['player_country'],
                ]),
            ]);
        }

        return $byMap;
    }

    /**
     * Per-map record holders for each tracked stat, keyed by map id then stat
     * key. Each entry is the single record-setting game (highest per-game
     * value, no accumulation) with its player, date and game id.
     *
     * One window-function query per stat (6 total) instead of one per map per
     * stat (~300 total).
     *
     * @return array<string, array<string, \Cake\ORM\Entity>>
     */
    private function getMapLeaders(array $mapIds): array
    {
        if (empty($mapIds)) {
            return [];
        }

        // key shown in the view => real stat column. Column names are a fixed
        // whitelist here, never user input, so interpolating them is safe.
        $fields = [
            'ratio'    => 'kd_ratio',
            'headshot' => 'headshot',
            'flags'    => 'scored_with_the_flag',
            'slashed'  => 'slashed',
            'gibbed'   => 'gibbed',
            'points'   => 'total_score',
        ];

        $placeholders = implode(',', array_fill(0, count($mapIds), '?'));
        $connection = $this->fetchTable('PlayerStatsPerGame')->getConnection();

        $leaders = [];
        foreach ($fields as $key => $field) {
            $sql = "SELECT map_id, val, game_id, played_at,
                           player_id, player_name, player_country FROM (
                        SELECT g.map_id AS map_id,
                               p.$field AS val,
                               g.id AS game_id,
                               g.started_at AS played_at,
                               pl.id AS player_id,
                               pl.name AS player_name,
                               pl.country AS player_country,
                               ROW_NUMBER() OVER (
                                   PARTITION BY g.map_id
                                   ORDER BY p.$field DESC, g.started_at DESC
                               ) AS rn
                        FROM player_stats_per_game p
                        INNER JOIN games g ON g.id = p.game_id
                        INNER JOIN players pl ON pl.id = p.player_id
                        WHERE g.map_id IN ($placeholders)
                          AND p.$field > 0
                    ) t
                    WHERE t.rn = 1";

            $rows = $connection->execute($sql, $mapIds)->fetchAll('assoc');
            foreach ($rows as $row) {
                $leaders[$row['map_id']][$key] = new \Cake\ORM\Entity([
                    'val' => $row['val'],
                    'game_id' => $row['game_id'],
                    'played_at' => $row['played_at']
                        ? new \Cake\I18n\DateTime($row['played_at'])
                        : null,
                    'player' => new \Cake\ORM\Entity([
                        'id' => $row['player_id'],
                        'name' => $row['player_name'],
                        'country' => $row['player_country'],
                    ]),
                ]);
            }
        }

        return $leaders;
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
//        $theLast100GameIds = $this->getLastGameIds();
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
//                        'Games.id IN' => $theLast100GameIds
//                    ])
//            ])
//            ->contain(['Games' => function ($q) use ($theLast100GameIds) {
//                return $q
//                    ->where(['Games.id IN' => $theLast100GameIds])
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
