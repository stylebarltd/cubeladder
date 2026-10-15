<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

/**
 * Keeps what the live loop sees (extinfo, every 15 s) and the logs don't:
 * each player's damage and "shot damage" (the damage their shots could have
 * done - accuracy = damage / shot damage) and the gun they hold.
 *
 * One live_player_runs row per player and game: a poll continues the
 * player's run on that server, map and mode while the numbers only grow; when
 * they drop (a new game - the server starts everyone at 0) or the player was
 * gone for RUN_GAP seconds, a new run starts. bin/cake link_live_stats then
 * puts the runs on the log's games.
 *
 * In match mode the server sends 0 for all of it - nothing is recorded then.
 */
class LiveStatsRecorder
{
    /** Seconds without a poll after which a player's run is over */
    public const RUN_GAP = 120;
    /** Runs are kept this many days (linked within minutes) */
    public const KEEP_DAYS = 14;

    /**
     * @param array $servers AcExtInfoService results keyed by server key (DiscordLiveService::poll())
     */
    public function record(array $servers, ?int $now = null): int
    {
        $now ??= time();
        $conn = ConnectionManager::get('default');
        $ladder = Configure::read('Ladder.servers') ?: [];
        $stamp = gmdate('Y-m-d H:i:s', $now);
        $written = 0;

        foreach ($servers as $key => $s) {
            // ladder servers only (clan match servers have 'live' => false)
            if (empty($s['online']) || empty($s['players']) || empty($s['map']) || !isset($ladder[$key]) || ($ladder[$key]['live'] ?? true) === false) {
                continue;
            }
            $mode = AcExtInfoService::MODE_CODES[(int)$s['mode']] ?? (string)$s['mode'];
            $open = [];
            foreach ($conn->execute(
                'SELECT id, name, map, mode, damage, shot_damage, guns FROM live_player_runs
                 WHERE server_name = ? AND last_seen >= ? ORDER BY id',
                [$key, gmdate('Y-m-d H:i:s', $now - self::RUN_GAP)])->fetchAll('assoc') as $r) {
                $open[$r['name']] = $r; // the latest run per name wins (ordered by id)
            }

            foreach ($s['players'] as $p) {
                // "unarmed" is the default name of countless players (as AcLogParser)
                if (!empty($p['is_spectator']) || ($p['shotdamage'] ?? null) === null || strtolower((string)$p['name']) === 'unarmed') {
                    continue;
                }
                $damage = (int)$p['damage'];
                $shot = (int)$p['shotdamage'];
                if ($shot === 0 && $damage === 0) {
                    continue; // match mode, or nothing fired yet
                }
                $run = $open[$p['name']] ?? null;
                $same = $run && $run['map'] === $s['map'] && $run['mode'] === $mode
                    && $shot >= (int)$run['shot_damage'] && $damage >= (int)$run['damage'];
                $guns = $same ? (json_decode((string)$run['guns'], true) ?: []) : [];
                $gun = (string)($p['gun_name'] ?? $p['gun']);
                $guns[$gun] = ($guns[$gun] ?? 0) + 1;

                if ($same) {
                    $conn->execute(
                        'UPDATE live_player_runs SET last_seen = ?, damage = ?, shot_damage = ?, frags = ?, guns = ? WHERE id = ?',
                        [$stamp, $damage, $shot, (int)$p['frags'], json_encode($guns), $run['id']]);
                } else {
                    $conn->execute(
                        'INSERT INTO live_player_runs (server_name, map, mode, name, first_seen, last_seen, damage, shot_damage, frags, guns)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$key, $s['map'], $mode, $p['name'], $stamp, $stamp, $damage, $shot, (int)$p['frags'], json_encode($guns)]);
                }
                $written++;
            }
        }

        // now and then: forget old runs
        if (random_int(1, 200) === 1) {
            $conn->execute('DELETE FROM live_player_runs WHERE last_seen < ?', [gmdate('Y-m-d H:i:s', $now - self::KEEP_DAYS * 86400)]);
        }

        return $written;
    }
}
