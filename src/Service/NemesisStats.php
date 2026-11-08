<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * Nemesis advanced stats (a QR hunt unlock, level 3): a player's duels in
 * detail - every opponent, per map, per week and per weapon, and one rival
 * head to head.
 *
 * Source: kill_pairs (who killed whom per game, since Sep 2026) and
 * kill_weapons (the same per weapon, since Nov 2026; AcLogParser::KILL_WEAPONS).
 * Teamkills are left out, games marked inaccurate too, and opponents who
 * opted out of tracking are not shown.
 */
class NemesisStats
{
    public const WEEKS = 26;
    public const RIVALS = 30;
    public const WEAPONS = ['assault', 'smg', 'sniper', 'carbine', 'shotgun', 'pistol', 'grenade', 'knife'];
    public const WEAPON_LABELS = ['assault' => 'Assault rifle', 'smg' => 'Submachine gun', 'sniper' => 'Sniper rifle',
        'carbine' => 'Carbine', 'shotgun' => 'Shotgun', 'pistol' => 'Pistol / akimbo', 'grenade' => 'Grenade', 'knife' => 'Knife'];

    /** Since when kill_weapons is filled (the parser change). */
    public const WEAPONS_SINCE = '2026-11-08';

    /**
     * Everything for one player's duels page; with $vs also that rival head to head.
     *
     * @return array<string, mixed>
     */
    public function player(string $id, ?string $vs = null): array
    {
        $out = [
            'totals' => $this->totals($id),
            'rivals' => $this->rivals($id),
            'maps' => $this->maps($id),
            'weeks' => $this->weeks($id),
            'weapons' => $this->weapons($id),
        ];
        if ($vs !== null && $vs !== $id) {
            $out['duel'] = $this->duel($id, $vs);
        }

        return $out;
    }

    /** Kills / deaths in duels and the number of opponents. */
    private function totals(string $id): array
    {
        $r = $this->conn()->execute(
            "SELECT SUM(CASE WHEN kp.killer_id = ? THEN kp.kills ELSE 0 END) k,
                    SUM(CASE WHEN kp.victim_id = ? THEN kp.kills ELSE 0 END) d,
                    COUNT(DISTINCT CASE WHEN kp.killer_id = ? THEN kp.victim_id ELSE kp.killer_id END) opponents,
                    COUNT(DISTINCT kp.game_id) games
             FROM kill_pairs kp JOIN games g ON g.id = kp.game_id AND g.inaccurate = 0
             JOIN players o ON o.id = CASE WHEN kp.killer_id = ? THEN kp.victim_id ELSE kp.killer_id END AND o.track = 1
             WHERE (kp.killer_id = ? OR kp.victim_id = ?) AND kp.kills > 0",
            [$id, $id, $id, $id, $id, $id])->fetch('assoc') ?: [];

        return ['kills' => (int)($r['k'] ?? 0), 'deaths' => (int)($r['d'] ?? 0),
            'opponents' => (int)($r['opponents'] ?? 0), 'games' => (int)($r['games'] ?? 0)];
    }

    /** Every opponent: kills on them, deaths by them, games met, last met; most duels first. */
    private function rivals(string $id): array
    {
        $rows = $this->conn()->execute(
            "SELECT o.id, o.name, o.country, o.picture,
                    SUM(CASE WHEN kp.killer_id = ? THEN kp.kills ELSE 0 END) k,
                    SUM(CASE WHEN kp.victim_id = ? THEN kp.kills ELSE 0 END) d,
                    COUNT(DISTINCT kp.game_id) games, MAX(g.started_at) last_met
             FROM kill_pairs kp JOIN games g ON g.id = kp.game_id AND g.inaccurate = 0
             JOIN players o ON o.id = CASE WHEN kp.killer_id = ? THEN kp.victim_id ELSE kp.killer_id END AND o.track = 1
             WHERE (kp.killer_id = ? OR kp.victim_id = ?) AND kp.kills > 0
             GROUP BY o.id, o.name, o.country, o.picture
             ORDER BY SUM(kp.kills) DESC LIMIT " . self::RIVALS,
            [$id, $id, $id, $id, $id])->fetchAll('assoc');

        return array_map(fn($r) => ['id' => $r['id'], 'name' => $r['name'], 'country' => $r['country'], 'picture' => $r['picture'],
            'kills' => (int)$r['k'], 'deaths' => (int)$r['d'], 'games' => (int)$r['games'], 'last_met' => $r['last_met']], $rows);
    }

