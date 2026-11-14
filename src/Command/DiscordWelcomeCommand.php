<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\QrHunt;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Client;

/**
 * The start-here message of the cubeLadder Discord: what the ladder is, the
 * servers, points, fair play, and the QR hunt with four pictures and a live
 * line of the leading hunters. Posted through the channel's webhook
 * (Ladder.discord.welcome.webhook, from DISCORD_WELCOME_WEBHOOK); running it
 * again edits that same message, so the text can be changed here and re-run.
 * process_logs.sh runs it with --if-changed after every import, which edits
 * the message only when the hunters line (or anything else) has changed.
 *
 *   bin/cake discord_welcome               post / update the message
 *   bin/cake discord_welcome --if-changed  update only when the content changed
 *   bin/cake discord_welcome --new         post a fresh one (--delete-old removes the old one, --ping @everyone)
 *   bin/cake discord_welcome --print       show the payload, send nothing
 */
class DiscordWelcomeCommand extends Command
{
    /** Server name prefixes shown as one group ("Vortex CTF: USA West, Brazil, …") */
    private const GROUPS = ['[aCKa] ' => 'aCKa', 'Vortex CTF ' => 'Vortex CTF'];

    public static function defaultName(): string
    {
        return 'discord_welcome';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Post / update the start-here message in the Discord')
            ->addOption('new', ['help' => 'Post a new message instead of editing the old one', 'boolean' => true])
            ->addOption('if-changed', ['help' => 'Edit only when the content differs from the last send', 'boolean' => true])
            ->addOption('ping', ['help' => 'With --new: mention @everyone (edits never ping again)', 'boolean' => true])
            ->addOption('delete-old', ['help' => 'With --new: delete the previous message first', 'boolean' => true])
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
        $body = (string)json_encode($payload);
        $hash = md5($body);
        if ($args->getOption('if-changed') && !$args->getOption('new') && ($state['hash'] ?? null) === $hash) {
            $io->verbose('Welcome message unchanged');

            return self::CODE_SUCCESS;
        }

        $messageId = $args->getOption('new') ? null : ($state['message_id'] ?? null);
        $http = new Client(['timeout' => 15]);
        if ($args->getOption('new') && $args->getOption('delete-old') && !empty($state['message_id'])) {
            $res = $http->delete($webhook . '/messages/' . $state['message_id']);
            $io->out('Deleted old welcome message ' . $state['message_id'] . ': HTTP ' . $res->getStatusCode());
        }

        if ($messageId !== null) {
            $res = $http->patch($webhook . '/messages/' . $messageId . '?with_components=true', $body, ['type' => 'json']);
            if ($res->isOk()) {
                file_put_contents($statePath, json_encode(['hash' => $hash] + $state));
                $io->out('Updated welcome message ' . $messageId);

                return self::CODE_SUCCESS;
            }
            if ($res->getStatusCode() !== 404) {
                $io->err('Discord edit failed: HTTP ' . $res->getStatusCode() . ' ' . $res->getStringBody());

                return self::CODE_ERROR;
            }
        }

        if ($args->getOption('ping')) {
            $body = (string)json_encode(['content' => '@everyone', 'allowed_mentions' => ['parse' => ['everyone']]] + $payload);
        }
        $res = $http->post($webhook . '?wait=true&with_components=true', $body, ['type' => 'json']);
        if (!$res->isOk()) {
            $io->err('Discord post failed: HTTP ' . $res->getStatusCode() . ' ' . $res->getStringBody());

            return self::CODE_ERROR;
        }
        $messageId = (string)($res->getJson()['id'] ?? '');
        file_put_contents($statePath, json_encode(['message_id' => $messageId, 'posted_at' => time(), 'hash' => $hash]));
        $io->out('Posted welcome message ' . $messageId);

        return self::CODE_SUCCESS;
    }

