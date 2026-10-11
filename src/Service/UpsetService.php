<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * Upsets: a much weaker player wins the duel against a much stronger one in
 * a game. Two players who killed each other in a game had a duel
 * (kill_pairs: kills each way); it is an upset when the winner's skill
 * rating is GAP+ below the loser's, with MIN_KILLS+ kills on them and
 * MIN_LEAD+ more than they got back - e.g. "Joe (3.2) beat Wy6!LS (9.5) 5-2".
 *
 * Skill = the players' current skill rating (player_ratings, both rated);
 * counted games only. kill_pairs is filled since Sep 2026 (no backfill).
 * Used on the game page, in the Discord results and on the player page.
 */
class UpsetService
{
    public const GAP = 3.0;
    public const MIN_KILLS = 3;
    public const MIN_LEAD = 2;

    /**
     * The upsets of one game, biggest skill gap first.
     *
     * @return list<array{winner: array, loser: array, kills: int, deaths: int, gap: float}>
     */
    public function forGame(string $gameId, int $limit = 3): array
    {
        return $this->query('a.game_id = :id', ['id' => $gameId], $limit);
    }

    /**
     * A player's upsets (as the winner): how many, against how many players,
     * and the biggest ones (with game, map and date).
     *
     * @return array{count: int, opponents: int, top: list<array>}
     */
    public function forPlayer(string $playerId, int $limit = 5): array
    {
        $all = $this->query('a.killer_id = :id', ['id' => $playerId], 1000);

        return [
            'count' => count($all),
            'opponents' => count(array_unique(array_map(fn($u) => $u['loser']['id'], $all))),
            'top' => array_slice($all, 0, $limit),
        ];
    }

    /**
     * Upsets matching $where on kill_pairs a (the winner's kills on the loser).
     */
    private function query(string $where, array $params, int $limit): array
    {
        $rows = ConnectionManager::get('default')->execute("
            SELECT a.game_id, a.kills, COALESCE(b.kills, 0) AS deaths, g.started_at, m.name AS map_name,
                   w.id AS w_id, w.name AS w_name, w.country AS w_country, rw.skill AS w_skill,
                   l.id AS l_id, l.name AS l_name, l.country AS l_country, rl.skill AS l_skill
            FROM kill_pairs a
            LEFT JOIN kill_pairs b ON b.game_id = a.game_id AND b.killer_id = a.victim_id AND b.victim_id = a.killer_id
            INNER JOIN games g ON g.id = a.game_id AND g.inaccurate = 0
            LEFT JOIN maps m ON m.id = g.map_id
            INNER JOIN player_ratings rw ON rw.player_id = a.killer_id
            INNER JOIN player_ratings rl ON rl.player_id = a.victim_id
            INNER JOIN players w ON w.id = a.killer_id AND w.track = 1
            INNER JOIN players l ON l.id = a.victim_id AND l.track = 1
            WHERE $where AND a.killer_id <> a.victim_id
              AND rl.skill - rw.skill >= :gap AND a.kills >= :min_kills AND a.kills - COALESCE(b.kills, 0) >= :min_lead
            ORDER BY rl.skill - rw.skill DESC, a.kills DESC, g.started_at DESC
            LIMIT " . (int)$limit,
            $params + ['gap' => self::GAP, 'min_kills' => self::MIN_KILLS, 'min_lead' => self::MIN_LEAD]
        )->fetchAll('assoc');

        return array_map(fn($r) => [
            'game_id' => $r['game_id'],
            'map' => $r['map_name'],
            'started_at' => $r['started_at'],
            'winner' => ['id' => $r['w_id'], 'name' => $r['w_name'], 'country' => $r['w_country'], 'skill' => (float)$r['w_skill']],
            'loser' => ['id' => $r['l_id'], 'name' => $r['l_name'], 'country' => $r['l_country'], 'skill' => (float)$r['l_skill']],
            'kills' => (int)$r['kills'],
            'deaths' => (int)$r['deaths'],
            'gap' => round((float)$r['l_skill'] - (float)$r['w_skill'], 1),
        ], $rows);
    }
}
