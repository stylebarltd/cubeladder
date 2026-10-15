<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Table\PlayerStatsPerGameTable;
use App\Utility\Activity;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;

/**
 * CTF player rating (0.1 – 9.9, the average player is 5.0) and player type,
 * stored in player_ratings (rebuilt on every run).
 *
 * Every counted CTF game (MIN_MINUTES+ played, 4+ such players, not
 * inaccurate) gets a score per player. Each part is measured per minute
 * against the others in the same game, then scaled over all games:
 *
 *   55%  flag play   flags scored x5, steals +1, lost flags -1, returns +1
 *   30%  combat      K/D (80%) and frags per minute (20%)
 *   12%  team result win +1, loss -1, draw 0
 *    3%  discipline  teamkills and suicides (minus)
 *
 * The weights follow what decides CTF games in our data: flags scored and
 * completed runs first, then K/D; headshots tell almost nothing about
 * winning and do not count. A player's rating is the average over their
 * last 100 counted CTF games (at least 20). Only active players (a game in
 * the last Activity::ACTIVE_DAYS) are rated.
 *
 * Types compare each player's attack (scored / stolen / lost), defense
 * (returns) and combat with all rated players, as percentiles:
 *   All-Rounder   combat >= 65 and attack >= 65
 *   Flag Runner   attack >= 70 (and attack >= defense)
 *   Defender      defense >= 70
 *   Fragger       combat >= 70
 *   Offensive / Defensive Team Player: everyone else, by their lean
 *
 * Characters (archetypes) are what a player stands out for over the same
 * games, among the rated players: headshots, knife, grenade or pistol kills
 * per minute, or the average longest kill streak. A player in the top 10%
 * of one of them gets it (the strongest one if several); #1 in that stat is
 * "The Butcher" etc. on display (archetypeLabel()).
 *
 * Skill rating (individual, any mode, same 0-10 scale) of the same players
 * over their last 100 counted games, see SKILL_WEIGHTS and skills().
 */
class CalculateRatingsCommand extends Command
{
    public const WINDOW = 100;
    public const MIN_GAMES = 20;
    public const MIN_PLAYERS_PER_GAME = 4;

    public const TYPES = [
        'All-Rounder', 'Flag Runner', 'Defender', 'Fragger', 'Offensive Team Player', 'Defensive Team Player',
    ];

    /**
     * Character => [stat, minimum total in the window]; the stats are per
     * minute, except the streak (average longest kill streak per game)
     */
    public const ARCHETYPES = [
        'Headhunter' => ['headshot', 100], 'Butcher' => ['slashed', 50], 'Bomber' => ['gibbed', 100],
        'Pistolero' => ['busted', 200], 'Berserker' => ['streak', 0],
    ];

    /** Extra kill points for a headshot, knife or grenade kill (on top of the +1 of every kill) */
    public const SKILL_SPECIAL_BONUS = 0.5;

    /** Flag part of the skill rating: a flag scored counts this many steals (flag pickups) or returns */
    public const SKILL_FLAG_SCORED = 3;

    /**
     * Skill rating (individual, all modes): weights of the parts of a game.
     * points = kills, headshot / knife / grenade kills count
     * 1 + SKILL_SPECIAL_BONUS, per minute; kd = kills against deaths; flags =
     * flags scored (x SKILL_FLAG_SCORED), taken and returned, per minute (0
     * in modes without flags); win = team result (team modes - low, as the
     * teams change all the time); streak = longest kill streak; discipline = teamkills and
     * suicides (minus); lobby = how strong the others in the game were.
     */
    public const SKILL_WEIGHTS = [
        'points' => 0.30, 'kd' => 0.35, 'flags' => 0.10, 'win' => 0.05, 'streak' => 0.10, 'lobby' => 0.05, 'discipline' => 0.05,
    ];

    /** From this percentile in its stat a player gets the character */
    public const ARCHETYPE_PCT = 90;

