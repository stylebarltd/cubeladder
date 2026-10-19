<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * One-off: flags of earlier flag games (CTF, HTF, KTF, TKTF) as the server's
 * scoreboard counts them, the way AcLogParser does it since 2026-10-19.
 * Reads the raw server logs, finds each flag game by server, mode, map and
 * start time and sets every player's scored_with_the_flag to the server's
 * count: their latest value on the minute scoreboards or a "new score N" line,
 * plus what they had before a reconnect that started them at 0 (below 0 is 0).
 * total_score changes by 5 points per flag of difference.
 * Idempotent: it sets the counts, it doesn't add to them.
 *
 *   bin/cake backfill_flags --dry-run [--dir=tmp/] [--top=25]
 *   bin/cake backfill_flags
 */
class BackfillFlagsCommand extends Command
{
    /** log file suffix => games.server_name (as in process_logs.sh); barry-b is parsed this way already */
    private const SERVERS = [
        'Europa' => 'acka-europa', 'Custom' => 'acka-custom', 'Nostalgic' => 'acka-nostalgic',
        'Assault' => 'acka-assault', 'local#1111' => 'chobbz-banana', 'local#2222' => 'chobbz-potato',
    ];
    private const MODES = [
        'capture the flag' => 'ctf', 'hunt the flag' => 'htf',
        'keep the flag' => 'ktf', 'team keep the flag' => 'tktf',
    ];
    private const FLAG_POINTS = 5;

