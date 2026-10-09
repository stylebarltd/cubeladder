<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\Game;
use App\Service\AcExtInfoService;
use App\Service\LiveScoreboardImage;
use App\View\Helper\LayoutHelper;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\FormData;
use Cake\View\View;
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

    /** Posted game ids kept in the state file */
    private const KEEP_IDS = 300;

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
            $query->where(['Games.modified >=' => $state['since']]);
            if (!empty($state['posted'])) {
                $query->where(['Games.id NOT IN' => $state['posted']]);
            }
            $query->limit(self::MAX_PER_RUN);
        }

        $http = new Client(['timeout' => 20]);
        $failed = false;
        foreach ($query->all() as $game) {
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
                $io->out(sprintf('Posted %s (%s on %s)', $game->id, $game->mode, $game->map->name ?? '?'));
            } catch (Throwable $e) {
                $io->err('Game ' . $game->id . ': ' . $e->getMessage());
                $failed = true;
                continue;
            }

            if ($only === null) {
                $state['posted'][] = $game->id;
                $state['posted'] = array_slice($state['posted'], -self::KEEP_IDS);
                file_put_contents($statePath, json_encode($state));
            }
        }

        return $failed ? self::CODE_ERROR : self::CODE_SUCCESS;
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
        $layout = new LayoutHelper(new View());
        $mapName = (string)($game->map->name ?? '');
        $map = $layout->cleanMapName($mapName) ?: $mapName;
        $mode = strtoupper((string)$game->mode);
        $server = Configure::read('Ladder.servers.' . $game->server_name . '.name') ?: (string)$game->server_name;
        $minutes = (int)($game->duration_minutes ?? 0);
        $teamGame = $board['teams'] !== null;
        $flagMode = $board['flagMode'];

        // team scores of the whole game (incl. untracked players) from the log
        $score = fn(string $team) => (int)($flagMode
            ? ($board['teams'][$team]['score']['flags'] ?? 0)
            : ($board['teams'][$team]['score']['frags'] ?? 0));

        if ($teamGame) {
            $title = $board['winner'] !== null
                ? sprintf('%s wins %d : %d', $board['winner'], $score($board['winner']), $score($board['winner'] === 'CLA' ? 'RVSF' : 'CLA'))
                : sprintf('Draw %d : %d', $score('CLA'), $score('RVSF'));
        } else {
            $title = !empty($board['rows']) ? $board['rows'][0]['player']->name . ' wins' : 'Game over';
        }
        $color = match ($board['winner']) {
            'CLA' => 0xDC2626,
            'RVSF' => 0x2563EB,
            default => 0x71717A,
        };

        // the picture: the live scoreboard, final
        $modeId = array_search(strtolower((string)$game->mode), AcExtInfoService::MODE_CODES, true);
        $players = [];
        foreach ($board['rows'] as $row) {
            $players[] = [
                'name' => $row['player']->name,
                'team' => (string)$row['team'],
                'frags' => $row['kills'],
                'flags' => $row['flags'],
                'deaths' => $row['deaths'],
                'is_spectator' => false,
            ];
        }
        $picture = [
            'map' => $mapName,
            'mode' => $modeId === false ? -1 : $modeId,
            'mode_name' => $mode,
            'name' => $server,
            'minremain' => null,
            'players' => $players,
            'meta' => 'FINAL  ·  ' . $mode . '  ·  ' . $server . ($minutes > 0 ? "  ·  {$minutes} min" : ''),
            'badge' => [(string)count($players), 'players'],
            'host' => null,
            'footer' => 'cubeladder.ovh',
        ];
        if ($teamGame) {
            $picture['team_scores'] = ['CLA' => $score('CLA'), 'RVSF' => $score('RVSF')];
            $picture['winner'] = $board['winner'];
        }
        $jpeg = (new LiveScoreboardImage())->render($picture);
        $filename = 'result-' . substr((string)$game->id, 0, 8) . '.jpg';

        // highlights
        $link = fn($player) => sprintf('[%s](%s/players/view/%s)', $this->linkText((string)$player->name), $site, $player->id);
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

        $ended = $game->ended_at ? $game->ended_at->getTimestamp() : time();
        $button = fn(string $label, string $url, ?string $emoji = null) => ['type' => 2, 'style' => 5, 'label' => $label, 'url' => $url]
            + ($emoji ? ['emoji' => ['name' => $emoji]] : []);

        $payload = [
            'username' => 'cubeLadder Results',
            'avatar_url' => $site . '/img/brand/cubeladder-discord-icon-512.png',
            'embeds' => [[
                'title' => sprintf('🏁 %s · %s — %s', $map, $mode, $title),
                'url' => $site . '/games/view/' . $game->id,
                'description' => sprintf('**%s** · <t:%d:f>%s', $server, $ended, $minutes > 0 ? " · {$minutes} min" : ''),
                'color' => $color,
                'fields' => $fields,
                'image' => ['url' => 'attachment://' . $filename],
            ]],
            'attachments' => [['id' => 0, 'filename' => $filename]],
            'allowed_mentions' => ['parse' => []],
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