    /** What each character stands out for (tooltips, the card) */
    public const ARCHETYPE_STATS = [
        'Headhunter' => 'headshots', 'Butcher' => 'knife kills', 'Bomber' => 'grenade kills',
        'Pistolero' => 'pistol kills', 'Berserker' => 'kill streaks',
    ];

    private const WEAPONS = [
        'Sniper' => ['punctured'], 'Rifle' => ['shredded'], 'Carbine' => ['picked_off'], 'SMG' => ['sprayed'],
        'Shotgun' => ['peppered', 'splattered'], 'Pistol' => ['busted'], 'Knife' => ['slashed'], 'Grenade' => ['gibbed'],
    ];

    public static function defaultName(): string
    {
        return 'CalculateRatings';
    }

    /** From this difference (last 10 vs last 100 games) the trend arrow shows */
    public const TREND_ARROW = 0.5;

    /** The trends compare the last this many games with the last WINDOW */
    public const TREND_GAMES = 10;

    /**
     * The type as shown: All-Rounders get their specialization - the
     * strongest of attack (flag play), defense (returns) and combat, as the
     * bars on the player page - e.g. "All-Rounder · Attack".
     */
    public static function typeLabel(string $type, int $attack, int $defense, int $combat): string
    {
        if ($type !== 'All-Rounder') {
            return $type;
        }
        $spec = match (true) {
            $attack >= $defense && $attack >= $combat => 'Attack',
            $defense >= $combat => 'Defense',
            default => 'Combat',
        };

        return $type . ' · ' . $spec;
    }

    /** "Butcher", or "The Butcher" for #1 in knife kills */
    public static function archetypeLabel(string $archetype, ?int $rank): string
    {
        return ($rank === 1 ? 'The ' : '') . $archetype;
    }

