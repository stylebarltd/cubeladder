<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\HallOfFameService;
use Cake\Core\Configure;
use Cake\Collection\Collection;

class GamesController extends AppController
{
    /** Games per page of the list */
    public const PER_PAGE = 50;

    /**
     * The games list, last played first (?page=n): map, mode, server, result
     * and the best player; each row opens the game page.
     */
    public function index()
    {
        $where = ['Games.inaccurate' => false, 'Games.ended_at IS NOT' => null];
        $total = $this->Games->find()->where($where)->count();
        $pages = max(1, (int)ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int)$this->request->getQuery('page', 1)));

        $games = $this->Games->find()
            ->contain(['Maps', 'PlayerStatsPerGame' => ['Players']])
            ->where($where)
            ->orderBy(['Games.ended_at' => 'DESC', 'Games.id' => 'DESC'])
            ->limit(self::PER_PAGE)
            ->offset(($page - 1) * self::PER_PAGE)
            ->toArray();

        $boards = [];
        $results = [];
        foreach ($games as $game) {
            $boards[$game->id] = $this->Games->scoreboard($game);
            $results[$game->id] = \App\Service\GameResultPicture::describe($game, $boards[$game->id]);
        }

        $lastGameDateRange = $this->getGameDateRange();

        $this->set(compact('games', 'boards', 'results', 'lastGameDateRange', 'page', 'pages', 'total'));
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

        // Neighbouring (counted) games for the arrows, in the order of the
        // games list (last played first): prev = the newer game above it,
        // next = the older one below
        $neighbour = function (string $op, string $dir) use ($game) {
            if ($game->ended_at === null) {
                return null;
            }
            return $this->Games->find()
                ->select(['Games.id'])
                ->where(['Games.inaccurate' => false, 'Games.ended_at IS NOT' => null, 'OR' => [
                    "Games.ended_at $op" => $game->ended_at,
                    'AND' => ['Games.ended_at' => $game->ended_at, "Games.id $op" => $game->id],
                ]])
                ->orderBy(['Games.ended_at' => $dir, 'Games.id' => $dir])
                ->disableHydration()
                ->first()['id'] ?? null;
        };
        $prevId = $neighbour('>', 'ASC');
        $nextId = $neighbour('<', 'DESC');

        $notQualified = $game->inaccurate ? (new HallOfFameService())->notQualified($game->id) : [];
        // a much weaker player beating a much stronger one in their duel
        $upsets = $game->inaccurate ? [] : (new \App\Service\UpsetService())->forGame($game->id);

        [$timeline, $votes] = $this->timelineAndVotes($game);

        $this->set(compact('game', 'board', 'prevId', 'nextId', 'notQualified', 'upsets', 'timeline', 'votes'));
    }

    /**
     * The game's scoreboard of every minute (game_timelines, games since Oct
     * 2026) and the votes called during it, players who opted out of
     * tracking shown as Anonymous.
     */
    private function timelineAndVotes(\App\Model\Entity\Game $game): array
    {
        $conn = $this->Games->getConnection();
        $json = $conn->execute('SELECT timeline FROM game_timelines WHERE game_id = ?', [$game->id])->fetch('assoc')['timeline'] ?? null;
        $timeline = $json ? (json_decode($json, true) ?: []) : [];
        $votes = $conn->execute(
            "SELECT event_time, actor_id, target_id, details FROM events WHERE game_id = ? AND type = 'vote' ORDER BY event_time, id",
            [$game->id])->fetchAll('assoc');

        $ids = array_filter(array_merge(
            array_column($votes, 'actor_id'), array_column($votes, 'target_id'),
            ...array_map(fn($s) => array_column($s['p'], 'id'), $timeline)));
        $players = $ids ? $this->fetchTable('Players')->find()
            ->select(['id', 'name', 'country', 'track'])
            ->where(['id IN' => array_values(array_unique($ids))])
            ->all()->indexBy('id')->toArray() : [];
        $known = fn(?string $id) => $id !== null && isset($players[$id]) && (int)$players[$id]->track === 1;

        foreach ($timeline as &$snapshot) {
            foreach ($snapshot['p'] as &$p) {
                if ($known($p['id'])) {
                    // the profile's name, as on the scoreboard (not the one used that minute)
                    $p['n'] = $players[$p['id']]->name;
                    $p['c'] = $players[$p['id']]->country;
                } else {
                    // not a profile we can show (opted out, default name): no link
                    $anonymous = $p['id'] !== null;
                    $p['id'] = null;
                    $p['n'] = $anonymous ? 'Anonymous' : $p['n'];
                }
            }
            unset($p);
        }
        unset($snapshot);

        $start = $game->started_at ? $game->started_at->getTimestamp() : null;
        $optedOut = fn(?string $id) => $id !== null && isset($players[$id]) && !$known($id);
        foreach ($votes as &$v) {
            $d = (json_decode((string)$v['details'], true) ?: []) + ['name' => '', 'kind' => 'other', 'target' => null];
            if ($optedOut($v['actor_id'])) {
                $d['name'] = 'Anonymous';
            } elseif ($known($v['actor_id'])) {
                $d['name'] = $players[$v['actor_id']]->name;
            }
            if ($optedOut($v['target_id'])) {
                $d['target'] = 'Anonymous';
            } elseif ($known($v['target_id'])) {
                $d['target'] = $players[$v['target_id']]->name;
            }
            $v = [
                'at' => $start ? max(0, strtotime((string)$v['event_time']) - $start) : null,
                'actor_id' => $known($v['actor_id']) ? $v['actor_id'] : null,
                'target_id' => $known($v['target_id']) ? $v['target_id'] : null,
            ] + $d;
        }
        unset($v);

        return [$timeline, $votes];
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
        // the drawing code's date too, so a new layout redraws the cached pictures
        $file = $dir . 'game-' . $id . '-' . md5((string)json_encode($picture) . filemtime(ROOT . '/src/Service/LiveScoreboardImage.php')) . '.jpg';
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
