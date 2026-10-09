<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Table\PlayerStatsPerGameTable;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

/**
 * Player milestones: tiered totals (hours, kills, flags, ...) and one-off
 * specials (hat-trick, untouchable, ...), each with the game and date it
 * was reached.
 *
 * compute() walks every player-game once, in date order, with running
 * totals per player - so a tier's date is the start of the game that
 * crossed it. Inaccurate games never count. Like everywhere on the site,
 * a "game played" (games, days, maps, servers, wins, MVP, specials) needs
 * MIN_MINUTES; kills, flags and time count from every game.
 */
class MilestoneService
{
    /** Tier names by position (1-based) */
    public const TIER_NAMES = [1 => 'Bronze', 2 => 'Silver', 3 => 'Gold', 4 => 'Platinum', 5 => 'Diamond', 6 => 'Master', 7 => 'Legend'];

    /** Gold and up are announced in Discord */
    public const ANNOUNCE_FROM_TIER = 3;

    /**
     * Tiered milestones: key => label, unit (for "1,000 kills"), icon
     * (Font Awesome class or an image path) and thresholds.
     */
    public const MILESTONES = [
        'hours'     => ['label' => 'Hours played',   'unit' => 'hours',          'icon' => 'fa-clock',             'tiers' => [10, 50, 100, 200, 300]],
        'games'     => ['label' => 'Games played',   'unit' => 'games',          'icon' => 'fa-gamepad',           'tiers' => [100, 500, 1000, 2500]],
        'days'      => ['label' => 'Days played',    'unit' => 'days',           'icon' => 'fa-calendar-days',     'tiers' => [10, 30, 100, 150]],
        'maps'      => ['label' => 'Maps explored',  'unit' => 'maps',           'icon' => 'fa-map',               'tiers' => [10, 25, 50, 100, 150]],
        'kills'     => ['label' => 'Kills',          'unit' => 'kills',          'icon' => '/img/achievements/kills.svg', 'tiers' => [500, 1000, 2500, 5000, 10000, 25000, 50000]],
        'headshots' => ['label' => 'Headshots',      'unit' => 'headshots',      'icon' => '/img/achievements/headshot.svg', 'tiers' => [100, 500, 1000, 2500]],
        'knife'     => ['label' => 'Butcher',        'unit' => 'knife kills',    'icon' => '/img/achievements/slashed.svg', 'tiers' => [10, 50, 100, 500]],
        'gibs'      => ['label' => 'Grenadier',      'unit' => 'grenade kills',  'icon' => '/img/achievements/gibbed.svg', 'tiers' => [50, 250, 500]],
        'flags'     => ['label' => 'Flags scored',   'unit' => 'flags scored',   'icon' => '/img/achievements/scored_with_the_flag.svg', 'tiers' => [10, 50, 100, 250, 500, 1000]],
        'returns'   => ['label' => 'Guardian',       'unit' => 'flags returned', 'icon' => 'fa-shield-halved',     'tiers' => [50, 250, 1000]],
        'steals'    => ['label' => 'Flag thief',     'unit' => 'flags stolen',   'icon' => 'fa-hand',              'tiers' => [100, 500, 1000]],
        'wins'      => ['label' => 'Wins',           'unit' => 'wins',           'icon' => 'fa-trophy',            'tiers' => [10, 100, 500]],
        'win_streak' => ['label' => 'Win streak',    'unit' => 'wins in a row',  'icon' => 'fa-fire',              'tiers' => [5, 10]],
        'mvp'       => ['label' => 'Game MVP',       'unit' => 'times MVP',      'icon' => 'fa-crown',             'tiers' => [10, 50, 100]],
    ];

    /** One-off milestones (tier 1) */
    public const SPECIALS = [
        'marathon'       => ['label' => 'Marathon',          'text' => '20+ games in one day',             'icon' => 'fa-person-running'],
        'map_addict'     => ['label' => 'Map addict',        'text' => '100 games on one map',             'icon' => 'fa-map-pin'],
        'tourist'        => ['label' => 'Tourist',           'text' => 'played on every ladder server',    'icon' => 'fa-suitcase-rolling'],
        'untouchable'    => ['label' => 'Untouchable',       'text' => '10+ kills and no death in a game', 'icon' => 'fa-ghost'],
        'hattrick'       => ['label' => 'Hat-trick',         'text' => '3 flags scored in one game',       'icon' => 'fa-hat-wizard'],
        'super_hattrick' => ['label' => 'Super hat-trick',   'text' => '5 flags scored in one game',       'icon' => 'fa-wand-magic-sparkles'],
    ];

