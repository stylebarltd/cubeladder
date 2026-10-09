<?php


declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;
use Cake\Core\Configure;

class MapsController extends AppController
{
    public function index()
    {
        // Every map with counted games, most played first (dropdown + the
        // prev / next order). Maps.times_played is a stale parse-time counter
        // (e.g. ac_shine 3 vs 281 real games), so count the games live;
        // stats of inaccurate games are filtered out by PlayerStatsPerGame.
        $allMaps = $this->Maps->find()
            ->select([
                'Maps.id',
                'Maps.name',
                'games_count' => 'COUNT(DISTINCT Games.id)',
            ])
            ->innerJoinWith('Games.PlayerStatsPerGame')
            ->groupBy(['Maps.id', 'Maps.name'])
            ->orderByDesc('games_count')
            ->orderByAsc('Maps.name')
            ->all()
            ->toList();

        if (!$allMaps) {
            $this->set(['map' => null, 'allMaps' => [], 'rank' => 0, 'prevMap' => null, 'nextMap' => null]);

            return;
        }

        // ?map=<name>, default: the most played map
        $index = 0;
        $wanted = (string)$this->request->getQuery('map', '');
        foreach ($allMaps as $i => $m) {
            if ($m->name === $wanted) {
                $index = $i;
                break;
            }
        }
        $map = $allMaps[$index];
        $rank = $index + 1;
        $count = count($allMaps);
        $prevMap = $allMaps[($index - 1 + $count) % $count]->name;
        $nextMap = $allMaps[($index + 1) % $count]->name;

        $map->top_players = $this->getTopPlayersByMap([$map->id])[$map->id] ?? [];
        // Last 10 weekly "best on map" winners for this map (newest first)
        $map->best_on_map = $this->getBestOnMapByMap([$map->id])[$map->id] ?? [];
        // Per-map single-game records
        $map->leaders = $this->getMapLeaders([$map->id])[$map->id] ?? [];
        // The visitor's own stats on this map (cookie / IP identity)
        $identity = $this->request->getAttribute('identity');
        $myStats = $identity ? $this->getPlayerMapStats($identity->id, $map->id) : null;

        $this->set(compact('map', 'allMaps', 'rank', 'prevMap', 'nextMap', 'myStats'));
    }

    /**
     * Top 12 players (by summed total_score) for each of the given maps, keyed
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
                      AND g.inaccurate = 0
                    GROUP BY g.map_id, p.player_id, pl.name, pl.country
                ) t
                WHERE t.rn <= 12
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
        $minMinutes = \App\Model\Table\PlayerStatsPerGameTable::MIN_MINUTES;
        foreach ($fields as $key => $field) {
            // a ratio from a 1-minute visit is no record: skip short games
            $shortGames = $key === 'ratio'
                ? "AND (p.minutes_played IS NULL OR p.minutes_played >= $minMinutes)"
                : '';
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
                          AND g.inaccurate = 0
                          AND p.$field > 0
                          $shortGames
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

    /**
     * One player's totals on one map (inaccurate games left out, short games
     * not counted as played, see PlayerStatsPerGameTable::MIN_MINUTES), their
     * place among all players by summed points and their best single game.
     * Null when they never played the map.
     */
    private function getPlayerMapStats(string $playerId, string $mapId): ?array
    {
        $connection = $this->fetchTable('PlayerStatsPerGame')->getConnection();
        $counted = \App\Model\Table\PlayerStatsPerGameTable::countedGamesSql('p');

        $totals = $connection->execute(
            "SELECT $counted AS games, COUNT(*) AS joined,
                    SUM(p.minutes_played) AS minutes,
                    SUM(p.total_score) AS points,
                    SUM(p.kills) AS kills, SUM(p.deaths) AS deaths,
                    SUM(p.scored_with_the_flag) AS flags,
                    SUM(p.headshot) AS headshots,
                    MAX(g.started_at) AS last_played
             FROM player_stats_per_game p
             INNER JOIN games g ON g.id = p.game_id
             WHERE p.player_id = ? AND g.map_id = ? AND g.inaccurate = 0",
            [$playerId, $mapId]
        )->fetch('assoc');

        if (!$totals || (int)$totals['joined'] === 0) {
            return null;
        }

        // 1 + players with more summed points on this map
        $place = 1 + (int)$connection->execute(
            "SELECT COUNT(*) FROM (
                 SELECT p.player_id
                 FROM player_stats_per_game p
                 INNER JOIN games g ON g.id = p.game_id
                 WHERE g.map_id = ? AND g.inaccurate = 0
                 GROUP BY p.player_id
                 HAVING SUM(p.total_score) > ?
             ) t",
            [$mapId, (int)$totals['points']]
        )->fetchColumn(0);

        $best = $connection->execute(
            "SELECT p.total_score AS points, g.id AS game_id, g.started_at AS played_at
             FROM player_stats_per_game p
             INNER JOIN games g ON g.id = p.game_id
             WHERE p.player_id = ? AND g.map_id = ? AND g.inaccurate = 0
             ORDER BY p.total_score DESC, g.started_at DESC
             LIMIT 1",
            [$playerId, $mapId]
        )->fetch('assoc');

        $kills = (int)$totals['kills'];
        $deaths = (int)$totals['deaths'];

        return [
            'games' => (int)$totals['games'],
            'minutes' => (int)$totals['minutes'],
            'points' => (int)$totals['points'],
            'place' => $place,
            'kills' => $kills,
            'deaths' => $deaths,
            'kd' => $deaths > 0 ? $kills / $deaths : (float)$kills,
            'flags' => (int)$totals['flags'],
            'headshots' => (int)$totals['headshots'],
            'lastPlayed' => $totals['last_played'] ? new \Cake\I18n\DateTime($totals['last_played']) : null,
            'best' => $best ? [
                'points' => (int)$best['points'],
                'gameId' => $best['game_id'],
                'playedAt' => $best['played_at'] ? new \Cake\I18n\DateTime($best['played_at']) : null,
            ] : null,
        ];
    }
}
