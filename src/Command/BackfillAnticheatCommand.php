<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\CheatSignals;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * One-off: the cheat signs (CheatSignals - far flag touches / pickups, kill
 * bursts) of the raw server logs still in tmp/, from before AcLogParser
 * recorded them. Each signal goes to the game running at that time and the
 * player of that game with the name (or an alias of it).
 * Idempotent: the same events.event_hash as the parser, so nothing doubles.
 *
 *   bin/cake backfill_anticheat --dry-run
 *   bin/cake backfill_anticheat
 *   bin/cake backfill_anticheat --redo-bursts   (bursts cut a new way: the old ones go)
 */
class BackfillAnticheatCommand extends Command
{
    /** log file suffix => games.server_name (as in process_logs.sh) */
    private const SERVERS = [
        'Europa' => 'acka-europa', 'Custom' => 'acka-custom', 'Nostalgic' => 'acka-nostalgic',
        'Assault' => 'acka-assault', 'local#1111' => 'chobbz-banana', 'local#2222' => 'chobbz-potato', 'local#28763' => 'barry-b',
    ];
    private const KILL_REGEX = '~^\[([0-9a-f:\.]+)\]\s+(.+?)\s+(sprayed|headshot|punctured|busted|shredded|peppered|gibbed|splattered|slashed|picked off)\s+(.+)$~i';
    private const IGNORED_NAMES = ['unarmed'];

