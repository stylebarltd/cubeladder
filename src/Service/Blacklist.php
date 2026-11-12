<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;
use Cake\Database\Connection;

/**
 * The admin's decisions on a player (players.admin_status), set from the
 * player's page (AdminController::playerStatus):
 * - 'untracked': players.track = 0, locked. Shown as Anonymous everywhere,
 *   page hidden, and the player cannot switch "Don't track me" off again.
 * - 'blacklisted': cheater = 1 and track = 0, locked. Out of every ranking,
 *   board, Hall of Fame, rating, milestone and achievement, Anonymous in games,
 *   live and Discord, and no messages to other players. Their AssaultCube
 *   pubkeys (and the last IP, if the admin asks) go to the blacklist table.
 *
 * After each import, apply() blacklists every other profile that uses a
 * listed pubkey. A profile on a listed IP is only reported (ipMatches()), as
 * IPs are shared and change: the admin decides in Admin -> Blacklist.
 * Their games keep counting for everyone else (ratings, MVPs of the others).
 */
class Blacklist
{
    public const UNTRACKED = 'untracked';
    public const BLACKLISTED = 'blacklisted';

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? ConnectionManager::get('default');
    }

    /**
     * Sets a player's admin status. Returns what was listed:
     * ['pubkeys' => n, 'ip' => bool].
     */
    public function set(string $playerId, string $status, ?string $note = null, bool $withIp = false): array
    {
        $player = $this->conn->execute('SELECT id, pubkey, ip FROM players WHERE id = ?', [$playerId])->fetch('assoc');
        if (!$player || !in_array($status, [self::UNTRACKED, self::BLACKLISTED], true)) {
            return ['pubkeys' => 0, 'ip' => false];
        }
        $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null;
        $now = date('Y-m-d H:i:s');
        $listed = ['pubkeys' => 0, 'ip' => false];

        if ($status === self::UNTRACKED) {
            $this->conn->execute('UPDATE players SET track = 0, admin_status = ?, admin_note = ? WHERE id = ?', [$status, $note, $playerId]);
        } else {
            $this->conn->execute('UPDATE players SET track = 0, cheater = 1, admin_status = ?, admin_note = ? WHERE id = ?', [$status, $note, $playerId]);
            $keys = array_column($this->conn->execute('SELECT pubkey FROM player_pubkeys WHERE player_id = ?', [$playerId])->fetchAll('assoc'), 'pubkey');
            if (!empty($player['pubkey'])) {
                $keys[] = $player['pubkey'];
            }
            foreach (array_unique(array_filter($keys)) as $key) {
                $listed['pubkeys'] += $this->conn->execute(
                    'INSERT IGNORE INTO blacklist (kind, value, player_id, note, created) VALUES (?, ?, ?, ?, ?)',
                    ['pubkey', $key, $playerId, $note, $now])->rowCount();
            }
            if ($withIp && !empty($player['ip'])) {
                $listed['ip'] = $this->conn->execute(
                    'INSERT IGNORE INTO blacklist (kind, value, player_id, note, created) VALUES (?, ?, ?, ?, ?)',
                    ['ip', $player['ip'], $playerId, $note, $now])->rowCount() > 0;
            }
            // the suspicious list shows the same verdict
            $this->conn->execute('REPLACE INTO player_reviews (player_id, status, note, reviewed_at) VALUES (?, ?, ?, ?)',
                [$playerId, 'cheater', mb_substr('Blacklisted' . ($note ? ': ' . $note : ''), 0, 500), $now]);
        }
        $this->clearCaches();

        return $listed;
    }

    /** Undoes the admin status: tracked again, not a cheater, its blacklist entries removed */
    public function clear(string $playerId): void
    {
        $was = $this->conn->execute('SELECT admin_status FROM players WHERE id = ?', [$playerId])->fetchColumn(0);
        if ($was === self::BLACKLISTED) {
            $this->conn->execute('UPDATE players SET track = 1, cheater = 0, admin_status = NULL, admin_note = NULL WHERE id = ?', [$playerId]);
            $this->conn->execute("DELETE FROM player_reviews WHERE player_id = ? AND status = 'cheater'", [$playerId]);
        } elseif ($was === self::UNTRACKED) {
            $this->conn->execute('UPDATE players SET track = 1, admin_status = NULL, admin_note = NULL WHERE id = ?', [$playerId]);
        }
        $this->conn->execute('DELETE FROM blacklist WHERE player_id = ?', [$playerId]);
        $this->clearCaches();
    }

    /** Removes one entry of the list (the player keeps their status) */
    public function remove(int $entryId): void
    {
        $this->conn->execute('DELETE FROM blacklist WHERE id = ?', [$entryId]);
    }

    /**
     * After an import: blacklists every profile not yet blacklisted that uses
     * a listed pubkey. Returns [[id, name, source name], ...].
     */
    public function apply(): array
    {
        $rows = $this->conn->execute(
            "SELECT DISTINCT p.id, p.name, src.name AS source
             FROM players p
             LEFT JOIN player_pubkeys pk ON pk.player_id = p.id
             JOIN blacklist b ON b.kind = 'pubkey' AND (b.value = p.pubkey OR b.value = pk.pubkey)
             LEFT JOIN players src ON src.id = b.player_id
             WHERE COALESCE(p.admin_status, '') <> ?", [self::BLACKLISTED])->fetchAll('assoc');
        $done = [];
        foreach ($rows as $r) {
            if (isset($done[$r['id']])) {
                continue;
            }
            $this->set($r['id'], self::BLACKLISTED, 'Same AssaultCube key as ' . ($r['source'] ?? 'a blacklisted player'));
            $done[$r['id']] = $r;
        }

        return array_values($done);
    }

    /**
     * Profiles on a blacklisted IP that the admin has not decided on yet
     * (not blacklisted, not untracked by the admin, not reviewed 'ok').
     */
    public function ipMatches(): array
    {
        return $this->conn->execute(
            "SELECT p.id, p.name, p.country, p.picture, p.ip, p.first_seen, p.last_seen,
                    src.id AS source_id, src.name AS source,
                    (SELECT COUNT(*) FROM player_stats_per_game s WHERE s.player_id = p.id) AS games
             FROM blacklist b
             JOIN players p ON p.ip = b.value AND p.id <> COALESCE(b.player_id, '')
             LEFT JOIN players src ON src.id = b.player_id
             LEFT JOIN player_reviews r ON r.player_id = p.id
             WHERE b.kind = 'ip' AND p.admin_status IS NULL AND COALESCE(r.status, '') <> 'ok'
             ORDER BY p.last_seen DESC")->fetchAll('assoc');
    }

    /** An IP match checked by the admin: not the same person (shows no more) */
    public function dismiss(string $playerId, string $note): void
    {
        $this->conn->execute('REPLACE INTO player_reviews (player_id, status, note, reviewed_at) VALUES (?, ?, ?, ?)',
            [$playerId, 'ok', mb_substr($note, 0, 500), date('Y-m-d H:i:s')]);
    }

    /** Every entry of the list with its player */
    public function entries(): array
    {
        return $this->conn->execute(
            'SELECT b.*, p.name, p.country, p.admin_status FROM blacklist b
             LEFT JOIN players p ON p.id = b.player_id
             ORDER BY b.created DESC, b.id DESC')->fetchAll('assoc');
    }

    /** Players the admin has blacklisted or untracked */
    public function players(): array
    {
        return $this->conn->execute(
            'SELECT p.id, p.name, p.country, p.picture, p.ip, p.first_seen, p.last_seen, p.admin_status, p.admin_note, p.modified,
                    (SELECT COUNT(*) FROM player_stats_per_game s WHERE s.player_id = p.id) AS games
             FROM players p
             WHERE p.admin_status IS NOT NULL
             ORDER BY p.admin_status, p.last_seen DESC')->fetchAll('assoc');
    }

    private function clearCaches(): void
    {
        Cache::clear('rankings');
        Cache::clear();
    }
}
