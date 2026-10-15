<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\CompetitiveRatings;
use App\Service\CompetitiveTracker;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;

/**
 * The competitive ladder (CompetitiveTracker): Elo of the clan match
 * servers' games, apart from the normal ladder. Admins only until
 * Ladder.competitive.public is switched on.
 */
class CompetitiveController extends AppController
{
    /** Ratings shown, and games a player needs to be ranked */
    public const MIN_GAMES = 3;

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        if (!self::isPublic() && !$this->isAdmin()) {
            throw new NotFoundException();
        }
    }

    public static function isPublic(): bool
    {
        return (bool)Configure::read('Ladder.competitive.public');
    }

    /** The Elo ranking and the latest games */
    public function index()
    {
        $conn = $this->fetchTable('Games')->getConnection();
        $ratings = $conn->execute(
            'SELECT r.*, p.name profile_name, p.country, p.track FROM comp_ratings r
             LEFT JOIN players p ON p.id = r.player_id
             WHERE r.games >= ? AND COALESCE(p.cheater, 0) = 0 AND COALESCE(p.track, 1) = 1
             ORDER BY r.rating DESC', [self::MIN_GAMES])->fetchAll('assoc');
        $games = $conn->execute(
            'SELECT * FROM comp_games ORDER BY ended_at DESC LIMIT 30')->fetchAll('assoc');
        $players = $this->gamePlayers(array_column($games, 'id'));
        $running = $conn->execute('SELECT server_name, state, updated_at FROM comp_running')->fetchAll('assoc');

        $this->set(compact('ratings', 'games', 'players', 'running'));
        $this->set('servers', CompetitiveTracker::servers());
        $this->set('minGames', self::MIN_GAMES);
        $this->set('public', self::isPublic());
    }

    /** One competitive game: teams, numbers and Elo changes */
    public function game(string $id)
    {
        $game = $this->fetchTable('Games')->getConnection()
            ->execute('SELECT * FROM comp_games WHERE id = ?', [$id])->fetch('assoc');
        if (!$game) {
            throw new NotFoundException();
        }
        $players = $this->gamePlayers([$id])[$id] ?? [];
        $this->set(compact('game', 'players'));
    }

    /** comp_player_stats of these games with the profiles (Anonymous for opted-out players), by game id */
    private function gamePlayers(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $rows = $this->fetchTable('Games')->getConnection()->execute(
            'SELECT s.*, p.name profile_name, p.country, p.track FROM comp_player_stats s
             LEFT JOIN players p ON p.id = s.player_id
             WHERE s.comp_game_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
             ORDER BY s.flags DESC, s.frags DESC', $ids)->fetchAll('assoc');
        $out = [];
        foreach ($rows as $r) {
            if ($r['player_id'] && (int)$r['track'] !== 1) {
                $r['player_id'] = null;
                $r['name'] = 'Anonymous';
            }
            $out[$r['comp_game_id']][] = $r;
        }

        return $out;
    }

    /** Elo start value, for the templates */
    public static function start(): float
    {
        return CompetitiveRatings::START;
    }
}