    public static function defaultName(): string
    {
        return 'backfill_flags';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Flags of earlier flag games from the server scoreboards in the raw logs')
            ->addOption('dry-run', ['help' => 'Show what would change, write nothing', 'boolean' => true])
            ->addOption('dir', ['help' => 'Folder with the serverlog_*.txt files', 'default' => TMP])
            ->addOption('top', ['help' => 'Players with the biggest point changes to list', 'default' => '25']);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dry = (bool)$args->getOption('dry-run');
        $conn = $this->fetchTable('Games')->getConnection();
        $seen = [];
        $stats = ['games' => 0, 'unmatched_games' => 0, 'rows' => 0, 'unmatched_names' => 0];
        $byMode = [];      // mode => [games, rows changed, points +, points -]
        $byPlayer = [];    // player id => points change

        $files = glob(rtrim((string)$args->getOption('dir'), '/') . '/serverlog_*.txt') ?: [];
        sort($files);
        foreach ($files as $file) {
            if (!preg_match('~serverlog_(\d{4})(\d{2})\d{2}_[\d.]+_(.+)\.txt$~', basename($file), $fm) || !isset(self::SERVERS[$fm[3]])) {
                continue;
            }
            $server = self::SERVERS[$fm[3]];
            $year = (int)$fm[1];
            $lastMonth = (int)$fm[2];
            $current = null; // start, mode, map; per name: score now, banked from earlier sessions, check after a reconnect
            $cols = null;

            $finish = function () use (&$current, $conn, $server, $dry, $io, &$seen, &$stats, &$byMode, &$byPlayer) {
                if ($current) {
                    $current['flags'] = [];
                    foreach ($current['now'] + $current['banked'] as $name => $_) {
                        $current['flags'][$name] = max(0, ($current['banked'][$name] ?? 0) + ($current['now'][$name] ?? 0));
                    }
                }
                if (!$current || !$current['flags']) {
                    $current = null;

                    return;
                }
                // same server, mode, map and second (these servers' logs are in the stored time)
                $game = $conn->execute(
                    'SELECT g.id FROM games g JOIN maps m ON m.id = g.map_id
                     WHERE g.server_name = ? AND g.mode = ? AND m.name = ? AND g.started_at = ?',
                    [$server, $current['mode'], $current['map'], date('Y-m-d H:i:s', $current['start'])]
                )->fetch('assoc');
                if (!$game) {
                    $stats['unmatched_games']++;
                    $current = null;

                    return;
                }
                if (isset($seen[$game['id']])) {
                    $current = null;

                    return;
                }
                $seen[$game['id']] = true;
                $stats['games']++;
                $mode = $current['mode'];
                $byMode[$mode] ??= ['games' => 0, 'rows' => 0, 'plus' => 0, 'minus' => 0];
                $byMode[$mode]['games']++;

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
                $done = [];
                foreach ($current['flags'] as $name => $flags) {
                    $row = $byName[$name] ?? null;
                    if (!$row) {
                        $stats['unmatched_names']++;
                        continue;
                    }
                    if (isset($done[$row['id']])) {
                        continue;
                    }
                    $done[$row['id']] = true;
                    $diff = $flags - (int)$row['scored_with_the_flag'];
                    if ($diff === 0) {
                        continue;
                    }
                    $stats['rows']++;
                    $byMode[$mode]['rows']++;
                    $byMode[$mode][$diff > 0 ? 'plus' : 'minus'] += $diff * self::FLAG_POINTS;
                    $byPlayer[$row['player_id']] = ($byPlayer[$row['player_id']] ?? 0) + $diff * self::FLAG_POINTS;
                    $io->verbose(sprintf('  %s %s %s %s: %d -> %d', $server, date('Y-m-d H:i:s', $current['start']), $mode, $name, $row['scored_with_the_flag'], $flags));
                    if (!$dry) {
                        $conn->execute(
                            'UPDATE player_stats_per_game SET scored_with_the_flag = ?, total_score = total_score + ? WHERE id = ?',
                            [$flags, $diff * self::FLAG_POINTS, $row['id']]
                        );
                    }
                }
                $current = null;
            };

            // a player's flag score as the server shows it now; after a reconnect
            // the server either continues their score or starts at 0 again
            $see = function (string $name, int $value) use (&$current) {
                if (isset($current['check'][$name])) {
                    if ($value >= $current['check'][$name]) {
                        $current['banked'][$name] -= $current['check'][$name]; // continued
                    }
                    unset($current['check'][$name]);
                }
                $current['now'][$name] = $value;
            };

            $fh = fopen($file, 'r');
            while (($line = fgets($fh)) !== false) {
                if (!preg_match('~^([A-Z][a-z]{2}) +(\d{1,2}) (\d{2}:\d{2}:\d{2}) ?(.*)$~', rtrim($line, "\r\n"), $m)) {
                    continue;
                }
                $month = (int)date('n', strtotime($m[1] . ' 1 2000'));
                if ($month < $lastMonth) {
                    $year++; // the log went over new year
                }
                $lastMonth = $month;
                $rest = trim($m[4]);
                if (str_starts_with($rest, 'Game start: ')) {
                    $finish();
                    if (preg_match('~^Game start: (.+?) on ([^,]+),~', $rest, $gm) && isset(self::MODES[$gm[1]])) {
                        $current = ['start' => strtotime("$year-$month-{$m[2]} {$m[3]}"), 'mode' => self::MODES[$gm[1]],
                            'map' => trim($gm[2]), 'now' => [], 'banked' => [], 'check' => []];
                    }
                    continue;
                }
                if (!$current) {
                    continue;
                }
                // "[ip] name scored with the flag for CLA, new score 4": the player's
                // flag score right now (newer than their last minute row)
                if (preg_match('~^\[[0-9a-f:.]+\]\s+(\S+)\s.*, new score (-?\d+)$~', $rest, $sm) && strtolower($sm[1]) !== 'unarmed') {
                    $see($sm[1], (int)$sm[2]);
                    continue;
                }
                // left the game: their score so far is kept (a reconnect may start at 0)
                if (preg_match('~^\[[0-9a-f:.]+\]\s+disconnected client (\S+) cn \d+~', $rest, $sm) && isset($current['now'][$sm[1]])) {
                    $name = $sm[1];
                    $current['banked'][$name] = ($current['banked'][$name] ?? 0) + $current['now'][$name];
                    $current['check'][$name] = $current['now'][$name];
                    $current['now'][$name] = 0;
                    continue;
                }
                // minute scoreboard (also the final one after "game finished"):
                // header "cn name team flag frag ...", then one row per player
                if (str_starts_with($rest, 'cn name ')) {
                    $cols = preg_split('~\s+~', $rest);
                    continue;
                }
                if ($cols && preg_match('~^\d+\s+\S.*\s-?\d+\s+(?:normal|admin)\s+\S+$~', $rest)) {
                    $tokens = preg_split('~\s+~', $rest);
                    $tail = count($cols) - 2; // everything after cn and name
                    if (count($tokens) < $tail + 2) {
                        continue;
                    }
                    $values = array_combine(array_slice($cols, 2), array_slice($tokens, -$tail));
                    $name = implode(' ', array_slice($tokens, 1, count($tokens) - 1 - $tail));
                    $team = $values['team'] ?? null;
                    if (!isset($values['flag']) || in_array($team, ['SPEC', 'CSPC', 'RSPC'], true) || strtolower($name) === 'unarmed') {
                        continue;
                    }
                    $see($name, (int)$values['flag']);
                }
            }
            fclose($fh);
            $finish();
        }

        $io->out(sprintf('%s%d flag games matched (%d in the logs not found in the database), %d player rows %s, %d names without a player',
            $dry ? '[dry run] ' : '', $stats['games'], $stats['unmatched_games'], $stats['rows'],
            $dry ? 'to change' : 'changed', $stats['unmatched_names']));
        ksort($byMode);
        foreach ($byMode as $mode => $s) {
            $io->out(sprintf('  %-4s %5d games, %5d rows changed, points %+d / %+d (net %+d)',
                $mode, $s['games'], $s['rows'], $s['plus'], $s['minus'], $s['plus'] + $s['minus']));
        }
        $this->reportPlayers($byPlayer, (int)$args->getOption('top'), $io);

        return self::CODE_SUCCESS;
    }

    /** The players whose points change most, against their all-time points */
    private function reportPlayers(array $byPlayer, int $top, ConsoleIo $io): void
    {
        if (!$byPlayer || $top < 1) {
            return;
        }
        uasort($byPlayer, fn($a, $b) => abs($b) <=> abs($a));
        $ids = array_slice(array_keys($byPlayer), 0, $top);
        $rows = $this->fetchTable('Games')->getConnection()->execute(
            'SELECT p.id, p.name, SUM(s.total_score) points FROM players p
             JOIN player_stats_per_game s ON s.player_id = p.id
             WHERE p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') GROUP BY p.id, p.name',
            $ids
        )->fetchAll('assoc');
        $info = array_column($rows, null, 'id');
        $io->out(sprintf('Biggest changes (of %d players):', count($byPlayer)));
        foreach ($ids as $id) {
            $points = (int)($info[$id]['points'] ?? 0);
            $io->out(sprintf('  %-20s %+6d points  (all-time %d, %+.1f%%)', $info[$id]['name'] ?? $id, $byPlayer[$id],
                $points, $points ? 100 * $byPlayer[$id] / $points : 0));
        }
    }
}
