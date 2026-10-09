<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\HallOfFameService;
use Cake\Core\Configure;
use Cake\Collection\Collection;

class GamesController extends AppController
{
    public function index()
    {
        $games = $this->Games->find()
            ->contain(['Maps', 'PlayerStatsPerGame' => ['Players']])
            ->where(['Games.inaccurate' => false, 'Games.ended_at IS NOT' => null])
            ->orderByDesc('Games.ended_at')
            ->limit(50)
            ->toArray();

        $boards = [];
        foreach ($games as $game) {
            $boards[$game->id] = $this->Games->scoreboard($game);
        }

        $lastGameDateRange = $this->getGameDateRange();

        $this->set(compact('games', 'boards', 'lastGameDateRange'));
    }

    /**
     * Game with all its stats (inaccurate games keep their scoreboard too).
     */
    private function loadGame(string $id): \App\Model\Entity\Game
    {
        return $this->Games->get($id, contain: [
            'Maps',
            'PlayerStatsPerGame' => fn($q) => $q
                ->applyOptions(['includeInaccurate' => true])
                ->contain(['Players']),
        ]);
    }

    /**
     * Bare game card (no layout) for the player page's recent-games box:
     * the game like on its page, the player's row highlighted and their
     * own stats on top. /games/card/<game id>?player=<player id>
     */
    public function card(string $id)
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();

        $game = $this->loadGame($id);
        $board = $this->Games->scoreboard($game);
        $playerId = (string)$this->request->getQuery('player', '');
        $stat = null;
        foreach ($game->player_stats_per_game as $s) {
            if ((string)$s->player_id === $playerId) {
                $stat = $s;
                break;
            }
        }
        $inLast100 = in_array($game->id, $this->getLastGameIds(), true);

        $this->set(compact('game', 'board', 'stat', 'playerId', 'inLast100'));
    }

    public function view(string $id)
    {
        $game = $this->loadGame($id);
        $board = $this->Games->scoreboard($game);

        // Neighbouring (counted) games for the prev / next arrows
        $neighbour = fn(string $op, string $dir) => $this->Games->find()
            ->select(['Games.id'])
            ->where(['Games.inaccurate' => false, 'Games.ended_at IS NOT' => null, "Games.started_at $op" => $game->started_at])
            ->orderBy(['Games.started_at' => $dir])
            ->disableHydration()
            ->first()['id'] ?? null;
        $prevId = $neighbour('<', 'DESC');
        $nextId = $neighbour('>', 'ASC');

        $notQualified = $game->inaccurate ? (new HallOfFameService())->notQualified($game->id) : [];

        $this->set(compact('game', 'board', 'prevId', 'nextId', 'notQualified'));
    }

    /**
     * Link preview picture of a game page (og:image): the final scoreboard
     * as in the Discord results channel. GET /games/preview/{id}, cached on
     * disk until the scoreboard changes.
     */
    public function preview(string $id)
    {
        $game = $this->loadGame($id);
        $picture = \App\Service\GameResultPicture::describe($game, $this->Games->scoreboard($game))['picture'];

        $dir = CACHE . 'cards' . DS;
        $file = $dir . 'game-' . $id . '-' . md5((string)json_encode($picture)) . '.jpg';
        if (!is_file($file)) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            foreach (glob($dir . 'game-' . $id . '-*.jpg') ?: [] as $old) {
                @unlink($old);
            }
            file_put_contents($file, (new \App\Service\LiveScoreboardImage())->render($picture));
        }

        return $this->response
            ->withType('jpg')
            ->withHeader('Cache-Control', 'public, max-age=86400')
            ->withFile($file);
    }
}
