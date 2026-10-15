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
    /** Games a player needs to be ranked: the Elo moves fast for the first ones (CompetitiveRatings::NEW_GAMES) */
    public const MIN_GAMES = 10;
    /** Ranked and provisional players: a game in the last ACTIVE_DAYS days */
    public const ACTIVE_DAYS = 90;

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
        // active players: ranked from MIN_GAMES on, the others provisional
        $active = $conn->execute(
            'SELECT r.*, p.name profile_name, p.country, p.track FROM comp_ratings r
             LEFT JOIN players p ON p.id = r.player_id
             WHERE r.last_game_at >= ? AND COALESCE(p.cheater, 0) = 0 AND COALESCE(p.track, 1) = 1
             ORDER BY r.rating DESC', [date('Y-m-d H:i:s', strtotime('-' . self::ACTIVE_DAYS . ' days'))])->fetchAll('assoc');
        $ratings = array_values(array_filter($active, fn($r) => (int)$r['games'] >= self::MIN_GAMES));
        $provisional = array_values(array_filter($active, fn($r) => (int)$r['games'] < self::MIN_GAMES));
        $inactive = (int)$conn->execute('SELECT COUNT(*) FROM comp_ratings WHERE games >= ? AND last_game_at < ?',
            [self::MIN_GAMES, date('Y-m-d H:i:s', strtotime('-' . self::ACTIVE_DAYS . ' days'))])->fetchColumn(0);
        $games = $conn->execute(
            'SELECT * FROM comp_games ORDER BY ended_at DESC LIMIT 30')->fetchAll('assoc');
        $players = $this->gamePlayers(array_column($games, 'id'));
        $running = $conn->execute('SELECT server_name, state, updated_at FROM comp_running')->fetchAll('assoc');

        $this->set(compact('ratings', 'provisional', 'inactive', 'games', 'players', 'running'));
        $this->set('activeDays', self::ACTIVE_DAYS);
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
