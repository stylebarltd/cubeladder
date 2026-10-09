<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Game;
use App\View\Helper\LayoutHelper;
use Cake\Core\Configure;
use Cake\View\View;

/**
 * The final result of a game - headline ("RVSF wins 5 : 3") and the
 * scoreboard picture (LiveScoreboardImage) - shared by the Discord results
 * channel (DiscordResultsCommand) and the link previews of game pages
 * (GamesController::card).
 */
class GameResultPicture
{
    /**
     * @param array $board GamesTable::scoreboard()
     * @return array{title: string, map: string, mode: string, server: string, minutes: int, picture: array}
     */
    public static function describe(Game $game, array $board): array
    {
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
            $winner = $board['winner'];
            $loser = $winner === 'CLA' ? 'RVSF' : 'CLA';
            $title = match (true) {
                $winner === null => sprintf('Draw %d : %d', $score('CLA'), $score('RVSF')),
                // equal flags: the website decides on frags
                $score($winner) === $score($loser) => sprintf('%s wins on frags · %d : %d', $winner, $score($winner), $score($loser)),
                default => sprintf('%s wins %d : %d', $winner, $score($winner), $score($loser)),
            };
        } else {
            $title = !empty($board['rows']) ? $board['rows'][0]['player']->name . ' wins' : 'Game over';
        }

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
            'meta' => implode('  ·  ', array_filter([$game->ended_at ? 'FINAL' : '', $mode, $server, $minutes > 0 ? "{$minutes} min" : ''])),
            'badge' => [(string)count($players), 'players'],
            'host' => null,
            'footer' => 'cubeladder.ovh',
        ];
        if ($teamGame) {
            $picture['team_scores'] = ['CLA' => $score('CLA'), 'RVSF' => $score('RVSF')];
            // players who opted out of tracking are not drawn, but counted
            $picture['hidden'] = ['CLA' => (int)($board['teams']['CLA']['hidden'] ?? 0), 'RVSF' => (int)($board['teams']['RVSF']['hidden'] ?? 0)];
            $picture['winner'] = $board['winner'];
        }

        return compact('title', 'map', 'mode', 'server', 'minutes', 'picture');
    }
}