    /** "Top 3% in knife kills" (#1: "Most knife kills of all rated players") */
    public static function archetypeText(string $archetype, ?int $pct, ?int $rank): string
    {
        $stat = self::ARCHETYPE_STATS[$archetype] ?? '';
        if ($rank === 1) {
            return $archetype === 'Berserker' ? 'Longest kill streaks of all rated players' : 'Most ' . $stat . ' of all rated players';
        }

        return sprintf('Top %d%% in %s', max(1, 100 - (int)$pct), $stat);
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Calculate the CTF player ratings and player types (player_ratings)')
            ->addOption('dry-run', ['help' => 'Print the top 20, store nothing', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $started = microtime(true);
        $connection = $this->fetchTable('PlayerStatsPerGame')->getConnection();
        $rows = $connection->execute($this->sql(), [
            'min_minutes' => PlayerStatsPerGameTable::MIN_MINUTES,
            'min_players' => self::MIN_PLAYERS_PER_GAME,
            'window' => self::WINDOW,
            'min_games' => self::MIN_GAMES,
        ])->fetchAll('assoc');

        // only active players (a game in the last Activity::ACTIVE_DAYS) are rated and ranked
        if ($rows) {
            $active = array_flip(array_column($connection->execute(
                'SELECT DISTINCT s.player_id FROM player_stats_per_game s INNER JOIN games g ON g.id = s.game_id WHERE g.started_at >= ?',
                [Activity::since()]
            )->fetchAll('assoc'), 'player_id'));
            $rows = array_values(array_filter($rows, fn($r) => isset($active[$r['player_id']])));
        }

        if (!$rows) {
            $io->out('No player has enough CTF games yet.');

            return self::CODE_SUCCESS;
        }

        // percentiles of attack / defense / combat among the rated players
        $pct = [];
        foreach (['attack', 'defense', 'combat'] as $key) {
            $values = array_map(fn($r) => (float)$r[$key], $rows);
            sort($values);
            foreach ($rows as $i => $r) {
                $pct[$i][$key] = (int)round(100 * $this->countBelow($values, (float)$r[$key]) / count($values));
            }
        }

        // rating: the average game score, scaled so the average player is 5.0
        $raw = array_map(fn($r) => (float)$r['raw'], $rows);
        $mean = array_sum($raw) / count($raw);
        $sd = sqrt(array_sum(array_map(fn($v) => ($v - $mean) ** 2, $raw)) / count($raw)) ?: 1.0;

        $archetypes = $this->archetypes($rows);

        $now = DateTime::now();
        $ratings = [];
        foreach ($rows as $i => $r) {
            ['attack' => $a, 'defense' => $d, 'combat' => $c] = $pct[$i];
            $type = match (true) {
                $c >= 65 && $a >= 65 => 'All-Rounder',
                $a >= 70 && $a >= $d => 'Flag Runner',
                $d >= 70 => 'Defender',
                $c >= 70 => 'Fragger',
                $a >= $d => 'Offensive Team Player',
                default => 'Defensive Team Player',
            };
            $ratings[] = [
                'player_id' => $r['player_id'],
                'score' => (float)$r['raw'],
                'rating' => round(max(0.1, min(9.9, 5 + 1.5 * ((float)$r['raw'] - $mean) / $sd)), 1),
                'type' => $type,
                'weapon' => $this->weapon($r),
                'archetype' => $archetypes[$i]['archetype'] ?? null,
                'archetype_pct' => $archetypes[$i]['pct'] ?? null,
                'archetype_rank' => $archetypes[$i]['rank'] ?? null,
                'games' => (int)$r['games'],
                'attack_pct' => $a,
                'defense_pct' => $d,
                'combat_pct' => $c,
                'win_rate' => (int)$r['decided'] > 0 ? round((int)$r['wins'] / (int)$r['decided'], 3) : null,
                'calculated_at' => $now,
            ];
        }
        usort($ratings, fn($x, $y) => $y['score'] <=> $x['score']);
        foreach ($ratings as $i => &$rating) {
            $rating['rank'] = $i + 1;
            unset($rating['score']);
        }
        unset($rating);

        // skill rating (individual, all modes) of the same players, with its own place
        $skills = $this->skills(array_fill_keys(array_column($ratings, 'player_id'), true));
        uasort($skills, fn($x, $y) => $y['score'] <=> $x['score']);
        $skillRank = array_flip(array_keys($skills));
        foreach ($ratings as &$rating) {
            $skill = $skills[$rating['player_id']] ?? null;
            $rating['skill'] = $skill['skill'] ?? null;
            $rating['skill_rank'] = $skill ? $skillRank[$rating['player_id']] + 1 : null;
            $rating['skill_trend'] = $skill['trend'] ?? null;
            $rating['skill_win_rate'] = $skill && $skill['decided'] > 0 ? round($skill['wins'] / $skill['decided'], 3) : null;
        }
        unset($rating);

        if ($args->getOption('dry-run')) {
            $names = $this->fetchTable('Players')->find('list', valueField: 'name')
                ->where(['id IN' => array_column(array_slice($ratings, 0, 20), 'player_id')])->toArray();
            foreach (array_slice($ratings, 0, 20) as $r) {
                $io->out(sprintf('%3d  %-18s %4.1f  skill %4.1f (#%3d)  %-22s %-8s %s', $r['rank'], $names[$r['player_id']] ?? '?', $r['rating'], $r['skill'] ?? 0, $r['skill_rank'] ?? 0, $r['type'], $r['weapon'],
                    $r['archetype'] ? self::archetypeLabel($r['archetype'], $r['archetype_rank']) . ' (' . $r['archetype_pct'] . ')' : ''));
            }
            $counts = array_count_values(array_filter(array_column($ratings, 'archetype')));
            $io->out('Characters: ' . json_encode($counts) . ' of ' . count($ratings));
        } else {
            $connection->transactional(function ($connection) use ($ratings) {
                $connection->execute('DELETE FROM player_ratings');
                foreach (array_chunk($ratings, 200) as $chunk) {
                    $query = $connection->insertQuery('player_ratings')->insert(array_keys($chunk[0]), [
                        'calculated_at' => 'datetime',
                    ]);
                    foreach ($chunk as $row) {
                        $query->values($row);
                    }
                    $query->execute();
                }
            });
        }

        if (!$args->getOption('dry-run')) {
            // every rated CTF game on the same 0-10 scale: 5.0 = an average
            // game, one great game can reach 9.9
            $games = $connection->execute($this->perGameSql(), [
                'min_minutes' => PlayerStatsPerGameTable::MIN_MINUTES,
                'min_players' => self::MIN_PLAYERS_PER_GAME,
            ])->fetchAll('num');
            $scores = array_map(fn($r) => (float)$r[2], $games);
            $gMean = $scores ? array_sum($scores) / count($scores) : 0.0;
            $gSd = $scores ? (sqrt(array_sum(array_map(fn($v) => ($v - $gMean) ** 2, $scores)) / count($scores)) ?: 1.0) : 1.0;
            $connection->transactional(function ($connection) use ($games, $gMean, $gSd) {
                $connection->execute('DELETE FROM player_game_ratings');
                foreach (array_chunk($games, 1000) as $chunk) {
                    $query = $connection->insertQuery('player_game_ratings')->insert(['game_id', 'player_id', 'rating']);
                    foreach ($chunk as [$gameId, $playerId, $score]) {
                        $query->values([
                            'game_id' => $gameId,
                            'player_id' => $playerId,
                            'rating' => round(max(0.1, min(9.9, 5 + 1.5 * ((float)$score - $gMean) / $gSd)), 1),
                        ]);
                    }
                    $query->execute();
                }
            });
        }

        if (!$args->getOption('dry-run')) {
            // form: last 10 games vs last 100 (per-game ratings), for the arrows
            $trends = $connection->execute("
                WITH x AS (
                    SELECT r.player_id, r.rating AS game_rating,
                           ROW_NUMBER() OVER (PARTITION BY r.player_id ORDER BY g.ended_at DESC) AS rn
                    FROM player_game_ratings r
                    INNER JOIN games g ON g.id = r.game_id
                )
                SELECT x.player_id, AVG(IF(rn <= " . self::TREND_GAMES . ", game_rating, NULL)) - AVG(game_rating) AS trend
                FROM x INNER JOIN player_ratings pr ON pr.player_id = x.player_id
                WHERE rn <= :window
                GROUP BY x.player_id", ['window' => self::WINDOW])->fetchAll('assoc');
            $connection->transactional(function ($connection) use ($trends) {
                foreach ($trends as $t) {
                    $connection->updateQuery('player_ratings')
                        ->set(['trend' => round((float)$t['trend'], 2)])
                        ->where(['player_id' => $t['player_id']])
                        ->execute();
                }
            });
        }

        $io->out(sprintf('%d players rated in %.1f s', count($ratings), microtime(true) - $started));

        return self::CODE_SUCCESS;
    }

    /**
     * One row per player with enough games: the average game score and the
     * average attack / defense / combat over their last WINDOW games, plus
     * wins and kills per weapon.
     */
    private function sql(): string
    {
        return $this->ctes() . "
            SELECT ranked.player_id, COUNT(*) AS games, AVG(score) AS raw,
                   AVG(att_z) AS attack, AVG(dfn_z) AS defense, AVG(combat_z) AS combat,
                   SUM(win = 1) AS wins, SUM(win <> 0) AS decided, " . $this->sumWeapons() . ",
                   SUM(mins) AS mins, SUM(headshot) AS headshot,
                   AVG(longest_streak) AS streak, COUNT(longest_streak) AS streak_games
            FROM ranked
            INNER JOIN players pl ON pl.id = ranked.player_id AND pl.track = 1 AND pl.cheater = 0
            WHERE rn <= :window
            GROUP BY ranked.player_id
            HAVING COUNT(*) >= :min_games";
    }

    /**
     * The score of every player in every rated CTF game (an INSERT ... WITH
     * of the same query is very slow in MariaDB, so the rows go through PHP).
     */
    private function perGameSql(): string
    {
        return $this->ctes() . '
            SELECT game_id, player_id, score FROM ranked';
    }

    private function sumWeapons(): string
    {
        return implode(', ', array_map(fn($c) => "SUM($c) AS $c", array_merge(...array_values(self::WEAPONS))));
    }

    /** Per player-game scores ("ranked"), see the class comment */
    private function ctes(): string
    {
        $weaponCols = array_merge(...array_values(self::WEAPONS));
        $pgWeapons = implode(', ', array_map(fn($c) => "p.$c", $weaponCols));

        return "
            WITH pg AS (
                SELECT p.player_id, p.game_id, g.ended_at,
                       CAST(COALESCE(p.minutes_played, g.duration_minutes, 0) AS DOUBLE) AS mins,
                       p.kills, p.deaths, p.teamkills, p.suicided, p.headshot, p.longest_streak,
                       p.scored_with_the_flag AS sc, p.stole_the_flag AS st,
                       p.lost_the_flag AS lo, p.returned_the_flag AS re,
                       $pgWeapons,
                       " . $this->winSql() . " AS win
                FROM player_stats_per_game p
                INNER JOIN games g ON g.id = p.game_id
                WHERE g.mode = 'ctf' AND g.inaccurate = 0 AND g.ended_at IS NOT NULL
                  AND COALESCE(p.minutes_played, g.duration_minutes, 0) >= :min_minutes
            ),
            r AS (
                SELECT pg.*,
                       COUNT(*) OVER (PARTITION BY game_id) AS n,
                       (5 * sc + st - lo + re) / mins AS obj,
                       (5 * sc + st - lo) / mins AS att,
                       re / mins AS dfn,
                       LN((GREATEST(kills, 0) + 3) / (deaths + 3.0)) AS kd,
                       kills / mins AS fpm,
                       -(teamkills + suicided) / mins AS disc
                FROM pg
            ),
            rel AS (
                SELECT r.*,
                       obj - AVG(obj) OVER w AS obj_rel, att - AVG(att) OVER w AS att_rel,
                       dfn - AVG(dfn) OVER w AS dfn_rel, kd - AVG(kd) OVER w AS kd_rel,
                       fpm - AVG(fpm) OVER w AS fpm_rel, disc - AVG(disc) OVER w AS disc_rel
                FROM r
                WHERE n >= :min_players
                WINDOW w AS (PARTITION BY game_id)
            ),
            scale AS (
                SELECT AVG(obj_rel) AS m_obj, STDDEV_POP(obj_rel) AS s_obj, AVG(att_rel) AS m_att, STDDEV_POP(att_rel) AS s_att,
                       AVG(dfn_rel) AS m_dfn, STDDEV_POP(dfn_rel) AS s_dfn, AVG(kd_rel) AS m_kd, STDDEV_POP(kd_rel) AS s_kd,
                       AVG(fpm_rel) AS m_fpm, STDDEV_POP(fpm_rel) AS s_fpm, AVG(disc_rel) AS m_disc, STDDEV_POP(disc_rel) AS s_disc,
                       AVG(win) AS m_win, STDDEV_POP(win) AS s_win
                FROM rel
            ),
            z AS (
                SELECT rel.player_id, rel.game_id, rel.ended_at, rel.win, rel.mins, rel.headshot, rel.longest_streak, " . implode(', ', $weaponCols) . ",
                       (obj_rel - m_obj) / s_obj AS obj_z, (att_rel - m_att) / s_att AS att_z,
                       (dfn_rel - m_dfn) / s_dfn AS dfn_z,
                       0.8 * (kd_rel - m_kd) / s_kd + 0.2 * (fpm_rel - m_fpm) / s_fpm AS combat_z,
                       (disc_rel - m_disc) / s_disc AS disc_z, (win - m_win) / s_win AS win_z
                FROM rel CROSS JOIN scale
            ),
            ranked AS (
                SELECT z.*,
                       0.55 * obj_z + 0.30 * combat_z + 0.12 * win_z + 0.03 * disc_z AS score,
                       ROW_NUMBER() OVER (PARTITION BY player_id ORDER BY ended_at DESC) AS rn
                FROM z
            )";
    }

    /** The team result of p in g: 1 won, -1 lost, 0 draw or no teams */
    private function winSql(): string
    {
        return "CASE
                           WHEN p.team NOT IN ('CLA', 'RVSF') OR g.team_scores IS NULL
                                OR NOT JSON_CONTAINS_PATH(g.team_scores, 'all', '$.CLA', '$.RVSF') THEN 0
                           WHEN (COALESCE(JSON_VALUE(g.team_scores, '$.CLA.flags'), 0) + 0, COALESCE(JSON_VALUE(g.team_scores, '$.CLA.frags'), 0) + 0)
                              = (COALESCE(JSON_VALUE(g.team_scores, '$.RVSF.flags'), 0) + 0, COALESCE(JSON_VALUE(g.team_scores, '$.RVSF.frags'), 0) + 0) THEN 0
                           WHEN ((COALESCE(JSON_VALUE(g.team_scores, '$.CLA.flags'), 0) + 0, COALESCE(JSON_VALUE(g.team_scores, '$.CLA.frags'), 0) + 0)
                               > (COALESCE(JSON_VALUE(g.team_scores, '$.RVSF.flags'), 0) + 0, COALESCE(JSON_VALUE(g.team_scores, '$.RVSF.frags'), 0) + 0))
                                = (p.team = 'CLA') THEN 1
                           ELSE -1
                       END";
    }

    /**
     * Skill (individual) score of every player in every counted game of any
     * mode, before the lobby part, newest first per player (rn). Each part per
     * minute (streak per game) against the others in the same game, see
     * SKILL_WEIGHTS.
     */
    private function skillSql(): string
    {
        $w = self::SKILL_WEIGHTS;

        return "
            WITH pg AS (
                SELECT p.player_id, p.game_id, g.ended_at,
                       CAST(COALESCE(p.minutes_played, g.duration_minutes, 0) AS DOUBLE) AS mins,
                       GREATEST(p.kills, 0) AS kills, p.deaths, p.teamkills, p.suicided,
                       p.headshot + p.slashed + p.gibbed AS special,
                       COALESCE(p.longest_streak, 0) AS streak,
                       p.scored_with_the_flag AS sc, p.stole_the_flag AS st, p.returned_the_flag AS re,
                       " . $this->winSql() . " AS win
                FROM player_stats_per_game p
                INNER JOIN games g ON g.id = p.game_id
                WHERE g.inaccurate = 0 AND g.ended_at IS NOT NULL
                  AND COALESCE(p.minutes_played, g.duration_minutes, 0) >= :min_minutes
            ),
            r AS (
                SELECT pg.*,
                       COUNT(*) OVER (PARTITION BY game_id) AS n,
                       (kills + " . self::SKILL_SPECIAL_BONUS . " * special) / mins AS pts,
                       LN((kills + 3) / (deaths + 3.0)) AS kd,
                       (" . self::SKILL_FLAG_SCORED . " * sc + st + re) / mins AS flags,
                       -(teamkills + suicided) / mins AS disc
                FROM pg
            ),
            rel AS (
                SELECT r.*,
                       pts - AVG(pts) OVER w AS pts_rel, kd - AVG(kd) OVER w AS kd_rel,
                       streak - AVG(streak) OVER w AS streak_rel, flags - AVG(flags) OVER w AS flags_rel,
                       disc - AVG(disc) OVER w AS disc_rel
                FROM r
                WHERE n >= :min_players
                WINDOW w AS (PARTITION BY game_id)
            ),
            scale AS (
                SELECT AVG(pts_rel) AS m_pts, STDDEV_POP(pts_rel) AS s_pts, AVG(kd_rel) AS m_kd, STDDEV_POP(kd_rel) AS s_kd,
                       AVG(streak_rel) AS m_streak, STDDEV_POP(streak_rel) AS s_streak,
                       AVG(flags_rel) AS m_flags, STDDEV_POP(flags_rel) AS s_flags,
                       AVG(disc_rel) AS m_disc, STDDEV_POP(disc_rel) AS s_disc, AVG(win) AS m_win, STDDEV_POP(win) AS s_win
                FROM rel
            )
            SELECT player_id, game_id, win,
                   {$w['points']} * (pts_rel - m_pts) / s_pts + {$w['kd']} * (kd_rel - m_kd) / s_kd
                   + {$w['flags']} * (flags_rel - m_flags) / s_flags + {$w['win']} * (win - m_win) / s_win + {$w['streak']} * (streak_rel - m_streak) / s_streak
                   + {$w['discipline']} * (disc_rel - m_disc) / s_disc AS score,
                   ROW_NUMBER() OVER (PARTITION BY player_id ORDER BY ended_at DESC) AS rn
            FROM rel CROSS JOIN scale";
    }

    /**
     * Skill rating (0.1 - 9.9, 5.0 = average) of the rated players: the
     * average skill game score over their last WINDOW counted games of any
     * mode, where each game also counts how strong the others in it were
     * (the average first-pass skill of the others, SKILL_WEIGHTS['lobby']).
     *
     * @param array<string, true> $rated player ids to rate
     * @return array<string, array{skill: float, trend: float, score: float, wins: int, decided: int}>
     */
    private function skills(array $rated): array
    {
        $connection = $this->fetchTable('PlayerStatsPerGame')->getConnection();
        $rows = $connection->execute($this->skillSql(), [
            'min_minutes' => PlayerStatsPerGameTable::MIN_MINUTES,
            'min_players' => self::MIN_PLAYERS_PER_GAME,
        ])->fetchAll('num');

        // first pass: each player's average without the lobby
        $sum = $count = [];
        foreach ($rows as [$playerId, , , $score, $rn]) {
            if ($rn <= self::WINDOW) {
                $sum[$playerId] = ($sum[$playerId] ?? 0) + (float)$score;
                $count[$playerId] = ($count[$playerId] ?? 0) + 1;
            }
        }
        $first = [];
        foreach ($sum as $playerId => $s) {
            $first[$playerId] = $s / $count[$playerId];
        }

        // the lobby of each game: the others' first-pass average (unknown players count as average, 0)
        $gameSum = $gameN = [];
        foreach ($rows as [$playerId, $gameId]) {
            $gameSum[$gameId] = ($gameSum[$gameId] ?? 0) + ($first[$playerId] ?? 0);
            $gameN[$gameId] = ($gameN[$gameId] ?? 0) + 1;
        }
        $lobby = [];
        foreach ($rows as $i => [$playerId, $gameId]) {
            $lobby[$i] = ($gameSum[$gameId] - ($first[$playerId] ?? 0)) / max(1, $gameN[$gameId] - 1);
        }
        $lMean = array_sum($lobby) / max(1, count($lobby));
        $lSd = sqrt(array_sum(array_map(fn($v) => ($v - $lMean) ** 2, $lobby)) / max(1, count($lobby))) ?: 1.0;

        $total = [];
        $gameScores = [];
        foreach ($rows as $i => [$playerId, , $win, $score, $rn]) {
            if ($rn > self::WINDOW || !isset($rated[$playerId])) {
                continue;
            }
            $t = &$total[$playerId];
            $t ??= ['score' => 0.0, 'n' => 0, 'wins' => 0, 'decided' => 0, 'recent' => 0.0, 'nRecent' => 0];
            $gameScores[] = $game = (float)$score + self::SKILL_WEIGHTS['lobby'] * ($lobby[$i] - $lMean) / $lSd;
            $t['score'] += $game;
            $t['n']++;
            if ($rn <= self::TREND_GAMES) {
                $t['recent'] += $game;
                $t['nRecent']++;
            }
            $t['wins'] += (int)$win === 1 ? 1 : 0;
            $t['decided'] += (int)$win !== 0 ? 1 : 0;
            unset($t);
        }
        $total = array_filter($total, fn($t) => $t['n'] >= self::MIN_GAMES);
        if (!$total) {
            return [];
        }
        $raw = array_map(fn($t) => $t['score'] / $t['n'], $total);
        $mean = array_sum($raw) / count($raw);
        $sd = sqrt(array_sum(array_map(fn($v) => ($v - $mean) ** 2, $raw)) / count($raw)) ?: 1.0;
        // trend on the per-game scale (5.0 = an average game), as the CTF trend
        $gMean = array_sum($gameScores) / count($gameScores);
        $gSd = sqrt(array_sum(array_map(fn($v) => ($v - $gMean) ** 2, $gameScores)) / count($gameScores)) ?: 1.0;
        $skills = [];
        foreach ($raw as $playerId => $v) {
            $t = $total[$playerId];
            $skills[$playerId] = [
                'skill' => round(max(0.1, min(9.9, 5 + 1.5 * ($v - $mean) / $sd)), 1),
                'trend' => round(1.5 * ($t['recent'] / max(1, $t['nRecent']) - $v) / $gSd, 2),
                'score' => $v,
                'wins' => $total[$playerId]['wins'],
                'decided' => $total[$playerId]['decided'],
            ];
        }

        return $skills;
    }

    /**
     * The character of each rated player (by row index): the ARCHETYPES stat
     * with the highest percentile among the rated players, from
     * ARCHETYPE_PCT, with the player's place in it. Players below a stat's
     * minimum total (or with fewer than MIN_GAMES games with a streak) count
     * as 0 in it.
     *
     * @return array<int, array{archetype: string, pct: int, rank: int}>
     */
    private function archetypes(array $rows): array
    {
        $best = [];
        foreach (self::ARCHETYPES as $archetype => [$stat, $min]) {
            $values = [];
            foreach ($rows as $i => $r) {
                if ($stat === 'streak') {
                    $values[$i] = (int)$r['streak_games'] >= self::MIN_GAMES ? (float)$r['streak'] : 0.0;
                } else {
                    $values[$i] = (int)$r[$stat] >= $min && (float)$r['mins'] > 0 ? (int)$r[$stat] / (float)$r['mins'] : 0.0;
                }
            }
            $sorted = array_values($values);
            sort($sorted);
            arsort($values);
            $rank = 0;
            foreach ($values as $i => $value) {
                $rank++;
                if ($value <= 0) {
                    break;
                }
                $pct = (int)floor(100 * $this->countBelow($sorted, $value) / count($sorted));
                if ($pct >= self::ARCHETYPE_PCT && $pct > ($best[$i]['pct'] ?? -1)) {
                    $best[$i] = ['archetype' => $archetype, 'pct' => $pct, 'rank' => $rank];
                }
            }
        }

        return $best;
    }

    /** The weapon with 40%+ of the player's kills, else "Mixed" */
    private function weapon(array $row): string
    {
        $kills = [];
        foreach (self::WEAPONS as $weapon => $cols) {
            $kills[$weapon] = array_sum(array_map(fn($c) => (int)$row[$c], $cols));
        }
        $total = array_sum($kills);
        arsort($kills);
        $top = array_key_first($kills);

        return $total > 0 && $kills[$top] / $total >= 0.4 ? $top : 'Mixed';
    }

    /** Number of values in the sorted list below $value */
    private function countBelow(array $sorted, float $value): int
    {
        $lo = 0;
        $hi = count($sorted);
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($sorted[$mid] < $value) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }

        return $lo;
    }
}