    private function payload(): array
    {
        $site = rtrim((string)(Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');

        $welcome = [
            'title' => 'Welcome to cubeLadder 👋',
            'url' => $site,
            'color' => 0x3B82F6,
            'description' => "cubeLadder ranks AssaultCube players automatically. Just play: every frag, flag and headshot "
                . "comes straight from the game servers' logs. Nothing is entered by hand.",
            'fields' => [
                [
                    'name' => '🎮 Get on the ladder',
                    'value' => "Play on any of these servers:\n" . $this->serverList() . "\n"
                        . 'A game counts when it starts with 4+ players and is played to the end. '
                        . 'Your stats follow your AssaultCube login, so you can change your name.',
                ],
                [
                    'name' => '🏆 Points',
                    'value' => "Kill **+1** · headshot, knife, grenade **+2** · flag scored **+5** · flag stolen / returned **+2** · "
                        . "teamkill, suicide, flag lost **−1**",
                ],
                [
                    'name' => '📡 Live',
                    'value' => 'Every running game as a scoreboard in our live channel and on cubeladder.ovh/live. '
                        . 'Results, Hall of Fame changes and achievements are posted here on Discord.',
                ],
                [
                    'name' => '⚖️ Fair play',
                    'value' => "Games where flags are scored against an empty team don't count, and joining for less "
                        . 'than 3 minutes is no game played. Cheaters are taken off the ladder for good, and a new name '
                        . "doesn't help. The code is public, so anyone can check how we count.",
                ],
            ],
            'image' => ['url' => $site . '/img/brand/cubeladder-discord-banner-960x540.png'],
        ];

        $levels = [];
        foreach (QrHunt::UNLOCKS as $key => [$level, $title]) {
            $levels[] = '`' . $level . '` ' . $title . ($key === 'avatars2' ? ' + the gold crown 👑' : '');
        }
        $huntUrl = $site . '/run';
        $hunt = [
            'title' => '🔎 The QR hunt: AssaultCube in your browser',
            'url' => $huntUrl,
            'color' => 0xEAB308,
            'description' => "**10 QR codes** are hidden in three maps you can walk through in your browser at cubeladder.ovh/run: "
                . "**cl_angkorwat**, **cl_lost**, the island from Lost, and **cl_cube**, our logo as a giant cube with a pyramid inside. "
                . "Deep in cl_cube wait the Hall of Fame as a great hall and the star chamber, one star for every player. Yours is gold.\n\n"
                . "Scan a code with your phone and you level up. Every level unlocks something on your profile:\n"
                . implode(' · ', array_slice($levels, 0, 5)) . "\n" . implode(' · ', array_slice($levels, 5)) . "\n\n"
                . $this->huntLine() . "\n\n"
                . 'Also on /run: the **flag run** and **Run & Gun**, with weekly boards. Desktop with a mouse.',
            'image' => ['url' => $site . '/img/news/welcome-hall.jpg'],
            'footer' => ['text' => 'Have fun and see you on the servers!'],
        ];
        // embeds with the same url show their images as one 2x2 gallery under the first
        $gallery = array_map(fn($img) => ['url' => $huntUrl, 'image' => ['url' => $site . '/img/news/' . $img]],
            ['welcome-stars.jpg', 'welcome-lost.jpg', 'welcome-avatars.jpg']);

        $button = fn(string $label, string $url, ?string $emoji = null) => ['type' => 2, 'style' => 5, 'label' => $label, 'url' => $url]
            + ($emoji ? ['emoji' => ['name' => $emoji]] : []);

        return [
            'username' => 'cubeLadder',
            'avatar_url' => $site . '/img/brand/cubeladder-discord-icon-512.png',
            'embeds' => array_merge([$welcome, $hunt], $gallery),
            'allowed_mentions' => ['parse' => []],
            'components' => [
                ['type' => 1, 'components' => [
                    $button('Ranking', $site . '/players', '📊'),
                    $button('Hall of Fame', $site . '/players/hall_of_fame', '🏆'),
                    $button('Live', $site . '/live', '📡'),
                    $button('Play in your browser', $huntUrl, '🔎'),
                ]],
                ['type' => 1, 'components' => [
                    $button('Rules & points', $site . '/about', '❔'),
                    $button('Source code', 'https://github.com/stylebarltd/cubeladder', '💻'),
                ]],
            ],
        ];
    }

    /** The ladder servers (not the clan match ones), one line per group */
    private function serverList(): string
    {
        $groups = [];
        $others = [];
        foreach (Configure::read('Ladder.servers') ?: [] as $server) {
            if (!($server['live'] ?? true)) {
                continue;
            }
            $name = (string)$server['name'];
            foreach (self::GROUPS as $prefix => $group) {
                if (str_starts_with($name, $prefix)) {
                    $groups[$group][] = substr($name, strlen($prefix));
                    continue 2;
                }
            }
            $others[] = $name;
        }
        $lines = [];
        foreach ($groups as $group => $names) {
            $lines[] = '**' . $group . ':** ' . implode(', ', array_unique($names));
        }
        if ($others) {
            $lines[] = '**Also:** ' . implode(', ', array_unique($others));
        }

        return implode("\n", $lines);
    }

    /** Live: the leading hunters and whether the crown is taken (admins, who built the hunt, left out) */
    private function huntLine(): string
    {
        $admins = (array)Configure::read('Ladder.admins');
        $hunt = new QrHunt();
        $total = $hunt->total() ?: 10;
        $hunters = array_values(array_filter($hunt->hunters(1000), fn($h) => !in_array($h['player_id'], $admins, true)));
        if (!$hunters) {
            return '🧭 **Nobody has found a code yet.** Be the first.';
        }
        $esc = fn(string $s) => preg_replace('/([\\\\*_~`|>#\[\]()-])/', '\\\\$1', $s);
        $top = array_map(fn($h) => '**' . $esc($h['name']) . '** ' . $h['level'] . '/' . $total, array_slice($hunters, 0, 3));
        $line = '🧭 ' . count($hunters) . ' hunter' . (count($hunters) === 1 ? '' : 's') . ' so far · leading: ' . implode(', ', $top);

        $crowned = array_values(array_filter($hunters, fn($h) => $h['level'] >= $total));
        if (!$crowned) {
            return $line . "\n👑 Nobody has found all ten yet. The crown is still waiting.";
        }
        $first = $crowned;
        usort($first, fn($a, $b) => strcmp($a['last'], $b['last']));

        return $line . "\n👑 " . count($crowned) . ' found all ten. First: **' . $esc($first[0]['name']) . '**';
    }
}
