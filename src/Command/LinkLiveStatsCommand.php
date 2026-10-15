<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\LiveStatsRecorder;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use DateTime;
use DateTimeZone;

/**
 * Puts the damage and shot damage the live loop saw (live_player_runs,
 * LiveStatsRecorder) on the log's finished games: player_stats_per_game
 * damage / shot_damage, accuracy = damage / shot_damage. Runs after each
 * import (process_logs.sh); games without live numbers keep NULL.
 *
 * The logs are in the server's local time (Ladder.servers.<key>.tz,
 * default Europe/Berlin), the runs in UTC. A run belongs to a game of the
 * same server, map and mode when it started within the game and ended by
 * its end; names come from the game's minute scoreboards (game_timelines),
 * else the profiles in the game by name or alias.
 *
 *   bin/cake link_live_stats [--days=2] [--dry-run]
 */
class LinkLiveStatsCommand extends Command
{
    public const DEFAULT_TZ = 'Europe/Berlin';
    /** Slack around the game's start / end (polls every 15 s, clocks a little off) */
    private const SLACK = 90;

    public static function defaultName(): string
    {
        return 'link_live_stats';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Damage / accuracy from the live loop onto the finished games')
            ->addOption('days', ['help' => 'Games that ended in the last N days', 'default' => '2'])
            ->addOption('dry-run', ['help' => 'Show, write nothing', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dry = (bool)$args->getOption('dry-run');
        $days = min(LiveStatsRecorder::KEEP_DAYS, max(1, (int)$args->getOption('days')));
        $conn = $this->fetchTable('Games')->getConnection();
        $servers = Configure::read('Ladder.servers') ?: [];

        $games = $conn->execute(
            'SELECT g.id, g.server_name, g.mode, g.started_at, g.ended_at, m.name map, t.timeline
             FROM games g
             INNER JOIN maps m ON m.id = g.map_id
             LEFT JOIN game_timelines t ON t.game_id = g.id
             WHERE g.ended_at IS NOT NULL AND g.ended_at >= ?
               AND EXISTS (SELECT 1 FROM player_stats_per_game s WHERE s.game_id = g.id AND s.shot_damage IS NULL)',
            [date('Y-m-d H:i:s', time() - ($days + 1) * 86400)])->fetchAll('assoc');

        $linkedGames = $linkedPlayers = 0;
        foreach ($games as $g) {
            if (!isset($servers[$g['server_name']])) {
                continue;
            }
            $tz = new DateTimeZone($servers[$g['server_name']]['tz'] ?? self::DEFAULT_TZ);
            $start = (new DateTime($g['started_at'], $tz))->getTimestamp();
            $end = (new DateTime($g['ended_at'], $tz))->getTimestamp();
            $runs = $conn->execute(
                'SELECT name, damage, shot_damage FROM live_player_runs
                 WHERE server_name = ? AND map = ? AND mode = ? AND first_seen BETWEEN ? AND ? AND last_seen <= ?',
                [$g['server_name'], $g['map'], $g['mode'], gmdate('Y-m-d H:i:s', $start - self::SLACK),
                 gmdate('Y-m-d H:i:s', $end), gmdate('Y-m-d H:i:s', $end + self::SLACK)])->fetchAll('assoc');
            if (!$runs) {
                continue;
            }

            // the names of this game -> player ids
            $ids = [];
            foreach (json_decode((string)$g['timeline'], true) ?: [] as $snapshot) {
                foreach ($snapshot['p'] ?? [] as $p) {
                    if (!empty($p['id'])) {
                        $ids[$p['n']] ??= $p['id'];
                    }
                }
            }
            foreach ($conn->execute(
                'SELECT s.player_id, p.name, a.alias FROM player_stats_per_game s
                 JOIN players p ON p.id = s.player_id
                 LEFT JOIN player_aliases a ON a.player_id = s.player_id
                 WHERE s.game_id = ?', [$g['id']])->fetchAll('assoc') as $r) {
                $ids[$r['name']] ??= $r['player_id'];
                if ($r['alias'] !== null) {
                    $ids[$r['alias']] ??= $r['player_id'];
                }
            }

            // per player the run with the most shot damage (a reconnect continues it)
            $best = [];
            foreach ($runs as $r) {
                $pid = strtolower($r['name']) === 'unarmed' ? null : ($ids[$r['name']] ?? null);
                if ($pid && (int)$r['shot_damage'] > ($best[$pid]['shot_damage'] ?? -1)) {
                    $best[$pid] = ['damage' => (int)$r['damage'], 'shot_damage' => (int)$r['shot_damage'], 'name' => $r['name']];
                }
            }
            if (!$best) {
                continue;
            }
            $linkedGames++;
            $linkedPlayers += count($best);
            $io->verbose(sprintf('%s %s %s: %s', $g['server_name'], $g['started_at'], $g['map'],
                implode(', ', array_map(fn($b) => sprintf('%s %d%%', $b['name'], round($b['damage'] / max(1, $b['shot_damage']) * 100)), $best))));
            if (!$dry) {
                foreach ($best as $pid => $b) {
                    $conn->execute('UPDATE player_stats_per_game SET damage = ?, shot_damage = ? WHERE game_id = ? AND player_id = ?',
                        [$b['damage'], $b['shot_damage'], $g['id'], $pid]);
                }
            }
        }

        $io->out(sprintf('%slive damage for %d players in %d games', $dry ? '[dry run] ' : '', $linkedPlayers, $linkedGames));

        return self::CODE_SUCCESS;
    }
}
