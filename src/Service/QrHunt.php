<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * QR hunt: codes hidden in our maps (cl_angkorwat first) open /q/<token>.
 * A player's level is the number of different codes they found; it is shown
 * as a badge on the profile and the hover card. Purely for fun, not part of
 * the ladder. Codes are created with `bin/cake qr_codes add`; a leaked code
 * is retired (active = 0) and replaced in the next map version.
 */
class QrHunt
{
    /** level (codes found) => name; a level beyond the list keeps the last name */
    public const LEVELS = [1 => 'Explorer', 2 => 'Temple Raider'];

    public static function levelName(int $level): ?string
    {
        if ($level < 1) {
            return null;
        }

        return self::LEVELS[$level] ?? end(self::LEVELS) ?: null;
    }

    /** The code behind a token, or null (unknown) */
    public function code(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9]{8,24}$/', $token)) {
            return null;
        }
        $row = ConnectionManager::get('default')->execute('SELECT id, token, map, label, hint, active FROM qr_codes WHERE token = ?', [$token])->fetch('assoc');

        return $row ?: null;
    }

    /**
     * Records a find (once per player and code).
     *
     * @return array{new: bool, place: int} place = how many players found it, this one included
     */
    public function record(int $codeId, string $playerId): array
    {
        $conn = ConnectionManager::get('default');
        $new = $conn->execute('INSERT IGNORE INTO qr_finds (qr_code_id, player_id, found) VALUES (?, ?, ?)',
            [$codeId, $playerId, date('Y-m-d H:i:s')])->rowCount() > 0;
        $place = (int)$conn->execute('SELECT COUNT(*) FROM qr_finds f INNER JOIN qr_finds me ON me.qr_code_id = f.qr_code_id
            WHERE me.qr_code_id = ? AND me.player_id = ? AND f.found <= me.found', [$codeId, $playerId])->fetchColumn(0);

        return ['new' => $new, 'place' => $place];
    }

    /** Codes found by a player (their level) */
    public function level(string $playerId): int
    {
        return (int)ConnectionManager::get('default')->execute('SELECT COUNT(*) FROM qr_finds WHERE player_id = ?', [$playerId])->fetchColumn(0);
    }

    /** Codes in active maps (the highest level reachable now) */
    public function total(): int
    {
        return (int)ConnectionManager::get('default')->execute('SELECT COUNT(*) FROM qr_codes WHERE active = 1')->fetchColumn(0);
    }

    /**
     * The hunters: players with at least one find, most finds first, the
     * earlier last find first on a tie.
     *
     * @return list<array{player_id:string, name:string, country:?string, level:int, last:string}>
     */
    public function hunters(int $limit = 20): array
    {
        $rows = ConnectionManager::get('default')->execute(
            'SELECT f.player_id, p.name, p.country, COUNT(*) level, MAX(f.found) last FROM qr_finds f
             INNER JOIN players p ON p.id = f.player_id
             WHERE p.track = 1 AND p.cheater = 0
             GROUP BY f.player_id, p.name, p.country ORDER BY level DESC, last ASC LIMIT ' . (int)$limit)->fetchAll('assoc');

        return array_map(fn($r) => ['player_id' => $r['player_id'], 'name' => $r['name'], 'country' => $r['country'],
            'level' => (int)$r['level'], 'last' => $r['last']], $rows);
    }
}