    /** Per map: duel kills / deaths, and the nemesis and prey on that map. */
    private function maps(string $id): array
    {
        $rows = $this->conn()->execute(
            "SELECT m.name map, o.id, o.name,
                    SUM(CASE WHEN kp.killer_id = ? THEN kp.kills ELSE 0 END) k,
                    SUM(CASE WHEN kp.victim_id = ? THEN kp.kills ELSE 0 END) d
             FROM kill_pairs kp JOIN games g ON g.id = kp.game_id AND g.inaccurate = 0 JOIN maps m ON m.id = g.map_id
             JOIN players o ON o.id = CASE WHEN kp.killer_id = ? THEN kp.victim_id ELSE kp.killer_id END AND o.track = 1
             WHERE (kp.killer_id = ? OR kp.victim_id = ?) AND kp.kills > 0
             GROUP BY m.name, o.id, o.name",
            [$id, $id, $id, $id, $id])->fetchAll('assoc');
        $maps = [];
        foreach ($rows as $r) {
            $m = &$maps[$r['map']];
            $m ??= ['map' => $r['map'], 'kills' => 0, 'deaths' => 0, 'nemesis' => null, 'prey' => null];
            $m['kills'] += (int)$r['k'];
            $m['deaths'] += (int)$r['d'];
            if ((int)$r['d'] > 0 && (int)$r['d'] > ($m['nemesis']['n'] ?? 0)) {
                $m['nemesis'] = ['id' => $r['id'], 'name' => $r['name'], 'n' => (int)$r['d']];
            }
            if ((int)$r['k'] > 0 && (int)$r['k'] > ($m['prey']['n'] ?? 0)) {
                $m['prey'] = ['id' => $r['id'], 'name' => $r['name'], 'n' => (int)$r['k']];
            }
            unset($m);
        }
        usort($maps, fn($a, $b) => ($b['kills'] + $b['deaths']) <=> ($a['kills'] + $a['deaths']));

        return array_values($maps);
    }

    /** Per week (Monday), the last WEEKS weeks with duels: kills, deaths and the nemesis of the week. */
    private function weeks(string $id): array
    {
        $since = date('Y-m-d', strtotime('monday this week -' . (self::WEEKS - 1) . ' weeks'));
        $rows = $this->conn()->execute(
            "SELECT DATE_SUB(DATE(g.started_at), INTERVAL WEEKDAY(g.started_at) DAY) wk, o.id, o.name,
                    SUM(CASE WHEN kp.killer_id = ? THEN kp.kills ELSE 0 END) k,
                    SUM(CASE WHEN kp.victim_id = ? THEN kp.kills ELSE 0 END) d
             FROM kill_pairs kp JOIN games g ON g.id = kp.game_id AND g.inaccurate = 0 AND g.started_at >= ?
             JOIN players o ON o.id = CASE WHEN kp.killer_id = ? THEN kp.victim_id ELSE kp.killer_id END AND o.track = 1
             WHERE (kp.killer_id = ? OR kp.victim_id = ?) AND kp.kills > 0
             GROUP BY wk, o.id, o.name",
            [$id, $id, $since, $id, $id, $id])->fetchAll('assoc');
        $weeks = [];
        foreach ($rows as $r) {
            $w = &$weeks[$r['wk']];
            $w ??= ['week' => $r['wk'], 'kills' => 0, 'deaths' => 0, 'nemesis' => null];
            $w['kills'] += (int)$r['k'];
            $w['deaths'] += (int)$r['d'];
            if ((int)$r['d'] > 0 && (int)$r['d'] > ($w['nemesis']['n'] ?? 0)) {
                $w['nemesis'] = ['id' => $r['id'], 'name' => $r['name'], 'n' => (int)$r['d']];
            }
            unset($w);
        }
        ksort($weeks);

        return array_values($weeks);
    }

