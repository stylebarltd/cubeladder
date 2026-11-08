<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Table\PlayerStatsPerGameTable;
use Cake\Datasource\ConnectionManager;

/**
 * Special stats (a QR hunt unlock, level 4): a player's K/D and accuracy week
 * by week, their best and worst maps and when they play.
 *
 * Source: player_stats_per_game joined with games, one indexed query for the
 * player's games (a few thousand rows at most), summed up here. K/D, maps and
 * accuracy leave out games marked inaccurate; the hours count every game, as
 * the time played on the profile does. Accuracy (damage dealt / damage the
 * shots could have done) exists only for games the live loop saw (since Oct
 * 2026), as on the profile. Times are UTC; the page shows them in the
 * visitor's time zone.
 */
class SpecialStats
{
    public const WEEKS = 26;
    /** Counted games on a map before it can be a best or worst map */
    public const MIN_MAP_GAMES = 5;
    public const MAPS = 5;

    /** @return array<string, mixed> */
    public function player(string $id): array
    {
        $rows = ConnectionManager::get('default')->execute(
            'SELECT g.started_at, g.inaccurate, m.name map, s.kills, s.deaths, s.minutes_played, s.damage, s.shot_damage
             FROM player_stats_per_game s
             INNER JOIN games g ON g.id = s.game_id
             INNER JOIN maps m ON m.id = g.map_id
             WHERE s.player_id = ? AND g.started_at IS NOT NULL',
            [$id])->fetchAll('assoc');

        // the last WEEKS weeks, Monday 00:00 UTC, oldest first
        $monday = strtotime('monday this week', time());
        $weeks = [];
        for ($i = self::WEEKS - 1; $i >= 0; $i--) {
            $weeks[date('Y-m-d', strtotime("-$i week", $monday))] = ['kills' => 0, 'deaths' => 0, 'games' => 0, 'damage' => 0, 'shot' => 0, 'minutes' => 0];
        }
        $maps = [];
        $heat = array_fill(0, 168, 0);   // minutes played, Monday 0:00 UTC first
        $tot = ['kills' => 0, 'deaths' => 0, 'games' => 0, 'minutes' => 0, 'damage' => 0, 'shot' => 0, 'live' => 0];
        $days = [];

        foreach ($rows as $r) {
            $t = strtotime($r['started_at']);
            $min = (int)$r['minutes_played'];
            $counted = $r['minutes_played'] === null || $min >= PlayerStatsPerGameTable::MIN_MINUTES;
            // hours: every game, by its start
            $tot['minutes'] += $min;
            $heat[((int)date('N', $t) - 1) * 24 + (int)date('G', $t)] += $min;
            $days[date('Y-m-d', $t)] = ($days[date('Y-m-d', $t)] ?? 0) + $min;
            if ((int)$r['inaccurate'] === 1) {
                continue;
            }
            $k = (int)$r['kills'];
            $d = (int)$r['deaths'];
            $live = (int)$r['shot_damage'] > 0;
            $tot['kills'] += $k;
            $tot['deaths'] += $d;
            $tot['games'] += $counted ? 1 : 0;
            if ($live) {
                $tot['damage'] += (int)$r['damage'];
                $tot['shot'] += (int)$r['shot_damage'];
                $tot['live']++;
            }
            $w = date('Y-m-d', strtotime('monday this week', $t));
            if (isset($weeks[$w])) {
                $weeks[$w]['kills'] += $k;
                $weeks[$w]['deaths'] += $d;
                $weeks[$w]['games'] += $counted ? 1 : 0;
                $weeks[$w]['minutes'] += $min;
                if ($live) {
                    $weeks[$w]['damage'] += (int)$r['damage'];
                    $weeks[$w]['shot'] += (int)$r['shot_damage'];
                }
            }
            $m = $maps[$r['map']] ??= ['map' => $r['map'], 'kills' => 0, 'deaths' => 0, 'games' => 0, 'damage' => 0, 'shot' => 0];
            $m['kills'] += $k;
            $m['deaths'] += $d;
            $m['games'] += $counted ? 1 : 0;
            if ($live) {
                $m['damage'] += (int)$r['damage'];
                $m['shot'] += (int)$r['shot_damage'];
            }
            $maps[$r['map']] = $m;
        }

        $kd = fn(array $a) => $a['deaths'] ? $a['kills'] / $a['deaths'] : (float)$a['kills'];
        $acc = fn(array $a) => $a['shot'] > 0 ? (int)round($a['damage'] / $a['shot'] * 100) : null;

        $weekList = [];
        foreach ($weeks as $week => $w) {
            $weekList[] = ['week' => $week, 'kd' => $w['kills'] + $w['deaths'] ? round($kd($w), 2) : null, 'acc' => $acc($w)] + $w;
        }

        $ranked = array_values(array_filter($maps, fn($m) => $m['games'] >= self::MIN_MAP_GAMES));
        foreach ($ranked as &$m) {
            $m['kd'] = round($kd($m), 2);
            $m['acc'] = $acc($m);
        }
        unset($m);
        usort($ranked, fn($a, $b) => [$b['kd'], $b['games']] <=> [$a['kd'], $a['games']]);
        $best = array_slice($ranked, 0, self::MAPS);
        // worst: from the other end, without the maps already among the best
        $worst = array_slice(array_reverse(array_slice($ranked, count($best))), 0, self::MAPS);

        arsort($days);

        return [
            'totals' => $tot + ['kd' => round($kd($tot), 2), 'acc' => $acc($tot)],
            'weeks' => $weekList,
            'best' => $best,
            'worst' => $worst,
            'mapsRanked' => count($ranked),
            'heat' => $heat,
            'bestDay' => $days ? ['day' => array_key_first($days), 'minutes' => reset($days)] : null,
            'daysPlayed' => count($days),
        ];
    }
}
