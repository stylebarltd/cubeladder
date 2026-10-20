<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Who is watching a server's live match page right now.
 *
 * Each open page makes a random id once and sends it with its 5-second
 * update (/live/game_status/<key>?v=<id>). Per server we keep the ids with
 * the time they were last seen and, for a logged-in player, their player id,
 * in one small locked file (tmp/live_viewers/<key>.json); an id not seen for
 * WINDOW seconds has left. Guests stay anonymous: no IPs, only a random
 * number per open tab.
 */
class LiveViewers
{
    /** Seconds after the last update before a viewer counts as gone (3 missed polls) */
    public const WINDOW = 15;

    /**
     * Notes the viewer (when an id is given) and returns who is watching.
     *
     * @return array{count:int, players:list<string>} players = ids of logged-in
     *   players, each once; count = those players + guest tabs
     */
    public function seen(string $serverKey, ?string $viewerId, ?string $playerId = null): array
    {
        $dir = TMP . 'live_viewers' . DS;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . preg_replace('/[^a-zA-Z0-9_-]/', '_', $serverKey) . '.json';
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return ['count' => 0, 'players' => []];
        }
        flock($fh, LOCK_EX);
        $seen = json_decode((string)stream_get_contents($fh), true);
        $seen = is_array($seen) ? $seen : [];

        $now = time();
        $seen = array_filter($seen, fn($v) => is_array($v) && $now - (int)($v['t'] ?? 0) < self::WINDOW);
        if ($viewerId !== null && preg_match('/^[a-zA-Z0-9]{8,32}$/', $viewerId)) {
            $seen[$viewerId] = ['t' => $now, 'p' => $playerId];
        }

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string)json_encode($seen));
        flock($fh, LOCK_UN);
        fclose($fh);

        // one player with two tabs open is one viewer
        $players = array_values(array_unique(array_filter(array_column($seen, 'p'))));
        $guests = count(array_filter($seen, fn($v) => empty($v['p'])));

        return ['count' => count($players) + $guests, 'players' => $players];
    }
}