    /** Kills with and deaths by each weapon (kill_weapons). */
    private function weapons(string $id): array
    {
        $rows = $this->conn()->execute(
            "SELECT kw.weapon, SUM(CASE WHEN kw.killer_id = ? THEN kw.kills ELSE 0 END) k, SUM(CASE WHEN kw.victim_id = ? THEN kw.kills ELSE 0 END) d
             FROM kill_weapons kw JOIN games g ON g.id = kw.game_id AND g.inaccurate = 0
             JOIN players o ON o.id = CASE WHEN kw.killer_id = ? THEN kw.victim_id ELSE kw.killer_id END AND o.track = 1
             WHERE kw.killer_id = ? OR kw.victim_id = ? GROUP BY kw.weapon",
            [$id, $id, $id, $id, $id])->fetchAll('assoc');

        return $this->weaponRows($rows);
    }

    /** One rival head to head: totals, per map, per week, per weapon, the last games together. */
    private function duel(string $id, string $vs): ?array
    {
        $conn = $this->conn();
        $rival = $conn->execute('SELECT id, name, country, picture FROM players WHERE id = ? AND track = 1', [$vs])->fetch('assoc');
        if (!$rival) {
            return null;
        }
        $pair = 'FROM kill_pairs kp JOIN games g ON g.id = kp.game_id AND g.inaccurate = 0 JOIN maps m ON m.id = g.map_id
                 WHERE ((kp.killer_id = ? AND kp.victim_id = ?) OR (kp.killer_id = ? AND kp.victim_id = ?)) AND kp.kills > 0';
        $kd = 'SUM(CASE WHEN kp.killer_id = ? THEN kp.kills ELSE 0 END) k, SUM(CASE WHEN kp.killer_id = ? THEN kp.kills ELSE 0 END) d';
        $args = [$id, $vs, $id, $vs, $vs, $id];
        $maps = $conn->execute("SELECT m.name map, $kd $pair GROUP BY m.name ORDER BY SUM(kp.kills) DESC", $args)->fetchAll('assoc');
        $weeks = $conn->execute("SELECT DATE_SUB(DATE(g.started_at), INTERVAL WEEKDAY(g.started_at) DAY) wk, $kd $pair GROUP BY wk ORDER BY wk", $args)->fetchAll('assoc');
        $games = $conn->execute("SELECT g.id, g.started_at, g.mode, m.name map, $kd $pair GROUP BY g.id, g.started_at, g.mode, m.name ORDER BY g.started_at DESC LIMIT 10", $args)->fetchAll('assoc');
        $weapons = $conn->execute(
            'SELECT kw.weapon, SUM(CASE WHEN kw.killer_id = ? THEN kw.kills ELSE 0 END) k, SUM(CASE WHEN kw.killer_id = ? THEN kw.kills ELSE 0 END) d
             FROM kill_weapons kw JOIN games g ON g.id = kw.game_id AND g.inaccurate = 0
             WHERE (kw.killer_id = ? AND kw.victim_id = ?) OR (kw.killer_id = ? AND kw.victim_id = ?) GROUP BY kw.weapon',
            [$id, $vs, $id, $vs, $vs, $id])->fetchAll('assoc');
        $int = fn(array $rows, string $key) => array_map(fn($r) => [$key => $r[$key], 'kills' => (int)$r['k'], 'deaths' => (int)$r['d']], $rows);
        $mapRows = $int($maps, 'map');

        return [
            'rival' => $rival,
            'kills' => array_sum(array_column($mapRows, 'kills')),
            'deaths' => array_sum(array_column($mapRows, 'deaths')),
            'maps' => $mapRows,
            'weeks' => array_map(fn($r) => ['week' => $r['wk'], 'kills' => (int)$r['k'], 'deaths' => (int)$r['d']], $weeks),
            'weapons' => $this->weaponRows($weapons),
            'games' => array_map(fn($r) => ['id' => $r['id'], 'started_at' => $r['started_at'], 'mode' => $r['mode'], 'map' => $r['map'],
                'kills' => (int)$r['k'], 'deaths' => (int)$r['d']], $games),
        ];
    }

    /** Weapon rows in a fixed order, every weapon once (0 when none). */
    private function weaponRows(array $rows): array
    {
        $by = array_column($rows, null, 'weapon');

        return array_map(fn($w) => ['weapon' => $w, 'label' => self::WEAPON_LABELS[$w],
            'kills' => (int)($by[$w]['k'] ?? 0), 'deaths' => (int)($by[$w]['d'] ?? 0)], self::WEAPONS);
    }

    private function conn(): \Cake\Database\Connection
    {
        /** @var \Cake\Database\Connection */
        return ConnectionManager::get('default');
    }
}
