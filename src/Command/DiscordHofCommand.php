<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\HallOfFameImage;
use App\Service\HallOfFameService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\FormData;
use Throwable;

/**
 * Hall of Fame news in the Discord achievements channel (after every
 * import, process_logs.sh): compares the top 10 of every category with the
 * last run (tmp/discord_hof.json) and posts one embed per changed category
 * - new entries, players who beat their own record, who moved and who
 * dropped out, each with their value - with a picture of the new top 10 on
 * a random player photo (HallOfFameImage). The first run only saves the
 * current lists.
 *
 *   bin/cake discord_hof              post what changed
 *   bin/cake discord_hof --dry-run    print it and write tmp/discord_hof_<category>.jpg,
 *                                     post and save nothing
 */
class DiscordHofCommand extends Command
{
    /** Category key (HallOfFameService::freshTopLists) => emoji, title, unit, picture icon */
    private const CATEGORIES = [
        'kills' => ['💀', 'Most Kills', 'kills', 'skull'],
        'headshot' => ['🎯', 'Most Headshots', 'headshots', 'crosshairs'],
        'flags' => ['🚩', 'Most Flags scored', 'flags', 'flag'],
        'streak' => ['🔥', 'Longest Streak', 'kills in a row', 'fire'],
        'slashed' => ['🔪', 'Most Slashes', 'slashes', 'png:knife'],
        'gibbed' => ['💣', 'Most Gibbed', 'gibs', 'png:grenade'],
        'helper' => ['🤝', 'Flag Helper', 'steals + returns', 'handshake'],
    ];

    public static function defaultName(): string
    {
        return 'discord_hof';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Post Hall of Fame changes (top 10 per category) to the Discord achievements channel')
            ->addOption('dry-run', ['boolean' => true, 'help' => 'Print the changes and write the pictures to tmp/, post and save nothing']);
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
                    'steals' => (int)$row->steals,
                    'returns' => (int)$row->returns,
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
        $posts = [];
        foreach (self::CATEGORIES as $key => [$emoji, $title, $unit, $icon]) {
            $change = $this->changes($before[$key] ?? [], $now[$key] ?? [], $key, $unit, $site);
            if (!$change) {
                continue;
            }
            $filename = 'hall_of_fame_' . $key . '.jpg';
            $jpeg = (new HallOfFameImage())->render([
                'title' => $title,
                'subtitle' => 'Best single game since ' . date('F Y', strtotime(\App\Controller\PlayersController::HOF_SINCE)) . ' · ' . $unit,
                'icon' => $icon,
                'headline' => $change['headline'],
                'background' => HallOfFameImage::randomBackground(),
                'rows' => array_map(fn($e) => [$e['pos'], $e['name'], number_format($e['value']), $change['marks'][$e['player_id']] ?? ''], $now[$key]),
            ]);
            $posts[] = [
                'embed' => [
                    'title' => $emoji . ' Hall of Fame · ' . $title,
                    'url' => $site . '/players/hall_of_fame',
                    'description' => implode("\n", $change['lines']),
                    'color' => 0xEAB308,
                    'image' => ['url' => 'attachment://' . $filename],
                ],
                'filename' => $filename,
                'jpeg' => $jpeg,
            ];
        }

        if (!$posts) {
            $io->out('No Hall of Fame changes');
        } elseif ($dryRun) {
            foreach ($posts as $p) {
                file_put_contents(TMP . 'discord_hof_' . substr($p['filename'], 13), $p['jpeg']);
                $io->out($p['embed']['title'] . "\n" . $p['embed']['description'] . "\npicture: tmp/discord_hof_" . substr($p['filename'], 13) . "\n");
            }
        } else {
            $this->post($posts, $site, $io);
        }

        if (!$dryRun) {
            file_put_contents($statePath, json_encode($now));
        }

        return self::CODE_SUCCESS;
    }

