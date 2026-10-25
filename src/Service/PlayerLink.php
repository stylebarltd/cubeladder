<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * Recognising a player whose browser IP differs from the IP they play from
 * (carrier-grade NAT, a VPN on one side, a phone): the page gives the browser a
 * one-time code ("cl-48213"), the player says it in the chat of a game on our
 * servers, the next log import (process_logs.sh, every 10 minutes) writes that
 * chat line to events with the speaker's player id, and check() links the code
 * to that player. The browser keeps the code in a cookie; PlayController then
 * sets the usual identity cookie.
 *
 * The speaker is the import's player for that name in that game (pubkey first,
 * see AcLogParser::getOrCreatePlayer), so someone using another player's name
 * with their own login maps to their own row, not the other player's.
 */
class PlayerLink
{
    /** a code waits this long for its chat line */
    public const VALID_HOURS = 3;

    /** New code for a browser (never reused) */
    public function issue(): string
    {
        $conn = ConnectionManager::get('default');
        $from = (int)$conn->execute('SELECT COALESCE(MAX(id), 0) FROM events')->fetchColumn(0);
        for ($i = 0; $i < 20; $i++) {
            $code = 'cl-' . random_int(10000, 99999);
            try {
                $conn->execute('INSERT INTO link_codes (code, from_event_id, created) VALUES (?, ?, ?)', [$code, $from, date('Y-m-d H:i:s')]);

                return $code;
            } catch (\Throwable $e) {
                // taken (primary key): try another
            }
        }
        throw new \RuntimeException('no free link code');
    }

    /** The code is known, unused and not expired */
    public function open(?string $code): bool
    {
        if (!$code || !preg_match('/^cl-\d{5}$/', $code)) {
            return false;
        }
        $row = ConnectionManager::get('default')->execute('SELECT created, player_id FROM link_codes WHERE code = ?', [$code])->fetch('assoc');

        return $row && $row['player_id'] === null && strtotime($row['created']) > time() - self::VALID_HOURS * 3600;
    }

    /**
     * The player who said the code in a game chat, or null (not yet / unknown /
     * expired). Links the code on the first match.
     *
     * @return array{id:string, name:string}|null
     */
    public function check(?string $code): ?array
    {
        if (!$code || !preg_match('/^cl-\d{5}$/', $code)) {
            return null;
        }
        $conn = ConnectionManager::get('default');
        $row = $conn->execute('SELECT code, from_event_id, created, player_id FROM link_codes WHERE code = ?', [$code])->fetch('assoc');
        if (!$row) {
            return null;
        }
        if ($row['player_id'] === null) {
            if (strtotime($row['created']) < time() - self::VALID_HOURS * 3600) {
                return null;
            }
            // chat lines since the code was issued (primary-key range, cheap); the code anywhere in the message,
            // also "cl 48213" / "CL48213" as players type it
            $digits = substr($code, 3);
            $hit = null;
            $rows = $conn->execute(
                "SELECT e.actor_id, e.details FROM events e INNER JOIN players p ON p.id = e.actor_id
                 WHERE e.id > ? AND e.type = 'chat' AND e.actor_id IS NOT NULL AND e.details LIKE ?
                 ORDER BY e.id LIMIT 10",
                [(int)$row['from_event_id'], '%' . $digits . '%'])->fetchAll('assoc');
            foreach ($rows as $r) {
                // the digits in the message itself, not inside a longer number or the name
                $msg = (string)(json_decode((string)$r['details'], true)['msg'] ?? '');
                if (preg_match('/(?<!\d)' . $digits . '(?!\d)/', $msg)) {
                    $hit = $r['actor_id'];
                    break;
                }
            }
            if (!$hit) {
                return null;
            }
            $conn->execute('UPDATE link_codes SET player_id = ?, linked = ? WHERE code = ? AND player_id IS NULL', [$hit, date('Y-m-d H:i:s'), $code]);
            $row['player_id'] = $hit;
        }
        $name = $conn->execute('SELECT name FROM players WHERE id = ?', [$row['player_id']])->fetchColumn(0);

        return $name === false ? null : ['id' => (string)$row['player_id'], 'name' => (string)$name];
    }
}
