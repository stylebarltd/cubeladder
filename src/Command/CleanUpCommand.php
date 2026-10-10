<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;

/**
 * Keeps the database slim.
 *
 * After every import (process_logs.sh): player rows of a game without a
 * single point, kill or death.
 *
 * --deep, once a day (cron): also
 *  - players who never had a game (no player_stats_per_game row), last seen
 *    PLAYER_GRACE_DAYS ago, with no picture, no messages, no chat, not
 *    locked and not opted out (their "don't track me" is kept)
 *  - games without any player row (a day old) and their events / kill pairs
 *  - maps nobody played
 *  - rows of deleted players or games left in the other tables (not the
 *    chat: player pages quote it, it is the only copy)
 *  - preview pictures (tmp/cache/cards) not used for CARD_DAYS
 *  - logs/logfile_server_processor.log: over LOG_MAX_MB, only its last LOG_KEEP_MB stay
 *
 *   bin/cake CleanUp
 *   bin/cake CleanUp --deep [--dry-run]
 */
class CleanUpCommand extends Command
{
    private const PLAYER_GRACE_DAYS = 30;
    private const CARD_DAYS = 30;
    private const LOG_MAX_MB = 100;
    private const LOG_KEEP_MB = 20;

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Delete empty player rows (and with --deep: unused players, games, maps, orphans, old pictures)')
            ->addOption('deep', ['help' => 'The daily cleanup too', 'boolean' => true])
            ->addOption('dry-run', ['help' => 'Count only, delete nothing', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dry = (bool)$args->getOption('dry-run');
        $conn = $this->fetchTable('PlayerStatsPerGame')->getConnection();

        $this->purge($conn, $io, $dry, 'empty player rows', 'player_stats_per_game s',
            's.total_score = 0 AND s.kills = 0 AND s.deaths = 0 AND s.scored_with_the_flag = 0');

        if (!$args->getOption('deep')) {
            return self::CODE_SUCCESS;
        }

        // players who never had a game, and nothing else of theirs to keep
        $this->purge($conn, $io, $dry, 'players without games', 'players p',
            'NOT EXISTS (SELECT 1 FROM player_stats_per_game s WHERE s.player_id = p.id)
             AND (p.last_seen IS NULL OR p.last_seen < NOW() - INTERVAL ' . self::PLAYER_GRACE_DAYS . ' DAY)
             AND p.track = 1 AND COALESCE(p.locked, 0) = 0 AND (p.picture IS NULL OR p.picture = \'\')
             AND NOT EXISTS (SELECT 1 FROM messages m WHERE m.sender_id = p.id OR m.receiver_id = p.id)
             AND NOT EXISTS (SELECT 1 FROM events e WHERE e.actor_id = p.id OR e.target_id = p.id)');

        // games without a single player row, and what hangs on them
        // (games are deleted with their rows, the chat stays - see LogProcessorService)
        $this->purge($conn, $io, $dry, 'games without players', 'games g',
            'NOT EXISTS (SELECT 1 FROM player_stats_per_game s WHERE s.game_id = g.id)
             AND g.started_at < NOW() - INTERVAL 1 DAY');
        $this->purge($conn, $io, $dry, 'maps without games', 'maps m',
            'NOT EXISTS (SELECT 1 FROM games g WHERE g.map_id = m.id)');

        // left-overs of deleted players / games
        foreach ([
            ['player_stats_per_game', 'x', 'game_id', 'games'],
            ['kill_pairs', 'x', 'game_id', 'games'],
            ['player_game_ratings', 'x', 'game_id', 'games'],
            ['player_stats_per_game', 'x', 'player_id', 'players'],
            ['player_game_ratings', 'x', 'player_id', 'players'],
            ['player_ratings', 'x', 'player_id', 'players'],
            ['player_milestones', 'x', 'player_id', 'players'],
            ['player_milestone_progress', 'x', 'player_id', 'players'],
            ['player_aliases', 'x', 'player_id', 'players'],
            ['achievements', 'x', 'player_id', 'players'],
            ['kill_pairs', 'x', 'killer_id', 'players'],
            ['kill_pairs', 'x', 'victim_id', 'players'],
        ] as [$table, $alias, $column, $parent]) {
            $this->purge($conn, $io, $dry, "$table without $parent ($column)", "$table $alias",
                "$alias.$column IS NOT NULL AND NOT EXISTS (SELECT 1 FROM $parent p WHERE p.id = $alias.$column)");
        }

        // preview pictures not asked for in a while (they are redrawn on demand)
        $old = 0;
        foreach (glob(CACHE . 'cards' . DS . '*.jpg') ?: [] as $file) {
            if (filemtime($file) < strtotime('-' . self::CARD_DAYS . ' days') && fileatime($file) < strtotime('-' . self::CARD_DAYS . ' days')) {
                $old++;
                if (!$dry) {
                    @unlink($file);
                }
            }
        }
        $io->out(sprintf('# CleanUp: %d old preview pictures%s', $old, $dry ? ' (dry run)' : ' deleted'));

        // the import log (process_logs.sh appends to it every 10 minutes): keep its last part
        $log = LOGS . 'logfile_server_processor.log';
        if (is_file($log) && filesize($log) > self::LOG_MAX_MB * 1048576) {
            $io->out(sprintf('# CleanUp: import log %d MB, keeping the last %d MB%s', filesize($log) / 1048576, self::LOG_KEEP_MB, $dry ? ' (dry run)' : ''));
            if (!$dry) {
                $fh = fopen($log, 'r');
                fseek($fh, -self::LOG_KEEP_MB * 1048576, SEEK_END);
                fgets($fh); // from a whole line on
                $tail = stream_get_contents($fh);
                fclose($fh);
                file_put_contents($log, $tail);
            }
        }

        return self::CODE_SUCCESS;
    }

    /** Count or delete the rows of "$table $alias" WHERE $where */
    private function purge(Connection $conn, ConsoleIo $io, bool $dry, string $label, string $from, string $where): void
    {
        [, $alias] = explode(' ', $from);
        $n = (int)$conn->execute("SELECT COUNT(*) FROM $from WHERE $where")->fetchColumn(0);
        if ($n > 0 && !$dry) {
            $conn->execute("DELETE $alias FROM $from WHERE $where");
            if ($n >= 1000) {
                // give the space back (InnoDB keeps it otherwise)
                $conn->execute('OPTIMIZE TABLE ' . explode(' ', $from)[0])->fetchAll();
            }
        }
        $io->out(sprintf('# CleanUp: %d %s%s', $n, $label, $dry ? ' (dry run)' : ' deleted'));
    }
}
