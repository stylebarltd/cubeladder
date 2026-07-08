<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\LogProcessorService;
use Cake\Cache\Cache;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

class ProcessLogsCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->addArgument('server', ['help' => 'Server name'])
            ->addArgument('file_identifier', ['help' => 'Remote log file path'])
            ->addArgument('local', [
                'help' => 'Local log file to parse instead of STDIN',
                //'default' => null,
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $server        = $args->getArgument('server');
        $fileId        = $args->getArgument('file_identifier');
        $local        = $args->getArgument('local');
        $io->out("### START " . date('Y-m-d H:i:s') . " ###");
        $io->out("# Server: $server");
        $io->out("# Logfile: $fileId");

        // No injection → instantiate service manually
        $logProcessor = new LogProcessorService();

        $logProcessor->processStream(
            $server,
            $fileId,
            $local
        );

        // New games/stats just landed, so the cached ranking aggregates
        // (Players::index) are stale — drop them so the next request rebuilds.
        Cache::clear('rankings');

        return self::CODE_SUCCESS;
    }
}
