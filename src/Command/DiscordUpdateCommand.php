<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Http\Client;

/**
 * Site updates for the Discord updates channel, pushed by hand: posts a
 * "What's new" entry of config/changelog.php through the updates webhook
 * (Ladder.discord.updates.webhook, from DISCORD_UPDATES_WEBHOOK) as a silent
 * message (no notification sound). Remembers what it posted in
 * tmp/discord_updates.json, so an entry goes out only once.
 *
 *   bin/cake discord_update            the newest entry
 *   bin/cake discord_update --entry 2  the 2nd newest
 *   bin/cake discord_update --print    show the payload, send nothing
 *   bin/cake discord_update --force    post again although it was posted
 */
class DiscordUpdateCommand extends Command
{
    public static function defaultName(): string
    {
        return 'discord_update';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Post a changelog entry to the Discord updates channel (silent)')
            ->addOption('entry', ['help' => 'Which entry, 1 = the newest', 'default' => '1'])
            ->addOption('print', ['help' => 'Print the payload only', 'boolean' => true])
            ->addOption('force', ['help' => 'Post even when this entry was posted before', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        Configure::load('changelog');
        $entries = Configure::read('Changelog', []);
        $entry = $entries[max(1, (int)$args->getOption('entry')) - 1] ?? null;
        if (!$entry) {
            $io->err('No such changelog entry');

            return self::CODE_ERROR;
        }

        $site = rtrim((string)(Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');
        $items = array_map(fn($i) => '• ' . $i, $entry['items'] ?? []);
        $payload = [
            'username' => 'cubeLadder Updates',
            'avatar_url' => $site . '/img/brand/cubeladder-discord-icon-512.png',
            'embeds' => [[
                'title' => $entry['title'],
                'url' => $site,
                // Discord allows 4096 characters
                'description' => mb_substr(implode("\n", $items), 0, 4000),
                'color' => 0x3B82F6,
                'footer' => ['text' => 'cubeladder.ovh · ' . date('j M Y', strtotime($entry['date']))],
            ]],
            'flags' => 4096, // SUPPRESS_NOTIFICATIONS: no ping, no sound
            'allowed_mentions' => ['parse' => []],
        ];
        if ($args->getOption('print')) {
            $io->out((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::CODE_SUCCESS;
        }

        $webhook = Configure::read('Ladder.discord.updates.webhook');
        if (empty($webhook)) {
            $io->err('No updates webhook configured (Ladder.discord.updates.webhook / DISCORD_UPDATES_WEBHOOK)');

            return self::CODE_ERROR;
        }

        $statePath = TMP . 'discord_updates.json';
        $state = is_file($statePath) ? (json_decode((string)file_get_contents($statePath), true) ?: []) : [];
        $key = md5($entry['date'] . $entry['title'] . implode('', $entry['items'] ?? []));
        if (isset($state[$key]) && !$args->getOption('force')) {
            $io->err('This entry was posted on ' . $state[$key] . ' - use --force to post it again');

            return self::CODE_ERROR;
        }

        $res = (new Client(['timeout' => 15]))->post($webhook . '?wait=true', (string)json_encode($payload), ['type' => 'json']);
        if (!$res->isOk()) {
            $io->err('Discord: HTTP ' . $res->getStatusCode() . ' ' . $res->getStringBody());

            return self::CODE_ERROR;
        }
        $state[$key] = date('Y-m-d H:i');
        file_put_contents($statePath, (string)json_encode($state, JSON_PRETTY_PRINT));
        $io->out('Posted "' . $entry['title'] . '"');

        return self::CODE_SUCCESS;
    }
}
