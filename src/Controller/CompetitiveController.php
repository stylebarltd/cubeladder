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
            'SELECT r.*, p.name profile_name, p.country, p.track, p.picture FROM comp_ratings r
             LEFT JOIN players p ON p.id = r.player_id
             WHERE r.last_game_at >= ? AND COALESCE(p.cheater, 0) = 0 AND COALESCE(p.track, 1) = 1
             ORDER BY r.rating DESC', [date('Y-m-d H:i:s', strtotime('-' . self::ACTIVE_DAYS . ' days'))])->fetchAll('assoc');
        // K/D and accuracy over their counted games (accuracy only from games without match mode), and the parts
        // of the Elo (CompetitiveRatings): points gained and lost, the expected score (sum of the win chances)
        // and the average strength of the teams they played against
        $totals = [];
        foreach ($conn->execute(
            'SELECT s.player_key, SUM(s.frags) frags, SUM(s.deaths) deaths, SUM(s.damage) damage, SUM(s.shot_damage) shot,
                    SUM(GREATEST(s.rating_after - s.rating_before, 0)) gained,
                    SUM(GREATEST(s.rating_before - s.rating_after, 0)) lost,
                    SUM(s.expected) expected, AVG(s.opp_rating) opp
             FROM comp_player_stats s INNER JOIN comp_games g ON g.id = s.comp_game_id AND g.counted = 1
             GROUP BY s.player_key')->fetchAll('assoc') as $t) {
            $totals[$t['player_key']] = $t;
        }
        foreach ($active as &$r) {
            $t = $totals[$r['player_key']] ?? null;
            $r['kd'] = $t ? (int)$t['frags'] / max(1, (int)$t['deaths']) : null;
            $r['acc'] = $t && (int)$t['shot'] > 0 ? (int)$t['damage'] / (int)$t['shot'] * 100 : null;
            $r['gained'] = $t ? (float)$t['gained'] : 0.0;
            $r['lost'] = $t ? (float)$t['lost'] : 0.0;
            $r['expected'] = $t && $t['expected'] !== null ? (float)$t['expected'] : null;
            $r['score'] = (int)$r['wins'] + (int)$r['draws'] / 2;
            $r['opp'] = $t && $t['opp'] !== null ? (float)$t['opp'] : null;
        }
        unset($r);
        // a worked example for "How the Elo works": a rated player of the latest counted game
        $example = $conn->execute(
            'SELECT s.name, s.team, s.rating_before, s.rating_after, s.team_rating, s.opp_rating, s.expected, s.k_factor,
                    g.id game_id, g.map, g.winner, g.team_size, p.track
             FROM comp_player_stats s INNER JOIN comp_games g ON g.id = s.comp_game_id AND g.counted = 1
             LEFT JOIN players p ON p.id = s.player_id
             WHERE s.expected IS NOT NULL ORDER BY g.ended_at DESC, s.team, s.flags DESC, s.frags DESC LIMIT 1')->fetch('assoc') ?: null;
        if ($example && $example['track'] !== null && (int)$example['track'] !== 1) {
            $example['name'] = 'Anonymous';
        }
        $ratings = array_values(array_filter($active, fn($r) => (int)$r['games'] >= self::MIN_GAMES));
        $provisional = array_values(array_filter($active, fn($r) => (int)$r['games'] < self::MIN_GAMES));
        $inactive = (int)$conn->execute('SELECT COUNT(*) FROM comp_ratings WHERE games >= ? AND last_game_at < ?',
            [self::MIN_GAMES, date('Y-m-d H:i:s', strtotime('-' . self::ACTIVE_DAYS . ' days'))])->fetchColumn(0);
        $games = $conn->execute(
            'SELECT * FROM comp_games ORDER BY ended_at DESC LIMIT 30')->fetchAll('assoc');
        $players = $this->gamePlayers(array_column($games, 'id'));
        $profiles = $this->profileBadges(array_values(array_filter(array_column($active, 'player_id'))));
        $viewer = $this->request->getAttribute('identity')?->id;

        $this->set(compact('ratings', 'provisional', 'inactive', 'games', 'players', 'example', 'profiles', 'viewer'));
        $this->set('k', ['new' => CompetitiveRatings::K_NEW, 'normal' => CompetitiveRatings::K, 'newGames' => CompetitiveRatings::NEW_GAMES]);
        $this->set('minShare', CompetitiveTracker::MIN_SHARE);
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
            'SELECT s.*, p.name profile_name, p.country, p.track, p.picture FROM comp_player_stats s
             LEFT JOIN players p ON p.id = s.player_id
             WHERE s.comp_game_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
             ORDER BY s.flags DESC, s.frags DESC', $ids)->fetchAll('assoc');
        $out = [];
        foreach ($rows as $r) {
            if ($r['player_id'] && (int)$r['track'] !== 1) {
                $r['player_id'] = null;
                $r['name'] = 'Anonymous';
                $r['picture'] = null;
            }
            $out[$r['comp_game_id']][] = $r;
        }

        return $out;
    }

    /**
     * What the All Time Ranking (/players) shows next to a name, for these
     * players, one query each: player type and archetype (PlayerRatings),
     * last week's achievements, name frame / gold badge (QR hunt).
     *
     * @return array{ratings: array, achievements: array, styles: array}
     */
    private function profileBadges(array $ids): array
    {
        if (!$ids) {
            return ['ratings' => [], 'achievements' => [], 'styles' => []];
        }
        $ratings = [];
        foreach ($this->fetchTable('PlayerRatings')->find()
            ->select(['player_id', 'type', 'attack_pct', 'defense_pct', 'combat_pct', 'archetype', 'archetype_pct', 'archetype_rank'])
            ->where(['player_id IN' => $ids])->disableHydration()->all() as $r) {
            $ratings[$r['player_id']] = [
                'type' => $r['type'],
                'type_label' => \App\Command\CalculateRatingsCommand::typeLabel($r['type'], (int)$r['attack_pct'], (int)$r['defense_pct'], (int)$r['combat_pct']),
                'archetype' => $r['archetype'],
                'archetype_pct' => $r['archetype_pct'] !== null ? (int)$r['archetype_pct'] : null,
                'archetype_rank' => $r['archetype_rank'] !== null ? (int)$r['archetype_rank'] : null,
            ];
        }
        $achievements = [];
        foreach ($this->fetchTable('Achievements')->find()
            ->select(['player_id', 'event_type', 'count'])
            ->where(['player_id IN' => $ids, 'week_end' => date('Y-m-d', strtotime('last week sunday')), 'event_type IS NOT' => 'best_on_map'])
            ->disableHydration()->all() as $a) {
            $achievements[$a['player_id']][$a['event_type']] = $a['count'];
        }

        return ['ratings' => $ratings, 'achievements' => $achievements, 'styles' => \App\Service\QrHunt::nameStyles($ids)];
    }

    /** Elo start value, for the templates */
    public static function start(): float
    {
        return CompetitiveRatings::START;
    }
}
