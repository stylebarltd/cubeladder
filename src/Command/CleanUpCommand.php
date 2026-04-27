<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\LogProcessorService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

class CleanUpCommand extends Command
{

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $PlayerStatsPerGame = $this->fetchTable('PlayerStatsPerGame');
        $result = $PlayerStatsPerGame->deleteAll([
            'total_score' => 0,
            'kills' => 0,
            'deaths' => 0,
        ]);
        $io->out("# CleanUp: $result deleted");

        return self::CODE_SUCCESS;
    }
}
