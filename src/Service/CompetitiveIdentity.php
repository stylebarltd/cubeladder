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

    /**
     * An admin says two competitive players are the same person: everything of
     * $from (games, names) becomes $into's, and the names keep resolving to
     * $into from now on. $into can also be a ladder player id, to link a player
     * to their profile.
     *
     * @return int games moved
     */
    public function merge(string $from, string $into): int
    {
        if ($from === $into) {
            return 0;
        }
        $conn = ConnectionManager::get('default');
        $profile = $conn->execute('SELECT id FROM players WHERE id = ?', [$into])->fetchColumn(0);
        $playerId = $profile ? (string)$profile : ($conn->execute(
            'SELECT player_id FROM comp_aliases WHERE player_key = ? AND player_id IS NOT NULL LIMIT 1', [$into])->fetchColumn(0) ?: null);
        // a profile on both sides: keep the one of $into
        $playerId ??= $conn->execute(
            'SELECT player_id FROM comp_aliases WHERE player_key = ? AND player_id IS NOT NULL LIMIT 1', [$from])->fetchColumn(0) ?: null;

        return $conn->transactional(function ($conn) use ($from, $into, $playerId) {
            $conn->execute('UPDATE comp_aliases SET player_key = ?, player_id = ? WHERE player_key IN (?, ?)', [$into, $playerId, $from, $into]);
            $conn->execute('UPDATE comp_player_stats SET player_key = ?, player_id = ? WHERE player_key = ?', [$into, $playerId, $into]);

            return $conn->execute('UPDATE comp_player_stats SET player_key = ?, player_id = ? WHERE player_key = ?', [$into, $playerId, $from])->rowCount();
        });
    }

    /**
     * When $key is a ladder profile: the competitive names it plays under
     * become the profile's aliases (player_aliases), so the ladder and the
     * name matching know them too.
     *
     * @return int names added
     */
    public function addLadderAliases(string $key): int
    {
        $conn = ConnectionManager::get('default');
        $profile = $conn->execute('SELECT name FROM players WHERE id = ?', [$key])->fetchColumn(0);
        if ($profile === false) {
            return 0;
        }
        $added = 0;
        foreach ($conn->execute('SELECT name, MIN(last_seen) f, MAX(last_seen) l FROM comp_aliases WHERE player_key = ? GROUP BY name', [$key])->fetchAll('assoc') as $a) {
            if ($a['name'] === $profile || strtolower($a['name']) === 'unarmed'
                || $conn->execute('SELECT 1 FROM player_aliases WHERE player_id = ? AND alias = ?', [$key, $a['name']])->fetchColumn(0)) {
                continue;
            }
            $conn->execute('INSERT INTO player_aliases (player_id, alias, first_seen, last_seen) VALUES (?, ?, ?, ?)',
                [$key, mb_substr($a['name'], 0, 64), $a['f'], $a['l']]);
            $added++;
        }

        return $added;
    }

    /**
     * Every competitive player for the admin's merge list: key, latest name,
     * all names, games, Elo, ladder profile - sorted by the name's core, so
     * "ZZ|Jahoo" and "jahoo_" stand together.
     */
    public function overview(): array
    {
        $conn = ConnectionManager::get('default');
        $players = [];
        foreach ($conn->execute(
            'SELECT s.player_key, MAX(s.player_id) player_id, COUNT(*) games, MAX(g.ended_at) last,
                    SUBSTRING_INDEX(GROUP_CONCAT(s.name ORDER BY g.ended_at DESC SEPARATOR 0x1f), 0x1f, 1) name
             FROM comp_player_stats s INNER JOIN comp_games g ON g.id = s.comp_game_id
             GROUP BY s.player_key')->fetchAll('assoc') as $r) {
            $players[$r['player_key']] = $r + ['names' => [], 'nets' => 0, 'rating' => null];
        }
        foreach ($conn->execute("SELECT player_key, name, ip3 FROM comp_aliases")->fetchAll('assoc') as $a) {
            if (isset($players[$a['player_key']])) {
                $players[$a['player_key']]['names'][$a['name']] = true;
                if ($a['ip3'] !== '') {
                    $players[$a['player_key']]['netList'][$a['ip3']] = true;
                }
            }
        }
        foreach ($conn->execute('SELECT player_key, rating FROM comp_ratings')->fetchAll('assoc') as $r) {
            if (isset($players[$r['player_key']])) {
                $players[$r['player_key']]['rating'] = (float)$r['rating'];
            }
        }
        $profiles = [];
        $ids = array_values(array_filter(array_unique(array_column($players, 'player_id'))));
        if ($ids) {
            foreach ($conn->execute('SELECT id, name, country FROM players WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids)->fetchAll('assoc') as $p) {
                $profiles[$p['id']] = $p;
            }
        }
        foreach ($players as &$p) {
            $p['names'] = array_keys($p['names']);
            $p['nets'] = count($p['netList'] ?? []);
            unset($p['netList']);
            $p['profile'] = $profiles[$p['player_id']] ?? null;
            $p['core'] = self::core((string)$p['name']);
        }
        unset($p);
        uasort($players, fn($a, $b) => [$a['core'], -$a['games']] <=> [$b['core'], -$b['games']]);

        return $players;
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