    private const MARATHON_GAMES = 20;
    private const ADDICT_GAMES = 100;

    /**
     * Every player's reached milestones and current totals.
     *
     * @return array{milestones: array<int, array>, progress: array<string, array>}
     */
    public function compute(): array
    {
        $connection = ConnectionManager::get('default');
        $ladderServers = array_keys(array_filter(Configure::read('Ladder.servers') ?: [], fn($s) => $s['live'] ?? true));

        $statement = $connection->execute("
            SELECT p.player_id, p.game_id, g.started_at, g.server_name, m.name AS map,
                   COALESCE(p.minutes_played, 0) AS mins,
                   (p.minutes_played IS NULL OR p.minutes_played >= :min_minutes) AS counted,
                   p.kills, p.deaths, p.headshot, p.slashed, p.gibbed,
                   p.scored_with_the_flag AS scored, p.returned_the_flag AS returned, p.stole_the_flag AS stolen,
                   CASE
                       WHEN p.team NOT IN ('CLA', 'RVSF') OR g.team_scores IS NULL
                            OR NOT JSON_CONTAINS_PATH(g.team_scores, 'all', '$.CLA', '$.RVSF') THEN 0
                       WHEN (COALESCE(JSON_VALUE(g.team_scores, '$.CLA.flags'), 0) + 0, COALESCE(JSON_VALUE(g.team_scores, '$.CLA.frags'), 0) + 0)
                          = (COALESCE(JSON_VALUE(g.team_scores, '$.RVSF.flags'), 0) + 0, COALESCE(JSON_VALUE(g.team_scores, '$.RVSF.frags'), 0) + 0) THEN 0
                       WHEN ((COALESCE(JSON_VALUE(g.team_scores, '$.CLA.flags'), 0) + 0, COALESCE(JSON_VALUE(g.team_scores, '$.CLA.frags'), 0) + 0)
                           > (COALESCE(JSON_VALUE(g.team_scores, '$.RVSF.flags'), 0) + 0, COALESCE(JSON_VALUE(g.team_scores, '$.RVSF.frags'), 0) + 0))
                            = (p.team = 'CLA') THEN 1
                       ELSE -1
                   END AS won,
                   (p.total_score > 0 AND p.total_score = MAX(p.total_score) OVER (PARTITION BY p.game_id)
                    AND COUNT(*) OVER (PARTITION BY p.game_id) >= 4) AS mvp
            FROM player_stats_per_game p
            INNER JOIN games g ON g.id = p.game_id
            LEFT JOIN maps m ON m.id = g.map_id
            WHERE g.inaccurate = 0
            ORDER BY p.player_id, g.started_at, p.game_id",
            ['min_minutes' => PlayerStatsPerGameTable::MIN_MINUTES]
        );

        $milestones = [];
        $progress = [];
        $player = null;
        $state = [];

        while ($row = $statement->fetch('assoc')) {
            if ($row['player_id'] !== $player) {
                if ($player !== null) {
                    $progress[$player] = $this->summary($state, $ladderServers);
                }
                $player = $row['player_id'];
                $state = $this->emptyState();
            }
            $at = (string)$row['started_at'];
            $game = (string)$row['game_id'];
            $reach = function (string $key, int|float $value) use (&$state, &$milestones, $player, $at, $game) {
                $tiers = self::MILESTONES[$key]['tiers'];
                while (($state['tier'][$key] ?? 0) < count($tiers) && $value >= $tiers[$state['tier'][$key] ?? 0]) {
                    $tier = ($state['tier'][$key] ?? 0) + 1;
                    $state['tier'][$key] = $tier;
                    $milestones[] = ['player_id' => $player, 'milestone' => $key, 'tier' => $tier,
                        'threshold' => $tiers[$tier - 1], 'reached_at' => $at, 'game_id' => $game, 'detail' => null];
                }
            };
            $special = function (string $key, ?string $detail = null) use (&$state, &$milestones, $player, $at, $game) {
                if (empty($state['special'][$key])) {
                    $state['special'][$key] = true;
                    $milestones[] = ['player_id' => $player, 'milestone' => $key, 'tier' => 1,
                        'threshold' => 1, 'reached_at' => $at, 'game_id' => $game, 'detail' => $detail];
                }
            };

            // totals over every game
            $state['minutes'] += (int)$row['mins'];
            $state['kills'] += max(0, (int)$row['kills']);
            $state['headshots'] += (int)$row['headshot'];
            $state['knife'] += (int)$row['slashed'];
            $state['gibs'] += (int)$row['gibbed'];
            $state['flags'] += (int)$row['scored'];
            $state['returns'] += (int)$row['returned'];
            $state['steals'] += (int)$row['stolen'];
            $reach('hours', $state['minutes'] / 60);
            foreach (['kills', 'headshots', 'knife', 'gibs', 'flags', 'returns', 'steals'] as $key) {
                $reach($key, $state[$key]);
            }

            if (!(int)$row['counted']) {
                continue;
            }

            // games played (MIN_MINUTES+)
            $state['games']++;
            $day = substr($at, 0, 10);
            $state['days'][$day] = ($state['days'][$day] ?? 0) + 1;
            if ($row['map'] !== null) {
                $state['maps'][$row['map']] = ($state['maps'][$row['map']] ?? 0) + 1;
            }
            $state['servers'][$row['server_name']] = true;
            $reach('games', $state['games']);
            $reach('days', count($state['days']));
            $reach('maps', count($state['maps']));

            $won = (int)$row['won'];
            if ($won === 1) {
                $state['wins']++;
                $state['streak']++;
                $state['best_streak'] = max($state['best_streak'], $state['streak']);
                $reach('wins', $state['wins']);
                $reach('win_streak', $state['streak']);
            } elseif ($won === -1) {
                $state['streak'] = 0; // draws and non-team games leave the streak alone
            }
            if ((int)$row['mvp']) {
                $state['mvp']++;
                $reach('mvp', $state['mvp']);
            }

            // specials
            if ($state['days'][$day] >= self::MARATHON_GAMES) {
                $special('marathon');
            }
            if ($row['map'] !== null && $state['maps'][$row['map']] >= self::ADDICT_GAMES) {
                $special('map_addict', $row['map']);
            }
            if ($ladderServers && !array_diff($ladderServers, array_keys($state['servers']))) {
                $special('tourist');
            }
            if ((int)$row['kills'] >= 10 && (int)$row['deaths'] === 0) {
                $state['untouchable']++;
                $special('untouchable');
            }
            if ((int)$row['scored'] >= 3) {
                $state['hattricks']++;
                $special('hattrick');
            }
            if ((int)$row['scored'] >= 5) {
                $special('super_hattrick');
            }
        }
        if ($player !== null) {
            $progress[$player] = $this->summary($state, $ladderServers);
        }

        return ['milestones' => $milestones, 'progress' => $progress];
    }

