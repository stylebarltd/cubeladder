<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;

/**
 * Pairs of profiles that are probably one player (admin area): the same
 * name (also without the clan tag), the same IP or network, one profile
 * ending before the other started. Pairs that ever played in the same game
 * are two people and left out, as are pairs already asked about, requested
 * or dismissed (merge_requests).
 *
 * 'old' is the profile to merge away, 'new' the one that stays (the one
 * seen last: it holds the key the player logs in with now).
 */
class DuplicateFinder
{
    /** names that many different people use */
    private const GENERIC = ['unarmed', 'player', 'test', 'anonymous', 'noob', 'newbie', 'guest', 'user'];
    private const MIN_GAMES = 3;

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? ConnectionManager::get('default');
    }

    /** The name without clan tag, symbols and case: "ZZ|Perros" and "perros." -> "perros" */
    public static function core(string $name): string
    {
        $name = preg_replace('/\[[^\]]*\]|\([^)]*\)|\{[^}]*\}/', '', $name);
        if (str_contains($name, '|')) {
            $parts = array_values(array_filter(explode('|', $name), fn($p) => trim($p) !== ''));
            // the tag is the shorter side: "ZZ|Perros", "Perros|ZZ"
            usort($parts, fn($a, $b) => strlen($b) <=> strlen($a));
            $name = $parts[0] ?? $name;
        }

        return strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
    }

    /**
     * @return list<array{old: array, new: array, score: int, reasons: list<string>}>
     */
    public function candidates(int $limit = 100): array
    {
        $players = $this->conn->execute(
            'SELECT p.id, p.name, p.ip, p.country, p.first_seen, p.last_seen, p.picture,
                    COUNT(s.id) AS games, COALESCE(SUM(s.total_score), 0) AS points,
                    MIN(g.started_at) AS first_game, MAX(g.started_at) AS last_game
             FROM players p
             JOIN player_stats_per_game s ON s.player_id = p.id
             JOIN games g ON g.id = s.game_id
             WHERE COALESCE(p.track, 1) = 1
             GROUP BY p.id
             HAVING games >= ?', [self::MIN_GAMES])->fetchAll('assoc');
        $byId = array_column($players, null, 'id');

        // groups that share something: core name, IP
        $groups = [];
        foreach ($players as $p) {
            $core = self::core((string)$p['name']);
            if (strlen($core) >= 3 && !in_array($core, self::GENERIC, true)) {
                $groups['n:' . $core][] = $p['id'];
            }
            if (!empty($p['ip'])) {
                $groups['i:' . $p['ip']][] = $p['id'];
            }
        }
        // aliases: a name someone else played under before
        foreach ($this->conn->execute('SELECT player_id, alias FROM player_aliases')->fetchAll('assoc') as $a) {
            $core = self::core((string)$a['alias']);
            if (isset($byId[$a['player_id']]) && strlen($core) >= 3 && !in_array($core, self::GENERIC, true)) {
                $groups['n:' . $core][] = $a['player_id'];
            }
        }

        $pairs = [];
        foreach ($groups as $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2 || count($ids) > 6) {
                continue; // a name half the server uses says nothing
            }
            foreach ($ids as $i => $a) {
                foreach (array_slice($ids, $i + 1) as $b) {
                    $pairs[min($a, $b) . '|' . max($a, $b)] = [$a, $b];
                }
            }
        }
        if (!$pairs) {
            return [];
        }

        $handled = [];
        foreach ($this->conn->execute('SELECT from_id, to_id FROM merge_requests')->fetchAll('assoc') as $r) {
            $handled[min($r['from_id'], $r['to_id']) . '|' . max($r['from_id'], $r['to_id'])] = true;
        }

        $result = [];
        foreach ($pairs as $key => [$a, $b]) {
            if (isset($handled[$key])) {
                continue;
            }
            [$old, $new] = $byId[$a]['last_game'] <= $byId[$b]['last_game'] ? [$byId[$a], $byId[$b]] : [$byId[$b], $byId[$a]];
            $score = 0;
            $reasons = [];
            if (strtolower($old['name']) === strtolower($new['name'])) {
                $score += 4;
                $reasons[] = 'same name';
            } elseif (self::core($old['name']) === self::core($new['name'])) {
                $score += 3;
                $reasons[] = 'same name without tag';
            }
            if (!empty($old['ip']) && $old['ip'] === $new['ip']) {
                $score += 4;
                $reasons[] = 'same IP';
            } elseif (!empty($old['ip']) && !empty($new['ip'])
                && implode('.', array_slice(explode('.', $old['ip']), 0, 3)) === implode('.', array_slice(explode('.', $new['ip']), 0, 3))) {
                $score += 2;
                $reasons[] = 'same network';
            }
            if (!empty($old['country']) && !empty($new['country'])) {
                if ($old['country'] === $new['country']) {
                    $score += 1;
                } else {
                    $score -= 3;
                    $reasons[] = 'different country';
                }
            }
            if ($old['last_game'] < $new['first_game']) {
                $score += 2;
                $reasons[] = 'one after the other';
            }
            if ($score < 4) {
                continue;
            }
            $result[] = ['old' => $old, 'new' => $new, 'score' => $score, 'reasons' => $reasons];
        }

        // two profiles in the same game are two people
        $result = array_values(array_filter($result, fn($c) => !$this->playedTogether($c['old']['id'], $c['new']['id'])));
        usort($result, fn($x, $y) => [$y['score'], (int)$y['old']['games']] <=> [$x['score'], (int)$x['old']['games']]);

        return array_slice($result, 0, $limit);
    }

    private function playedTogether(string $a, string $b): bool
    {
        return (bool)$this->conn->execute(
            'SELECT 1 FROM player_stats_per_game x JOIN player_stats_per_game y ON y.game_id = x.game_id
             WHERE x.player_id = ? AND y.player_id = ? LIMIT 1', [$a, $b])->fetch();
    }
}
