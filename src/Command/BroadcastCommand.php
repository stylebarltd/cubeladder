<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use DateTimeImmutable;

/**
 * Sends one inbox message (from the first admin) to every player who played
 * in the last N days. Players who already have the exact same message are
 * skipped, so running it twice sends nothing new.
 *
 *   bin/cake broadcast discord --dry-run
 *   bin/cake broadcast discord --days 90
 *   bin/cake broadcast --file announcement.txt
 */
class BroadcastCommand extends Command
{
    /** Ready-made messages, by name. */
    private const MESSAGES = [
        'discord' => "**Join us on Discord!**\n\n"
            . "cubeLadder has its own Discord server: ladder games and inters live - who is playing, map, score and flags, "
            . "updated every few seconds, with a ping when a server fills up.\n\n"
            . "Come say hi and find a game: https://discord.gg/tVX7FKCtK3",
    ];

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Send one inbox message to every recently active player.')
            ->addArgument('message', ['help' => 'Ready-made message: ' . implode(', ', array_keys(self::MESSAGES))])
            ->addOption('file', ['help' => 'Send the text of this file instead (**bold** and line breaks allowed)'])
            ->addOption('days', ['help' => 'Only players who played in the last N days (0 = everybody)', 'default' => '90'])
            ->addOption('dry-run', ['help' => 'Only count the receivers', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $file = $args->getOption('file');
        $name = $args->getArgument('message');
        if ($file) {
            $body = is_file((string)$file) ? trim((string)file_get_contents((string)$file)) : '';
        } else {
            $body = self::MESSAGES[$name] ?? '';
        }
        if ($body === '') {
            $io->error('Give a ready-made message (' . implode(', ', array_keys(self::MESSAGES)) . ') or --file with the text.');

            return static::CODE_ERROR;
        }

        $sender = ((array)Configure::read('Ladder.admins'))[0] ?? null;
        $days = (int)$args->getOption('days');

        $receivers = $this->fetchTable('Players')->find()
            ->select(['Players.id'])
            ->distinct(['Players.id']);
        if ($days > 0) {
            $since = (new DateTimeImmutable("-{$days} days"))->format('Y-m-d H:i:s');
            $receivers
                ->innerJoin(['s' => 'player_stats_per_game'], ['s.player_id = Players.id'])
                ->innerJoin(['g' => 'games'], ['g.id = s.game_id', 'g.created >=' => $since]);
        }
        $receiverIds = $receivers->all()->extract('id')->toList();

        $Messages = $this->fetchTable('Messages');
        $alreadySent = $Messages->find()
            ->select(['receiver_id'])
            ->where(['body' => $body])
            ->all()->extract('receiver_id')->toList();
        $todo = array_values(array_diff($receiverIds, $alreadySent, [$sender]));

        $io->out(sprintf(
            '%d players %s, %d already have this message -> %d to send.',
            count($receiverIds),
            $days > 0 ? "played in the last {$days} days" : 'in total',
            count($receiverIds) - count(array_diff($receiverIds, $alreadySent)),
            count($todo)
        ));
        $io->out('---' . "\n" . $body . "\n" . '---');

        if ($args->getOption('dry-run') || !$todo) {
            return static::CODE_SUCCESS;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach (array_chunk($todo, 500) as $chunk) {
            $insert = $Messages->insertQuery()->insert(['sender_id', 'receiver_id', 'body', 'is_read', 'created']);
            foreach ($chunk as $receiverId) {
                $insert->values([
                    'sender_id' => $sender,
                    'receiver_id' => $receiverId,
                    'body' => $body,
                    'is_read' => 0,
                    'created' => $now,
                ]);
            }
            $insert->execute();
        }
        $io->success(sprintf('Sent to %d players.', count($todo)));

        return static::CODE_SUCCESS;
    }
}