    private function emptyState(): array
    {
        return [
            'minutes' => 0, 'kills' => 0, 'headshots' => 0, 'knife' => 0, 'gibs' => 0, 'flags' => 0,
            'returns' => 0, 'steals' => 0, 'games' => 0, 'days' => [], 'maps' => [], 'servers' => [],
            'wins' => 0, 'streak' => 0, 'best_streak' => 0, 'mvp' => 0, 'untouchable' => 0, 'hattricks' => 0,
            'tier' => [], 'special' => [],
        ];
    }

    /** Current totals of one player, for the progress bars */
    private function summary(array $s, array $ladderServers): array
    {
        $topMap = $s['maps'] ? array_search(max($s['maps']), $s['maps'], true) : null;

        return [
            'hours' => round($s['minutes'] / 60, 1),
            'games' => $s['games'],
            'days' => count($s['days']),
            'maps' => count($s['maps']),
            'kills' => $s['kills'],
            'headshots' => $s['headshots'],
            'knife' => $s['knife'],
            'gibs' => $s['gibs'],
            'flags' => $s['flags'],
            'returns' => $s['returns'],
            'steals' => $s['steals'],
            'wins' => $s['wins'],
            'win_streak' => $s['best_streak'],
            'mvp' => $s['mvp'],
            // for the specials' "how close" lines
            'most_games_in_a_day' => $s['days'] ? max($s['days']) : 0,
            'top_map' => $topMap !== null ? ['name' => $topMap, 'games' => $s['maps'][$topMap]] : null,
            'servers' => count(array_intersect($ladderServers, array_keys($s['servers']))),
            'servers_needed' => count($ladderServers),
            'untouchable' => $s['untouchable'],
            'hattricks' => $s['hattricks'],
        ];
    }
}
