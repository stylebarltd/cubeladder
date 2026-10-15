<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * Who is behind a name in a competitive game. Players there change their
 * names a lot, and the live view only gives a name and the first three
 * parts of the IP (/24 network). In this order:
 *
 *  1. a name already seen on this network (comp_aliases)
 *  2. the ladder profile with this name on this network
 *  3. a ladder profile on this network with a SIMILAR name (clan tags and
 *     symbols left out: "ZZ|Jahoo" ~ "jahoo_")
 *  4. a competitive player seen on this network with a similar name
 *  5. this exact name from ActionFPS, else the only ladder profile with it
 *  6. else a new player "name:<name>"
 *
 * Every answer is remembered (comp_aliases), so a player stays one player
 * however they are found next time. player_key = ladder player id when we
 * know the profile, else "afps:<id>" / "name:<name>".
 */
class CompetitiveIdentity
{
    /** Name similarity (0..1) that counts as the same player on the same network */
    public const SIMILAR = 0.75;

    /**
     * @return array{key: string, player_id: ?string, how: string}
     */
    public function resolve(string $name, ?string $ip3): array
    {
        $conn = ConnectionManager::get('default');
        $net = (string)$ip3;
        $found = null;

        $alias = $conn->execute('SELECT player_key, player_id, how FROM comp_aliases WHERE name = ? AND ip3 = ?', [$name, $net])->fetch('assoc');
        if ($alias) {
            $found = ['key' => $alias['player_key'], 'player_id' => $alias['player_id'], 'how' => $alias['how']];
        }
        if (!$found && $net !== '') {
            $onNet = $conn->execute(
                'SELECT id, name FROM players WHERE ip LIKE ? AND cheater = 0 ORDER BY last_seen DESC LIMIT 200', [$net . '.%'])->fetchAll('assoc');
            foreach ($onNet as $p) {
                if ($p['name'] === $name) {
                    $found = ['key' => $p['id'], 'player_id' => $p['id'], 'how' => 'ladder name + network'];
                    break;
                }
            }
            if (!$found) {
                $found = $this->mostSimilar($name, $onNet, fn($p) => ['key' => $p['id'], 'player_id' => $p['id'], 'how' => 'ladder similar name + network']);
            }
            if (!$found) {
                $seen = $conn->execute('SELECT DISTINCT name, player_key, player_id FROM comp_aliases WHERE ip3 = ?', [$net])->fetchAll('assoc');
                $found = $this->mostSimilar($name, $seen, fn($a) => ['key' => $a['player_key'], 'player_id' => $a['player_id'], 'how' => 'similar name + network']);
            }
        }
        if (!$found) {
            $afps = $conn->execute("SELECT player_key, player_id FROM comp_aliases WHERE name = ? AND ip3 = '' LIMIT 1", [$name])->fetch('assoc');
            if ($afps) {
                $found = ['key' => $afps['player_key'], 'player_id' => $afps['player_id'], 'how' => 'ActionFPS name'];
            }
        }
        if (!$found && strtolower($name) !== 'unarmed') {
            $ids = $conn->execute(
                'SELECT id FROM players WHERE name = ? AND cheater = 0 UNION SELECT player_id FROM player_aliases WHERE alias = ? LIMIT 2',
                [$name, $name])->fetchAll('num');
            if (count($ids) === 1) {
                $found = ['key' => (string)$ids[0][0], 'player_id' => (string)$ids[0][0], 'how' => 'ladder name'];
            }
        }
        $found ??= ['key' => 'name:' . $name, 'player_id' => null, 'how' => 'new'];

        $this->remember($name, $net, $found);

        return $found;
    }

    /**
     * An ActionFPS player: their registered id ("jahoo") when they have one,
     * else the name alone.
     *
     * @return array{key: string, player_id: ?string, how: string}
     */
    public function resolveActionFps(string $name, ?string $afpsId): array
    {
        if ($afpsId === null || $afpsId === '') {
            return $this->resolve($name, null);
        }
        $conn = ConnectionManager::get('default');
        // this ActionFPS id under any name before
        $known = $conn->execute('SELECT player_key, player_id, how FROM comp_aliases WHERE how IN (?, ?) LIMIT 1',
            ['ActionFPS id ' . $afpsId, 'ActionFPS id ' . $afpsId . ' = ladder name'])->fetch('assoc');
        if ($known) {
            $found = ['key' => $known['player_key'], 'player_id' => $known['player_id'], 'how' => $known['how']];
        } else {
            $ids = $conn->execute(
                'SELECT id FROM players WHERE name = ? AND cheater = 0 UNION SELECT player_id FROM player_aliases WHERE alias = ? LIMIT 2',
                [$name, $name])->fetchAll('num');
            $found = count($ids) === 1
                ? ['key' => (string)$ids[0][0], 'player_id' => (string)$ids[0][0], 'how' => 'ActionFPS id ' . $afpsId . ' = ladder name']
                : ['key' => 'afps:' . $afpsId, 'player_id' => null, 'how' => 'ActionFPS id ' . $afpsId];
        }
        $this->remember($name, '', $found);

        return $found;
    }

    /** "ZZ|Jahoo" -> "jahoo": lower case, without clan tags and symbols */
    public static function core(string $name): string
    {
        $n = preg_replace('~\[[^\]]*\]|\([^)]*\)|\{[^}]*\}|=[^=]+=~', '', $name) ?? $name;
        // a tag before or after a "|": keep the longer side
        if (str_contains($n, '|')) {
            $parts = array_filter(explode('|', $n), fn($p) => $p !== '');
            usort($parts, fn($a, $b) => strlen($b) <=> strlen($a));
            $n = $parts[0] ?? $n;
        }
        $n = strtolower(preg_replace('~[^a-z0-9]~i', '', $n) ?? '');

        return $n !== '' ? $n : strtolower($name);
    }

    public static function similarity(string $a, string $b): float
    {
        $a = self::core($a);
        $b = self::core($b);
        if ($a === $b) {
            return 1.0;
        }
        // one inside the other ("jahoo" in "jahoo123"), when it is not too short
        if (min(strlen($a), strlen($b)) >= 4 && (str_contains($a, $b) || str_contains($b, $a))) {
            return 0.9;
        }

        return 1 - levenshtein($a, $b) / max(1, max(strlen($a), strlen($b)));
    }

    private function mostSimilar(string $name, array $candidates, callable $map): ?array
    {
        $best = null;
        $bestScore = self::SIMILAR;
        foreach ($candidates as $c) {
            $score = self::similarity($name, (string)$c['name']);
            if ($score >= $bestScore) {
                [$best, $bestScore] = [$c, $score];
            }
        }

        return $best ? $map($best) : null;
    }

    private function remember(string $name, string $net, array $found): void
    {
        ConnectionManager::get('default')->execute(
            'INSERT INTO comp_aliases (name, ip3, player_key, player_id, how, last_seen) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)',
            [$name, $net, $found['key'], $found['player_id'], $found['how'], gmdate('Y-m-d H:i:s')]);
    }
}
