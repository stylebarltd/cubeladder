<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * One-off: flags of (team) keep the flag games imported before the parser
 * knew KTF scores ("X scored, carrying for 15 seconds, new score 1").
 * Reads the raw server logs still in tmp/, finds each KTF / TKTF game by
 * server and start time, counts every player's carries (the first score of
 * each, "carrying for 15 seconds") and sets scored_with_the_flag (and
 * total_score, 5 points each) of the game.
 * Idempotent: it sets the counts, it doesn't add to them.
 *
 *   bin/cake backfill_ktf_flags --dry-run
 *   bin/cake backfill_ktf_flags
 */
class BackfillKtfFlagsCommand extends Command
{
    /** log file suffix => games.server_name (as in process_logs.sh) */
    private const SERVERS = [
        'Europa' => 'acka-europa', 'Custom' => 'acka-custom', 'Nostalgic' => 'acka-nostalgic',
        'Assault' => 'acka-assault', 'local#1111' => 'chobbz-banana', 'local#2222' => 'chobbz-potato',
    ];
    private const FLAG_POINTS = 5;

    public static function defaultName(): string
    {
        return 'backfill_ktf_flags';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Flags of earlier (team) keep the flag games from the raw logs in tmp/')
            ->addOption('dry-run', ['help' => 'Show what would change, write nothing', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dry = (bool)$args->getOption('dry-run');
        $conn = $this->fetchTable('Games')->getConnection();
        $games = $updatedRows = $unmatched = 0;
        $seen = [];

        foreach (glob(TMP . 'serverlog_*.txt') ?: [] as $file) {
            if (!preg_match('~serverlog_(\d{4})(\d{2})\d{2}_[\d.]+_(.+)\.txt$~', basename($file), $fm) || !isset(self::SERVERS[$fm[3]])) {
                continue;
            }
            $server = self::SERVERS[$fm[3]];
            $year = (int)$fm[1];
            $lastMonth = (int)$fm[2];
            $current = null; // ['start' => ..., 'scores' => [name => n]]
            $finish = function () use (&$current, $conn, $server, $dry, $io, &$games, &$updatedRows, &$unmatched, &$seen) {
                if (!$current || !$current['scores']) {
                    $current = null;

                    return;
                }
                $game = $conn->execute(
                    'SELECT id FROM games WHERE server_name = ? AND mode IN ("ktf", "tktf") AND started_at BETWEEN ? AND ?',
                    [$server, date('Y-m-d H:i:s', $current['start'] - 5), date('Y-m-d H:i:s', $current['start'] + 5)]
                )->fetch('assoc');
                if (!$game || isset($seen[$game['id']])) {
                    $current = null;

                    return;
                }
                $seen[$game['id']] = true;
                $games++;
                // the game's players by their current names and their aliases
                $rows = $conn->execute(
                    'SELECT s.id, s.player_id, s.scored_with_the_flag, p.name, a.alias
                     FROM player_stats_per_game s
                     JOIN players p ON p.id = s.player_id
                     LEFT JOIN player_aliases a ON a.player_id = s.player_id
                     WHERE s.game_id = ?',
                    [$game['id']]
                )->fetchAll('assoc');
                $byName = [];
                foreach ($rows as $r) {
                    $byName[$r['name']] ??= $r;
                    if ($r['alias'] !== null) {
                        $byName[$r['alias']] ??= $r;
                    }
                }
                foreach ($current['scores'] as $name => $n) {
                    $row = $byName[$name] ?? null;
                    if (!$row) {
                        $unmatched++;
                        $io->verbose("  {$game['id']}: no player for \"$name\" ($n)");
                        continue;
                    }
                    $diff = $n - (int)$row['scored_with_the_flag'];
                    if ($diff === 0) {
                        continue;
                    }
                    $updatedRows++;
                    if (!$dry) {
                        $conn->execute(
                            'UPDATE player_stats_per_game SET scored_with_the_flag = ?, total_score = total_score + ? WHERE id = ?',
                            [$n, $diff * self::FLAG_POINTS, $row['id']]
                        );
                    }
                }
                $io->out(sprintf('%s %s %s: %s', $server, date('Y-m-d H:i', $current['start']), $game['id'],
                    implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($current['scores']), $current['scores']))));
                $current = null;
            };

            $fh = fopen($file, 'r');
            while (($line = fgets($fh)) !== false) {
                if (!preg_match('~^([A-Z][a-z]{2}) (\d{2}) (\d{2}:\d{2}:\d{2}) (.*)$~', rtrim($line, "\r\n"), $m)) {
                    continue;
                }
                $month = (int)date('n', strtotime($m[1] . ' 1 2000'));
                if ($month < $lastMonth) {
                    $year++; // the log went over new year
                }
                $lastMonth = $month;
                $rest = $m[4];
                if (str_starts_with($rest, 'Game start: ')) {
                    $finish();
                    if (preg_match('~^Game start: (team )?keep the flag on~', $rest)) {
                        $current = ['start' => strtotime("$year-$month-{$m[2]} {$m[3]}"), 'scores' => []];
                    }
                    continue;
                }
                // one flag per carry: its first score (as AcLogParser)
                if ($current && preg_match('~^\[[0-9a-f:.]+\]\s+(.+?)\s+scored, carrying for (\d+) seconds~i', $rest, $sm) && (int)$sm[2] <= 15) {
                    $current['scores'][$sm[1]] = ($current['scores'][$sm[1]] ?? 0) + 1;
                }
            }
            fclose($fh);
            $finish();
        }

        $io->out(sprintf('%s%d games, %d player rows %s, %d scores without a player',
            $dry ? '[dry run] ' : '', $games, $updatedRows, $dry ? 'to update' : 'updated', $unmatched));

        return self::CODE_SUCCESS;
    }
}
