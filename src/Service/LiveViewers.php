<?php
declare(strict_types=1);

namespace App\Service;

/**
 * How many people are watching a server's live match page right now.
 *
 * Each open page makes a random id once and sends it with its 5-second
 * update (/live/game_status/<key>?v=<id>). Per server we keep the ids with
 * the time they were last seen, in one small locked file
 * (tmp/live_viewers/<key>.json); an id not seen for WINDOW seconds has left.
 * No IPs, no logins - only a random number per open tab.
 */
class LiveViewers
{
    /** Seconds after the last update before a viewer counts as gone (3 missed polls) */
    public const WINDOW = 15;

    /**
     * Notes the viewer (when an id is given) and returns the current count.
     */
    public function seen(string $serverKey, ?string $viewerId): int
    {
        $dir = TMP . 'live_viewers' . DS;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . preg_replace('/[^a-zA-Z0-9_-]/', '_', $serverKey) . '.json';
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return 0;
        }
        flock($fh, LOCK_EX);
        $seen = json_decode((string)stream_get_contents($fh), true);
        $seen = is_array($seen) ? $seen : [];

        $now = time();
        $seen = array_filter($seen, fn($t) => is_int($t) && $now - $t < self::WINDOW);
        if ($viewerId !== null && preg_match('/^[a-zA-Z0-9]{8,32}$/', $viewerId)) {
            $seen[$viewerId] = $now;
        }

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string)json_encode($seen));
        flock($fh, LOCK_UN);
        fclose($fh);

        return count($seen);
    }
}
