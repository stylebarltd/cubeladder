<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\Game;
use App\Service\GameResultPicture;
use App\Service\LiveScoreboardImage;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\FormData;
use Throwable;

/**
 * Posts the result of every newly finished game to the Discord results
 * channel (Ladder.discord.results.webhook, from DISCORD_RESULTS_WEBHOOK):
 * the final scoreboard as a picture (LiveScoreboardImage, like the live
 * channel), winner, best player and a link to the game page.
 *
 * Runs after every log import (process_logs.sh). Only games that count are
 * posted (finished, not inaccurate); games are remembered in
 * tmp/discord_results.json so each one is posted once. The first run only
 * sets the starting point – older games are not posted.
 *
 *   bin/cake discord_results                    post new results
 *   bin/cake discord_results --game=<id>        post this game (again)
 *   bin/cake discord_results --game=<id> --dry-run
 *                                               write tmp/discord_result.jpg, print the payload
 */
class DiscordResultsCommand extends Command
{
    /** At most this many results per run (a big import after a pause) */
    private const MAX_PER_RUN = 8;

    /** Handled (posted or skipped) game ids kept in the state file */
    private const KEEP_IDS = 1000;

    /** Only games finished within this window are looked at */
    private const WINDOW = '-1 day';

    public static function defaultName(): string
    {
        return 'discord_results';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Post the results of newly finished games to the Discord results channel')
            ->addOption('game', ['help' => 'Post this game id (also when posted before)'])
            ->addOption('dry-run', ['help' => 'Only render and print, send nothing', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $webhook = Configure::read('Ladder.discord.results.webhook');
        $dryRun = (bool)$args->getOption('dry-run');
        if (empty($webhook) && !$dryRun) {
            $io->err('No results webhook configured (Ladder.discord.results.webhook / DISCORD_RESULTS_WEBHOOK)');

            return self::CODE_ERROR;
        }

        $statePath = TMP . 'discord_results.json';
        $state = is_file($statePath) ? (json_decode((string)file_get_contents($statePath), true) ?: []) : [];
        $Games = $this->fetchTable('Games');
        $only = $args->getOption('game');

        if ($only === null && empty($state['since'])) {
            // first run: start from now, do not post the whole history
            $state = ['since' => date('Y-m-d H:i:s'), 'posted' => []];
            if (!$dryRun) {
                file_put_contents($statePath, json_encode($state));
            }
            $io->out('Results start from ' . $state['since']);

            return self::CODE_SUCCESS;
        }

        $query = $Games->find()
            ->contain(['Maps', 'PlayerStatsPerGame.Players'])
            ->where(['Games.ended_at IS NOT' => null, 'Games.inaccurate' => false])
            ->orderBy(['Games.ended_at' => 'ASC']);
        if ($only !== null) {
            $query->where(['Games.id' => $only]);
        } else {
            // a game is finished by a later import than the one that saw
            // it start, so look at recently changed rows, not new ones
            // ... and only games that really just ended: a script touching
            // old games (backfills, marking) must not flood the channel
            $window = date('Y-m-d H:i:s', strtotime(self::WINDOW));
            $query->where(['Games.modified >=' => max($state['since'], $window), 'Games.ended_at >=' => $window]);
            if (!empty($state['posted'])) {
                $query->where(['Games.id NOT IN' => $state['posted']]);
            }
        }

        $minPlayers = (int)(Configure::read('Ladder.discord.results.minPlayers') ?? 6);
        $http = new Client(['timeout' => 20]);
        $failed = false;
        $posted = 0;
        foreach ($query->all() as $game) {
            if ($only === null && $posted >= self::MAX_PER_RUN) {
                break; // the rest follows with the next import
            }
            // small games are not worth a result: fewer than $minPlayers
            // players who played at least MIN_MINUTES (as on the website)
            $players = count(array_filter(
                $game->player_stats_per_game ?? [],
                fn($st) => $st->minutes_played === null || $st->minutes_played >= \App\Model\Table\PlayerStatsPerGameTable::MIN_MINUTES
            ));
            if ($only === null && $players < $minPlayers) {
                $this->remember($state, $statePath, $game->id, $dryRun);
                continue;
            }
            // the final team scores follow a few log lines after "game
            // finished": when this import stopped in between, wait for the
            // next one (but not forever)
            $teamGame = in_array($game->mode, \App\Model\Table\GamesTable::TEAM_MODES, true);
            if ($only === null && $teamGame && empty($game->team_scores)
                && $game->modified && $game->modified->getTimestamp() > time() - 1800) {
                continue;
            }
            try {
                [$payload, $filename, $jpeg] = $this->message($game);
                if ($dryRun) {
                    file_put_contents(TMP . 'discord_result.jpg', $jpeg);
                    $io->out((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $io->out('picture: ' . TMP . 'discord_result.jpg');
                    continue;
                }

                $form = new FormData();
                $json = $form->newPart('payload_json', (string)json_encode($payload));
                $json->type('application/json');
                $form->add($json);
                $file = $form->newPart('files[0]', $jpeg);
                $file->filename($filename);
                $file->type('image/jpeg');
                $form->add($file);

                $res = $http->post($webhook . '?wait=true&with_components=true', (string)$form, [
                    'headers' => ['Content-Type' => $form->contentType()],
                ]);
                if (!$res->isOk()) {
                    throw new \RuntimeException('HTTP ' . $res->getStatusCode() . ' ' . $res->getStringBody());
                }
                $io->out(sprintf('Posted %s (%s on %s, %d players)', $game->id, $game->mode, $game->map->name ?? '?', $players));
                $posted++;
            } catch (Throwable $e) {
                $io->err('Game ' . $game->id . ': ' . $e->getMessage());
                $failed = true;
                continue;
            }

            if ($only === null) {
                $this->remember($state, $statePath, $game->id, $dryRun);
            }
        }

        return $failed ? self::CODE_ERROR : self::CODE_SUCCESS;
    }

    /**
     * Mark a game as handled (posted or skipped) in the state file.
     */
    private function remember(array &$state, string $statePath, string $gameId, bool $dryRun): void
    {
        $state['posted'][] = $gameId;
        $state['posted'] = array_slice($state['posted'], -self::KEEP_IDS);
        if (!$dryRun) {
            file_put_contents($statePath, json_encode($state));
        }
    }

    /**
     * Webhook payload + scoreboard picture of one game.
     *
     * @return array{0: array, 1: string, 2: string}
     */
    private function message(Game $game): array
    {
        $site = rtrim((string)(Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');
        $board = $this->fetchTable('Games')->scoreboard($game);
        // headline + scoreboard picture, shared with the game pages' link previews
        ['title' => $title, 'map' => $map, 'mode' => $mode, 'server' => $server, 'minutes' => $minutes, 'picture' => $picture]
            = GameResultPicture::describe($game, $board);
        $mapName = (string)($game->map->name ?? '');
        $flagMode = $board['flagMode'];
        $color = match ($board['winner']) {
            'CLA' => 0xDC2626,
            'RVSF' => 0x2563EB,
            default => 0x71717A,
        };
        $jpeg = (new LiveScoreboardImage())->render($picture);
        $filename = 'result-' . substr((string)$game->id, 0, 8) . '.jpg';

        // highlights
        // Anonymous (opted out of tracking): no link
        $link = fn($player) => $player->id === null
            ? '_Anonymous_'
            : sprintf('[%s](%s/players/view/%s)', $this->linkText((string)$player->name), $site, $player->id);
        $fields = [];
        if ($board['rows']) {
            $mvp = $board['rows'][0];
            $fields[] = ['name' => '⭐ Best player', 'value' => $link($mvp['player']) . ' · ' . number_format($mvp['score']) . ' points', 'inline' => true];
            $byKills = $board['rows'];
            usort($byKills, fn($a, $b) => $b['kills'] <=> $a['kills']);
            $fields[] = ['name' => '🎯 Most frags', 'value' => $link($byKills[0]['player']) . ' · ' . $byKills[0]['kills'], 'inline' => true];
            if ($flagMode) {
                $byFlags = $board['rows'];
                usort($byFlags, fn($a, $b) => $b['flags'] <=> $a['flags']);
                if ($byFlags[0]['flags'] > 0) {
                    $fields[] = ['name' => '🚩 Most flags', 'value' => $link($byFlags[0]['player']) . ' · ' . $byFlags[0]['flags'], 'inline' => true];
                }
            }
        }

        $when = $game->started_at
            ? $game->started_at->format('D j M, H:i') . ($game->ended_at ? ' – ' . $game->ended_at->format('H:i') : '')
            : '';
        $button = fn(string $label, string $url, ?string $emoji = null) => ['type' => 2, 'style' => 5, 'label' => $label, 'url' => $url]
            + ($emoji ? ['emoji' => ['name' => $emoji]] : []);

        $payload = [
            'username' => 'cubeLadder Results',
            'avatar_url' => $site . '/img/brand/cubeladder-discord-icon-512.png',
            'embeds' => [[
                'title' => '🏁 ' . $map . ($mode !== '' ? ' · ' . $mode : '') . ' — ' . $title,
                'url' => $site . '/games/view/' . $game->id,
                // plain text, as on the game page: a <t:…> timestamp shows up raw in
                // push notifications and previews
                'description' => sprintf('**%s** · %s%s', $server, $when, $minutes > 0 ? " · {$minutes} min" : ''),
                'color' => $color,
                'fields' => $fields,
                'image' => ['url' => 'attachment://' . $filename],
            ]],
            'attachments' => [['id' => 0, 'filename' => $filename]],
            'allowed_mentions' => ['parse' => []],
            'flags' => 4096, // SUPPRESS_NOTIFICATIONS: posted without a ping or sound
            'components' => [[
                'type' => 1,
                'components' => [
                    $button('Game details', $site . '/games/view/' . $game->id, '📊'),
                    $button($map . ' stats', $site . '/maps?map=' . rawurlencode($mapName), '🗺️'),
                    $button('the last 100', $site . '/players/thelast100', '🏆'),
                ],
            ]],
        ];

        return [$payload, $filename, $jpeg];
    }

    /**
     * Player name as masked-link text, as in DiscordLiveService::linkText():
     * brackets would end the link, "*" and "`" become look-alikes, "**",
     * "__", "~~", "||" are split with a zero-width space.
     */
    private function linkText(string $s): string
    {
        $s = strtr($s, ['[' => '(', ']' => ')', '*' => "\u{2217}", '`' => "\u{2CB}"]);
        if (preg_match('/\b_(?:__|[^_])+?_\b/', $s)) {
            $s = str_replace('_', "\u{2CD}", $s);
        }

        return (string)preg_replace('/([_~|])(?=\1)/', "$1\u{200B}", $s);
    }
}
