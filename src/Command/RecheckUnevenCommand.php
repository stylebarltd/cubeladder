<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\AcLogParser;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * One-off: the "uneven teams" rule of AcLogParser (a team with one player or
 * none for half of the game) applied to the games
 * imported before it, from the raw server logs still in tmp/. Such games
 * are marked inaccurate (bin/cake mark_inaccurate <id> --undo counts one
 * again).
 *
 *   bin/cake recheck_uneven --dry-run
 *   bin/cake recheck_uneven
 */
class RecheckUnevenCommand extends Command
{
    /** log file suffix => games.server_name (as in process_logs.sh) */
    private const SERVERS = [
        'Europa' => 'acka-europa', 'Custom' => 'acka-custom', 'Nostalgic' => 'acka-nostalgic',
        'Assault' => 'acka-assault', 'local#1111' => 'chobbz-banana', 'local#2222' => 'chobbz-potato',
    ];

    public static function defaultName(): string
    {
        return 'recheck_uneven';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Mark earlier team games with uneven teams inaccurate (raw logs in tmp/)')
            ->addOption('dry-run', ['help' => 'List them, change nothing', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dry = (bool)$args->getOption('dry-run');
        $Games = $this->fetchTable('Games');
        $marked = 0;
        $seen = [];

        foreach (glob(TMP . 'serverlog_*.txt') ?: [] as $file) {
            if (!preg_match('~serverlog_(\d{4})(\d{2})\d{2}_[\d.]+_(.+)\.txt$~', basename($file), $fm) || !isset(self::SERVERS[$fm[3]])) {
                continue;
            }
            $server = self::SERVERS[$fm[3]];
            $year = (int)$fm[1];
            $lastMonth = (int)$fm[2];
            $current = null; // ['start' => ts, 'blocks' => n, 'uneven' => n, 'cla' => n|null]

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
                    $current = ['start' => strtotime("$year-$month-{$m[2]} {$m[3]}"), 'blocks' => 0, 'uneven' => 0, 'cla' => null];
                    continue;
                }
                if (!$current) {
                    continue;
                }
                if (preg_match('~^Team\s+(CLA|RVSF):\s+(\d+) players~', $rest, $tm)) {
                    if ($tm[1] === 'CLA') {
                        $current['cla'] = (int)$tm[2];
                    } elseif ($current['cla'] !== null) {
                        $current['blocks']++;
                        if (AcLogParser::unevenTeams($current['cla'], (int)$tm[2])) {
                            $current['uneven']++;
                        }
                        $current['cla'] = null;
                    }
                    continue;
                }
                if (preg_match('~Game status: .*game finished~i', $rest)) {
                    if (AcLogParser::unevenGame($current['blocks'], $current['uneven'])) {
                        $game = $Games->find()->where([
                            'server_name' => $server,
                            'inaccurate' => false,
                            'started_at >=' => date('Y-m-d H:i:s', $current['start'] - 5),
                            'started_at <=' => date('Y-m-d H:i:s', $current['start'] + 5),
                        ])->first();
                        if ($game && !isset($seen[$game->id])) {
                            $seen[$game->id] = true;
                            $marked++;
                            $reason = sprintf('a team with one player or none for %d of %d minutes',
                                $current['uneven'], $current['blocks']);
                            $io->out(sprintf('%s %s %s %s: %s', $server, $game->started_at->format('Y-m-d H:i'), $game->mode, $game->id, $reason));
                            if (!$dry) {
                                $game->inaccurate = true;
                                $game->inaccurate_reason = $reason;
                                $Games->saveOrFail($game);
                            }
                        }
                    }
                    $current = null;
                }
            }
            fclose($fh);
        }

        $io->out(sprintf('%s%d games %s', $dry ? '[dry run] ' : '', $marked, $dry ? 'to mark inaccurate' : 'marked inaccurate'));

        return self::CODE_SUCCESS;
    }
}
