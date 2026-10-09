<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Mark a game as inaccurate (its stats are dropped from every ranking) or,
 * with --undo, count it again. Not a cheating verdict - just untrustworthy
 * data, e.g. everyone else left and flags were scored against an empty team.
 *
 *   bin/cake mark_inaccurate <game-id> "reason"
 *   bin/cake mark_inaccurate <game-id> --undo
 */
class MarkInaccurateCommand extends Command
{
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Mark a game as inaccurate data so it no longer counts anywhere.')
            ->addArgument('game_id', ['required' => true])
            ->addArgument('reason', ['required' => false])
            ->addOption('undo', ['boolean' => true, 'help' => 'Count the game again']);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $Games = $this->fetchTable('Games');
        $game = $Games->find()->where(['id' => $args->getArgument('game_id')])->first();
        if (!$game) {
            $io->error('Game not found.');

            return self::CODE_ERROR;
        }

        $undo = (bool)$args->getOption('undo');
        $game->inaccurate = !$undo;
        $game->inaccurate_reason = $undo ? null : ($args->getArgument('reason') ?: 'Marked by hand');
        $Games->saveOrFail($game);

        $io->out(sprintf(
            '# Game %s (%s, %s): %s',
            $game->id,
            $game->server_name,
            $game->started_at?->format('Y-m-d H:i'),
            $undo ? 'counts again' : 'marked inaccurate - ' . $game->inaccurate_reason
        ));

        return self::CODE_SUCCESS;
    }
}
