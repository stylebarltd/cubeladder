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
 * Welcome message for the cubeLadder Discord: thanks for joining and a quick
 * explanation of the ladder, with link buttons. Posted once through the
 * welcome channel's webhook (Ladder.discord.welcome.webhook, from
 * DISCORD_WELCOME_WEBHOOK); running it again edits that same message, so
 * the text can be changed here and re-run.
 *
 *   bin/cake discord_welcome          post / update the message
 *   bin/cake discord_welcome --new    post a fresh one
 *   bin/cake discord_welcome --print  show the payload, send nothing
 */
class DiscordWelcomeCommand extends Command
{
    public static function defaultName(): string
    {
        return 'discord_welcome';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Post / update the welcome message in the Discord welcome channel')
            ->addOption('new', ['help' => 'Post a new message instead of editing the old one', 'boolean' => true])
            ->addOption('print', ['help' => 'Print the payload only', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $payload = $this->payload();
        if ($args->getOption('print')) {
            $io->out((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::CODE_SUCCESS;
        }

        $webhook = Configure::read('Ladder.discord.welcome.webhook');
        if (empty($webhook)) {
            $io->err('No welcome webhook configured (Ladder.discord.welcome.webhook / DISCORD_WELCOME_WEBHOOK)');

            return self::CODE_ERROR;
        }

        $statePath = TMP . 'discord_welcome.json';
        $state = is_file($statePath) ? (json_decode((string)file_get_contents($statePath), true) ?: []) : [];
        $messageId = $args->getOption('new') ? null : ($state['message_id'] ?? null);
        $http = new Client(['timeout' => 15]);
        $body = (string)json_encode($payload);

        if ($messageId !== null) {
            $res = $http->patch($webhook . '/messages/' . $messageId . '?with_components=true', $body, ['type' => 'json']);
            if ($res->isOk()) {
                $io->out('Updated welcome message ' . $messageId);

                return self::CODE_SUCCESS;
            }
            if ($res->getStatusCode() !== 404) {
                $io->err('Discord edit failed: HTTP ' . $res->getStatusCode() . ' ' . $res->getStringBody());

                return self::CODE_ERROR;
            }
        }

        $res = $http->post($webhook . '?wait=true&with_components=true', $body, ['type' => 'json']);
        if (!$res->isOk()) {
            $io->err('Discord post failed: HTTP ' . $res->getStatusCode() . ' ' . $res->getStringBody());

            return self::CODE_ERROR;
        }
        $messageId = (string)($res->getJson()['id'] ?? '');
        file_put_contents($statePath, json_encode(['message_id' => $messageId, 'posted_at' => time()]));
        $io->out('Posted welcome message ' . $messageId);

        return self::CODE_SUCCESS;
    }

    private function payload(): array
    {
        $site = rtrim((string)(Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');

        // the ladder servers (not the clan match / inter ones)
        $servers = [];
        foreach (Configure::read('Ladder.servers') ?: [] as $server) {
            if ($server['live'] ?? true) {
                $servers[] = $server['name'];
            }
        }

        $embed = [
            'title' => 'Thanks for joining cubeLadder! 👋',
            'url' => $site,
            'color' => 0x3B82F6,
            'description' => "cubeLadder ranks AssaultCube players – automatically. Just play: every frag, flag and headshot "
                . "comes straight from the game servers' logs, nothing is entered by hand.",
            'fields' => [
                [
                    'name' => '🎮 How to get on the ladder',
                    'value' => 'Play on **' . implode('**, **', $servers) . '**. '
                        . 'A game counts when it starts with 4+ players and is played to the end. '
                        . 'Your stats follow your AssaultCube login, so name changes are fine.',
                ],
                [
                    'name' => '🏆 Points',
                    'value' => "Kill **+1** · headshot, knife, grenade **+2** · flag scored **+5** · flag stolen / returned **+2** · "
                        . "teamkill, suicide, flag lost **−1**",
                ],
                [
                    'name' => '📡 Live',
                    'value' => 'Our live channel shows every running game as a scoreboard, refreshed every 15 seconds.',
                ],
                [
                    'name' => '⚖️ Fair play',
                    'value' => "Games where flags are scored against an empty team don't count, and joining for less "
                        . 'than 3 minutes is no game played. The code is public – anyone can check how we count.',
                ],
            ],
            'image' => ['url' => $site . '/img/brand/cubeladder-discord-banner-960x540.png'],
            'footer' => ['text' => 'Have fun and see you on the servers!'],
        ];

        $button = fn(string $label, string $url, ?string $emoji = null) => ['type' => 2, 'style' => 5, 'label' => $label, 'url' => $url]
            + ($emoji ? ['emoji' => ['name' => $emoji]] : []);

        return [
            'username' => 'cubeLadder',
            'avatar_url' => $site . '/img/brand/cubeladder-discord-icon-512.png',
            'embeds' => [$embed],
            'allowed_mentions' => ['parse' => []],
            'components' => [[
                'type' => 1,
                'components' => [
                    $button('All Time Ranking', $site . '/players', '📊'),
                    $button('Hall of Fame', $site . '/players/hall_of_fame', '🏆'),
                    $button('Live servers', $site . '/live', '📡'),
                    $button('Rules & points', $site . '/about', '❔'),
                    $button('Source code', 'https://github.com/stylebarltd/cubeladder', '💻'),
                ],
            ]],
        ];
    }
}