    /**
     * What changed in one category's top 10: Discord lines (new entries and
     * beaten own records first - they caused the moves - then the moves and
     * who dropped out, each with the value), the marks for the picture
     * (player_id => NEW / RECORD / ▲2 / ▼1) and the first news as headline.
     * Null when nothing new happened.
     */
    private function changes(array $old, array $new, string $key, string $unit, string $site): ?array
    {
        $oldBy = array_column($old, null, 'player_id');
        $newBy = array_column($new, null, 'player_id');
        $news = $moves = $marks = [];
        $headline = null;

        foreach ($new as $e) {
            $was = $oldBy[$e['player_id']] ?? null;
            $game = sprintf('[%s](%s/games/view/%s)', $e['map'], $site, $e['game_id']);
            $value = $this->value($e, $key, $unit);
            if ($was === null) {
                $news[] = sprintf('🆕 %s entered the Hall of Fame at **#%d** with %s on %s', $this->link($e, $site), $e['pos'], $value, $game);
                $marks[$e['player_id']] = 'NEW';
                $headline ??= sprintf('%s enters at #%d · %s %s on %s', $e['name'], $e['pos'], number_format($e['value']), $unit, $e['map']);
            } elseif ($e['value'] > $was['value']) {
                $news[] = sprintf('📈 %s beat their own record: %s (was %s) on %s - now **#%d**%s',
                    $this->link($e, $site), $value, number_format($was['value']), $game,
                    $e['pos'], $e['pos'] !== $was['pos'] ? ' (was #' . $was['pos'] . ')' : '');
                $marks[$e['player_id']] = 'RECORD';
                $headline ??= sprintf('%s beats their own record · %s %s on %s · now #%d', $e['name'], number_format($e['value']), $unit, $e['map'], $e['pos']);
            } elseif ($e['pos'] !== $was['pos']) {
                $moves[] = sprintf('%s %s #%d → **#%d** · %s %s', $e['pos'] < $was['pos'] ? '⬆️' : '⬇️', $this->link($e, $site),
                    $was['pos'], $e['pos'], number_format($e['value']), $unit);
                $marks[$e['player_id']] = ($e['pos'] < $was['pos'] ? '▲' : '▼') . abs($was['pos'] - $e['pos']);
            }
        }
        foreach ($old as $e) {
            if (!isset($newBy[$e['player_id']])) {
                $moves[] = sprintf('👋 %s dropped out of the top 10 (was #%d, %s %s)', $this->link($e, $site), $e['pos'], number_format($e['value']), $unit);
            }
        }

        // a list that only shifted (e.g. a game marked inaccurate) is no news
        return $news ? ['lines' => array_merge($news, $moves), 'marks' => $marks, 'headline' => $headline] : null;
    }

    /** "**21** steals + returns (12 steals, 9 returns)" - the parts only for Flag Helper */
    private function value(array $e, string $key, string $unit): string
    {
        $text = sprintf('**%s** %s', number_format($e['value']), $unit);
        if ($key === 'helper' && ($e['steals'] ?? 0) + ($e['returns'] ?? 0) === $e['value']) {
            $text .= sprintf(' (%d steal%s, %d return%s)', $e['steals'], $e['steals'] === 1 ? '' : 's', $e['returns'], $e['returns'] === 1 ? '' : 's');
        }

        return $text;
    }

    private function link(array $e, string $site): string
    {
        $name = strtr($e['name'], ['[' => '(', ']' => ')', '*' => "\u{2217}", '`' => "\u{2CB}"]);

        return sprintf('[%s](%s/players/view/%s)', $name, $site, $e['player_id']);
    }

    /** One message per up to 10 categories, each embed with its picture */
    private function post(array $posts, string $site, ConsoleIo $io): void
    {
        $webhook = Configure::read('Ladder.discord.achievements.webhook');
        if (empty($webhook)) {
            $io->out('No achievements webhook configured - not posted');

            return;
        }
        foreach (array_chunk($posts, 10) as $chunk) {
            $form = new FormData();
            $json = $form->newPart('payload_json', (string)json_encode([
                'username' => 'cubeLadder Hall of Fame',
                'avatar_url' => $site . '/img/brand/cubeladder-discord-icon-512.png',
                'embeds' => array_column($chunk, 'embed'),
                'attachments' => array_map(fn($i, $p) => ['id' => $i, 'filename' => $p['filename']], array_keys($chunk), $chunk),
                'allowed_mentions' => ['parse' => []],
                'flags' => 4096, // SUPPRESS_NOTIFICATIONS: no sound in the achievements channel
            ]));
            $json->type('application/json');
            $form->add($json);
            foreach ($chunk as $i => $p) {
                $file = $form->newPart("files[$i]", $p['jpeg']);
                $file->filename($p['filename']);
                $file->type('image/jpeg');
                $form->add($file);
            }
            try {
                $res = (new Client(['timeout' => 30]))->post($webhook . '?wait=true', (string)$form, [
                    'headers' => ['Content-Type' => $form->contentType()],
                ]);
                $io->out($res->isOk() ? 'Posted ' . count($chunk) . ' Hall of Fame change(s)' : 'Discord: HTTP ' . $res->getStatusCode() . ' ' . $res->getStringBody());
            } catch (Throwable $e) {
                $io->err('Discord: ' . $e->getMessage());
            }
        }
    }
}
