<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\HallOfFameService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Http\Client;
use Throwable;

/**
 * Hall of Fame news in the Discord achievements channel (after every
 * import, process_logs.sh): compares the top 10 of every category with the
 * last run (tmp/discord_hof.json) and posts one message per changed
 * category - new entries, players who beat their own record, who moved and
 * who dropped out. The first run only saves the current lists.
 *
 *   bin/cake discord_hof              post what changed
 *   bin/cake discord_hof --dry-run    print it, post and save nothing
 */
class DiscordHofCommand extends Command
{
    /** Category key (HallOfFameService::freshTopLists) => emoji, title, unit */
    private const CATEGORIES = [
        'kills' => ['💀', 'Most Kills', 'kills'],
        'headshot' => ['🎯', 'Most Headshots', 'headshots'],
        'flags' => ['🚩', 'Most Flags scored', 'flags'],
        'streak' => ['🔥', 'Longest Streak', 'kills in a row'],
        'slashed' => ['🔪', 'Most Slashes', 'slashes'],
        'gibbed' => ['💣', 'Most Gibbed', 'gibs'],
        'helper' => ['🤝', 'Flag Helper', 'steals + returns'],
    ];

    public static function defaultName(): string
    {
        return 'discord_hof';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Post Hall of Fame changes (top 10 per category) to the Discord achievements channel')
            ->addOption('dry-run', ['boolean' => true, 'help' => 'Print the changes, post and save nothing']);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dryRun = (bool)$args->getOption('dry-run');
        $statePath = TMP . 'discord_hof.json';
        $before = is_file($statePath) ? (json_decode((string)file_get_contents($statePath), true) ?: null) : null;

        $now = [];
        foreach ((new HallOfFameService())->freshTopLists() as $key => $list) {
            foreach (array_values($list) as $i => $row) {
                $now[$key][] = [
                    'player_id' => (string)$row->player_id,
                    'name' => (string)$row->name,
                    'value' => (int)$row->value,
                    'game_id' => (string)$row->game_id,
                    'map' => (string)$row->map_name,
                    'pos' => $i + 1,
                ];
            }
        }

        if ($before === null) {
            if (!$dryRun) {
                file_put_contents($statePath, json_encode($now));
            }
            $io->out('First run: Hall of Fame saved, nothing posted');

            return self::CODE_SUCCESS;
        }

        $site = rtrim((string)(Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');
        $embeds = [];
        foreach (self::CATEGORIES as $key => [$emoji, $title, $unit]) {
            $lines = $this->changes($before[$key] ?? [], $now[$key] ?? [], $unit, $site);
            if ($lines) {
                $embeds[] = [
                    'title' => $emoji . ' Hall of Fame · ' . $title,
                    'url' => $site . '/players/hall_of_fame',
                    'description' => implode("\n", $lines),
                    'color' => 0xEAB308,
                ];
            }
        }

        if (!$embeds) {
            $io->out('No Hall of Fame changes');
        } elseif ($dryRun) {
            foreach ($embeds as $e) {
                $io->out($e['title'] . "\n" . $e['description'] . "\n");
            }
        } else {
            $this->post($embeds, $site, $io);
        }

        if (!$dryRun) {
            file_put_contents($statePath, json_encode($now));
        }

        return self::CODE_SUCCESS;
    }

    /**
     * What changed in one category's top 10, as Discord lines: new entries
     * and beaten own records first (they caused the moves), then the moves
     * and who dropped out.
     */
    private function changes(array $old, array $new, string $unit, string $site): array
    {
        $oldBy = array_column($old, null, 'player_id');
        $newBy = array_column($new, null, 'player_id');
        $news = [];
        $moves = [];

        foreach ($new as $e) {
            $was = $oldBy[$e['player_id']] ?? null;
            $game = sprintf('[%s](%s/games/view/%s)', $e['map'], $site, $e['game_id']);
            if ($was === null) {
                $news[] = sprintf('🆕 %s entered the Hall of Fame at **#%d** with **%s** %s on %s',
                    $this->link($e, $site), $e['pos'], number_format($e['value']), $unit, $game);
            } elseif ($e['value'] > $was['value']) {
                $news[] = sprintf('📈 %s beat their own record: **%s** %s (was %s) on %s - now **#%d**%s',
                    $this->link($e, $site), number_format($e['value']), $unit, number_format($was['value']), $game,
                    $e['pos'], $e['pos'] !== $was['pos'] ? ' (was #' . $was['pos'] . ')' : '');
            } elseif ($e['pos'] !== $was['pos']) {
                $moves[] = sprintf('%s %s #%d → **#%d**', $e['pos'] < $was['pos'] ? '⬆️' : '⬇️', $this->link($e, $site), $was['pos'], $e['pos']);
            }
        }
        foreach ($old as $e) {
            if (!isset($newBy[$e['player_id']])) {
                $moves[] = sprintf('👋 %s dropped out of the top 10 (was #%d)', $this->link($e, $site), $e['pos']);
            }
        }

        // a list that only shifted (e.g. a game marked inaccurate) is no news
        return $news ? array_merge($news, $moves) : [];
    }

    private function link(array $e, string $site): string
    {
        $name = strtr($e['name'], ['[' => '(', ']' => ')', '*' => "\u{2217}", '`' => "\u{2CB}"]);

        return sprintf('[%s](%s/players/view/%s)', $name, $site, $e['player_id']);
    }

    private function post(array $embeds, string $site, ConsoleIo $io): void
    {
        $webhook = Configure::read('Ladder.discord.achievements.webhook');
        if (empty($webhook)) {
            $io->out('No achievements webhook configured - not posted');

            return;
        }
        // Discord: at most 10 embeds per message
        foreach (array_chunk($embeds, 10) as $chunk) {
            try {
                $res = (new Client(['timeout' => 15]))->post($webhook . '?wait=true', (string)json_encode([
                    'username' => 'cubeLadder Hall of Fame',
                    'avatar_url' => $site . '/img/brand/cubeladder-discord-icon-512.png',
                    'embeds' => $chunk,
                    'allowed_mentions' => ['parse' => []],
                    'flags' => 4096, // SUPPRESS_NOTIFICATIONS: no sound in the achievements channel
                ]), ['type' => 'json']);
                $io->out($res->isOk() ? 'Posted ' . count($chunk) . ' Hall of Fame change(s)' : 'Discord: HTTP ' . $res->getStatusCode());
            } catch (Throwable $e) {
                $io->err('Discord: ' . $e->getMessage());
            }
        }
    }
}
