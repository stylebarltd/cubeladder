<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;

/**
 * The numbers behind the front page's "players" count (/stats): how many
 * different players the ladder servers have seen, how many of them come back,
 * how many are online at once by hour of day. Cached with the rankings
 * (cleared after every log import).
 */
class CommunityStatsService
{
    /** "Recently": the last DAYS days */
    public const DAYS = 30;
    /** Regulars: played on at least this many of those days */
    public const REGULAR_DAYS = 8;

    public function get(): array
    {
        return Cache::remember('community_stats', fn() => $this->build(), 'rankings');
    }

    private function build(): array
    {
        $conn = ConnectionManager::get('default');
        $q = fn(string $sql, array $p = []) => $conn->execute($sql, $p)->fetchAll('assoc');
        $since = gmdate('Y-m-d H:i:s', time() - self::DAYS * 86400);
        // every game row of a tracked player, the base of all numbers here
        $base = 'FROM player_stats_per_game s INNER JOIN games g ON g.id = s.game_id
                 INNER JOIN players p ON p.id = s.player_id AND p.track = 1';

        $first = $q("SELECT MIN(g.started_at) AS first, COUNT(DISTINCT s.player_id) AS players, COUNT(DISTINCT g.server_name) AS servers $base")[0];

        // how many games each player has
        $buckets = array_fill_keys(['1', '2-4', '5-19', '20-99', '100+'], 0);
        foreach ($q("SELECT CASE WHEN n = 1 THEN '1' WHEN n < 5 THEN '2-4' WHEN n < 20 THEN '5-19' WHEN n < 100 THEN '20-99' ELSE '100+' END AS b, COUNT(*) AS c
                     FROM (SELECT s.player_id, COUNT(*) AS n $base GROUP BY s.player_id) x GROUP BY b") as $r) {
            $buckets[$r['b']] = (int)$r['c'];
        }

        // the last DAYS days: on how many days each player played
        $days = array_column($q("SELECT s.player_id, COUNT(DISTINCT DATE(g.started_at)) AS d $base WHERE g.started_at >= ? GROUP BY s.player_id", [$since]), 'd');
        $dayBuckets = ['1' => 0, '2-3' => 0, '4-7' => 0, '8-14' => 0, '15+' => 0];
        foreach ($days as $d) {
            $d = (int)$d;
            $dayBuckets[$d >= 15 ? '15+' : ($d >= 8 ? '8-14' : ($d >= 4 ? '4-7' : ($d >= 2 ? '2-3' : '1')))]++;
        }
        $week = (int)$q("SELECT COUNT(DISTINCT s.player_id) AS c $base WHERE g.started_at >= ?", [gmdate('Y-m-d H:i:s', time() - 7 * 86400)])[0]['c'];
        $perDay = $q("SELECT DATE(g.started_at) AS d, COUNT(DISTINCT s.player_id) AS c $base WHERE g.started_at >= ? GROUP BY d", [$since]);
        $perDayCounts = array_map('intval', array_column($perDay, 'c'));

        // online at once by hour of day (UTC): minutes played in games that started in that hour, per day
        $hours = array_fill(0, 24, 0.0);
        foreach ($q("SELECT HOUR(g.started_at) AS h, SUM(s.minutes_played) AS m $base WHERE g.started_at >= ? GROUP BY h", [$since]) as $r) {
            $hours[(int)$r['h']] = (float)$r['m'] / (self::DAYS * 60);
        }

        // month by month: who played, who was new
        $months = [];
        foreach ($q("SELECT DATE_FORMAT(g.started_at, '%Y-%m') AS m, COUNT(DISTINCT s.player_id) AS active, COUNT(DISTINCT g.id) AS games $base GROUP BY m ORDER BY m") as $r) {
            $months[$r['m']] = ['active' => (int)$r['active'], 'games' => (int)$r['games'], 'new' => 0];
        }
        foreach ($q("SELECT DATE_FORMAT(f, '%Y-%m') AS m, COUNT(*) AS c FROM (SELECT MIN(g.started_at) AS f $base GROUP BY s.player_id) x GROUP BY m") as $r) {
            if (isset($months[$r['m']])) {
                $months[$r['m']]['new'] = (int)$r['c'];
            }
        }

        $servers = $q("SELECT g.server_name, COUNT(DISTINCT g.id) AS games, COUNT(DISTINCT s.player_id) AS players $base
                       WHERE g.started_at >= ? GROUP BY g.server_name ORDER BY games DESC", [$since]);

        // where the regulars come from
        $countries = $q("SELECT COALESCE(NULLIF(p.country, ''), '') AS country, COUNT(*) AS c FROM (
                             SELECT s.player_id $base WHERE g.started_at >= ? GROUP BY s.player_id HAVING COUNT(DISTINCT DATE(g.started_at)) >= ?
                         ) r INNER JOIN players p ON p.id = r.player_id GROUP BY country ORDER BY c DESC", [$since, self::REGULAR_DAYS]);

        return [
            'first' => $first['first'],
            'players' => (int)$first['players'],
            'servers' => (int)$first['servers'],
            'buckets' => $buckets,
            'week' => $week,
            'month' => count($days),
            'regulars' => $dayBuckets['8-14'] + $dayBuckets['15+'],
            'dayBuckets' => $dayBuckets,
            'perDayAvg' => $perDayCounts ? array_sum($perDayCounts) / count($perDayCounts) : 0,
            'perDayMax' => $perDayCounts ? max($perDayCounts) : 0,
            'hours' => $hours,
            'months' => $months,
            'serverRows' => $servers,
            'countries' => $countries,
        ];
    }
}