    public static function defaultName(): string
    {
        return 'backfill_anticheat';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Cheat signs (far pickups, kill bursts) of the raw logs in tmp/')
            ->addOption('dry-run', ['help' => 'Count only, write nothing', 'boolean' => true])
            ->addOption('redo-bursts', ['help' => 'Delete the kill bursts first (after a change to how they are cut)', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dry = (bool)$args->getOption('dry-run');
        $conn = $this->fetchTable('Games')->getConnection();
        $total = ['flag' => 0, 'item' => 0, 'burst' => 0, 'linked' => 0];
        if ($args->getOption('redo-bursts') && !$dry) {
            $gone = $conn->execute("DELETE FROM events WHERE type = 'anticheat' AND JSON_VALUE(details, '$.kind') = 'burst'")->rowCount();
            $io->out("$gone kill bursts deleted, cut again from the logs");
        }

        // players by every pubkey they logged in with: the log's login lines
        // tell whose a line's IP is, whatever name they use today
        $byPubkey = [];
        foreach ($conn->execute("SELECT pubkey, id FROM players WHERE pubkey IS NOT NULL AND pubkey <> ''")->fetchAll('num') as [$k, $id]) {
            $byPubkey[$k] = $id;
        }
        foreach ($conn->execute('SELECT pubkey, player_id FROM player_pubkeys')->fetchAll('num') as [$k, $id]) {
            $byPubkey[$k] ??= $id;
        }

        foreach (glob(TMP . 'serverlog_*.txt') ?: [] as $file) {
            if (!preg_match('~serverlog_(\d{4})(\d{2})\d{2}_[\d.]+_(.+)\.txt$~', basename($file), $fm) || !isset(self::SERVERS[$fm[3]])) {
                continue;
            }
            $server = self::SERVERS[$fm[3]];
            $year = (int)$fm[1];
            $lastMonth = (int)$fm[2];
            $signals = new CheatSignals();
            $ipPlayer = []; // ip => player id, from the latest login on it
            $rows = [];
            $count = ['flag' => 0, 'item' => 0, 'burst' => 0, 'linked' => 0];

            // the server's games, to find the one running at a time and its players
            $games = $conn->execute(
                'SELECT id, UNIX_TIMESTAMP(started_at) s, UNIX_TIMESTAMP(COALESCE(ended_at, started_at + INTERVAL 1 HOUR)) e
                 FROM games WHERE server_name = ? AND started_at >= ? ORDER BY started_at',
                [$server, sprintf('%04d-%02d-01', $year, $lastMonth)])->fetchAll('assoc');
            $players = []; // game id => name => player id
            $gameAt = function (int $t) use ($games): ?string {
                $found = null;
                foreach ($games as $g) {
                    if ((int)$g['s'] > $t) {
                        break;
                    }
                    if ($t <= (int)$g['e']) {
                        $found = $g['id'];
                    }
                }

                return $found;
            };
            $playerIn = function (?string $gameId, string $name) use ($conn, &$players): ?string {
                if ($gameId === null) {
                    return null;
                }
                if (!isset($players[$gameId])) {
                    $players[$gameId] = [];
                    foreach ($conn->execute(
                        'SELECT s.player_id, p.name, a.alias FROM player_stats_per_game s
                         JOIN players p ON p.id = s.player_id
                         LEFT JOIN player_aliases a ON a.player_id = s.player_id
                         WHERE s.game_id = ?', [$gameId])->fetchAll('assoc') as $r) {
                        $players[$gameId][$r['name']] ??= $r['player_id'];
                        if ($r['alias'] !== null) {
                            $players[$gameId][$r['alias']] ??= $r['player_id'];
                        }
                    }
                }

                return $players[$gameId][$name] ?? null;
            };

            $fh = fopen($file, 'r');
            while (($line = fgets($fh)) !== false) {
                if (!preg_match('~^([A-Z][a-z]{2})\s+(\d{1,2}) (\d{2}:\d{2}:\d{2}) (.*)$~', rtrim($line, "\r\n"), $m)) {
                    continue;
                }
                $month = (int)date('n', strtotime($m[1] . ' 1 2000'));
                if ($month < $lastMonth) {
                    $year++; // the log went over new year
                }
                $lastMonth = $month;
                $rest = $m[4];
                $time = strtotime(sprintf('%04d-%02d-%02d %s', $year, $month, $m[2], $m[3]));

                if (str_starts_with($rest, 'Game start: ')) {
                    $signals->reset();
                    continue;
                }
                if ($signals->notePing($rest)) {
                    continue;
                }
                if (preg_match('~^\[([0-9a-f:\.]+)\]\s+\S+\s+logged in \([^)]*\),\s*AC:\s*\d+\|\d+,\s*pubkey:\s*([0-9a-f]{64})$~i', $rest, $lm)) {
                    $ipPlayer[$lm[1]] = $byPubkey[$lm[2]] ?? null;
                    continue;
                }
                $signal = $signals->farPickup($rest, $time);
                if (!$signal && preg_match(self::KILL_REGEX, $rest, $km)
                    && !str_starts_with(strtolower($km[4]), 'their teammate ')
                    && !in_array(strtolower(trim($km[2])), self::IGNORED_NAMES, true)) {
                    $signal = $signals->kill(trim($km[2]), $km[3], $time);
                    if ($signal) {
                        $signal['ip'] = $km[1];
                    }
                }
                if (!$signal) {
                    continue;
                }
                $gameId = $gameAt($time);
                $actorId = in_array(strtolower($signal['name']), self::IGNORED_NAMES, true) ? null
                    : (($ipPlayer[$signal['ip'] ?? ''] ?? null) ?? $playerIn($gameId, $signal['name']));
                // keyed by hash: a burst going on replaces its earlier state
                $rows[CheatSignals::hash($server, $signal)] = [$gameId, date('Y-m-d H:i:s', $time), CheatSignals::hash($server, $signal),
                    'anticheat', $actorId, json_encode($signal['details'], JSON_UNESCAPED_UNICODE)];
            }
            fclose($fh);
            foreach ($rows as $row) {
                $count[json_decode($row[5], true)['kind']]++;
                $count['linked'] += $row[4] ? 1 : 0;
            }

            if (!$dry) {
                foreach (array_chunk(array_values($rows), 300) as $chunk) {
                    $conn->execute(
                        'INSERT INTO events (game_id, event_time, event_hash, type, actor_id, details) VALUES '
                        . implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?)'))
                        . ' ON DUPLICATE KEY UPDATE game_id = COALESCE(VALUES(game_id), game_id),'
                        . ' actor_id = COALESCE(VALUES(actor_id), actor_id), details = VALUES(details)',
                        array_merge(...$chunk));
                }
            }
            $io->out(sprintf('%s: %d flag, %d item, %d bursts (%d with a player)', basename($file),
                $count['flag'], $count['item'], $count['burst'], $count['linked']));
            foreach ($count as $k => $v) {
                $total[$k] += $v;
            }
        }

        $io->out(sprintf('%s%d flag touches, %d pickups, %d bursts, %d linked to a player%s',
            $dry ? '[dry run] ' : '', $total['flag'], $total['item'], $total['burst'], $total['linked'], $dry ? '' : ' - saved'));

        return self::CODE_SUCCESS;
    }
}
