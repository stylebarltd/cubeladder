<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\DuplicateFinder;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

/**
 * Asks players with likely duplicate profiles (DuplicateFinder) by message:
 * one message per player, on the profile they played last, with a confirm
 * link per old profile (PlayersController::claim). Each pair is stored as
 * an 'asked' merge request, so nobody is asked twice.
 *
 *   bin/cake ask_duplicates --dry-run      show who would get what
 *   bin/cake ask_duplicates --min-score 7  only the stronger matches
 */
class AskDuplicatesCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Message players about their likely duplicate profiles')
            ->addOption('min-score', ['default' => '4', 'help' => 'Lowest match score to ask about'])
            ->addOption('dry-run', ['boolean' => true, 'help' => 'Show the messages, send nothing']);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dry = (bool)$args->getOption('dry-run');
        $sender = ((array)Configure::read('Ladder.admins'))[0] ?? null;
        $site = rtrim((string)(Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');
        $min = (int)$args->getOption('min-score');

        $candidates = array_values(array_filter((new DuplicateFinder())->candidates(1000), fn($c) => $c['score'] >= $min));
        $clusters = DuplicateFinder::clusters($candidates);
        $conn = ConnectionManager::get('default');

        foreach ($clusters as ['to' => $to, 'olds' => $olds]) {
            $io->out(sprintf('%s <- %s', $to['name'], implode(', ', array_column($olds, 'name'))));
            if ($dry) {
                continue;
            }
            $conn->transactional(function ($c) use ($sender, $to, $olds, $site) {
                $c->execute('INSERT INTO messages (sender_id, receiver_id, body, is_read, created) VALUES (?, ?, ?, 0, NOW())',
                    [$sender, $to['id'], DuplicateFinder::askText($olds, $site)]);
                foreach ($olds as $old) {
                    $c->execute("INSERT INTO merge_requests (from_id, to_id, status, created) VALUES (?, ?, 'asked', NOW())",
                        [$old['id'], $to['id']]);
                }
            });
        }
        $io->out(sprintf('%d players %s about %d old profiles (%d pairs, score >= %d)', count($clusters),
            $dry ? 'would be asked' : 'asked', array_sum(array_map(fn($c) => count($c['olds']), $clusters)), count($candidates), $min));

        return self::CODE_SUCCESS;
    }
}
