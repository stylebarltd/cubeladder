<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * Who uses the site (admin "Visitors"), without storing IPs.
 *
 * Every page asks the browser to ping /visits/hit once it has loaded
 * (layout JS), so crawlers that run no JavaScript are not counted. A logged-in
 * player counts by their profile; a guest by a hash of IP + browser with a
 * random salt that is replaced every day (UTC), so guests can be counted per
 * day but not followed from one day to the next. Admins are not counted.
 *
 * Tables: site_visitors (visitor and day: views, phone, where they came
 * from), site_page_views (page and day). CleanUp drops rows after KEEP_DAYS.
 */
class SiteVisits
{
    public const KEEP_DAYS = 400;
    /** Views of one visitor per day that still count (a stuck tab, a script) */
    private const MAX_VIEWS = 1000;
    private const BOTS = '/bot|crawl|spider|slurp|curl|wget|python|headless|preview|scan|fetch|http|java|okhttp/i';

    /** Where a visitor came from: an outside referrer, simplified */
    public static function source(string $referrer, string $ownHost): string
    {
        $host = strtolower((string)parse_url($referrer, PHP_URL_HOST));
        $host = preg_replace('/^(www|m|l|lm)\./', '', $host);
        if ($host === '' || $host === strtolower(preg_replace('/^www\./', '', $ownHost))) {
            return 'direct';
        }

        return match (true) {
            (bool)preg_match('/(^|\.)discord(app)?\.(com|gg)$/', $host) => 'Discord',
            (bool)preg_match('/(^|\.)(google|bing|duckduckgo|yahoo|yandex|ecosia|qwant|startpage|brave)\./', $host) => 'Search',
            (bool)preg_match('/(^|\.)(reddit\.com|t\.co|x\.com|twitter\.com|facebook\.com|youtube\.com)$/', $host) => $host,
            default => substr($host, 0, 60),
        };
    }

    /** The page as a pattern: ids become :id, at most two parts ("players/view") */
    public static function page(string $path): ?string
    {
        $path = trim((string)parse_url($path, PHP_URL_PATH), '/');
        if ($path === '') {
            return 'home';
        }
        if (!preg_match('#^[\w./%:-]{1,300}$#', $path)) {
            return null;
        }
        $parts = array_slice(explode('/', strtolower($path)), 0, 2);
        $parts = array_map(fn($p) => preg_match('/^([0-9a-f-]{36}|\d+)$/', $p) ? ':id' : $p, $parts);

        return substr(implode('/', $parts), 0, 60);
    }

    /**
     * Counts one page view.
     *
     * @param string|null $playerId the logged-in player, null for a guest
     */
    public function hit(string $page, string $referrer, string $ownHost, ?string $playerId, string $ip, string $userAgent): bool
    {
        if ($userAgent === '' || preg_match(self::BOTS, $userAgent)) {
            return false;
        }
        $visitor = $playerId !== null
            ? substr(hash('sha256', 'player ' . $playerId), 0, 16)
            : substr(hash('sha256', $this->salt() . "\n" . $ip . "\n" . $userAgent), 0, 16);
        $now = gmdate('Y-m-d H:i:s');
        $day = substr($now, 0, 10);
        $phone = (bool)preg_match('/Mobi|Android|iPhone|iPad/i', $userAgent);
        $conn = ConnectionManager::get('default');

        $views = $conn->execute('SELECT views FROM site_visitors WHERE day = ? AND visitor = ?', [$day, $visitor])->fetchColumn(0);
        if ($views !== false && (int)$views >= self::MAX_VIEWS) {
            return false;
        }
        $conn->execute(
            'INSERT INTO site_visitors (day, visitor, player_id, phone, source, views, first_seen, last_seen)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE views = views + 1, last_seen = VALUES(last_seen)',
            [$day, $visitor, $playerId, (int)$phone, self::source($referrer, $ownHost), $now, $now]);
        $conn->execute(
            'INSERT INTO site_page_views (day, page, views) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE views = views + 1',
            [$day, $page]);

        return true;
    }

    /** Today's random salt for the guest hash; yesterday's is overwritten (and so gone) */
    private function salt(): string
    {
        $file = TMP . 'visit_salt';
        $today = gmdate('Y-m-d');
        $saved = is_file($file) ? explode(' ', (string)file_get_contents($file), 2) : [];
        if (($saved[0] ?? '') === $today && !empty($saved[1])) {
            return $saved[1];
        }
        $salt = bin2hex(random_bytes(16));
        file_put_contents($file, $today . ' ' . $salt, LOCK_EX);

        return $salt;
    }

    /** The numbers for the admin page: the last $days days */
    public function report(int $days = 30): array
    {
        $conn = ConnectionManager::get('default');
        $q = fn(string $sql, array $p = []) => $conn->execute($sql, $p)->fetchAll('assoc');
        $since = gmdate('Y-m-d', time() - ($days - 1) * 86400);

        $perDay = [];
        for ($t = strtotime($since . ' UTC'); $t <= time(); $t += 86400) {
            $perDay[gmdate('Y-m-d', $t)] = ['visitors' => 0, 'players' => 0, 'guests' => 0, 'phone' => 0, 'views' => 0];
        }
        foreach ($q('SELECT day, COUNT(*) visitors, SUM(player_id IS NOT NULL) players, SUM(player_id IS NULL) guests,
                            SUM(phone) phone, SUM(views) views
                     FROM site_visitors WHERE day >= ? GROUP BY day', [$since]) as $r) {
            $perDay[$r['day']] = array_map('intval', array_diff_key($r, ['day' => 1]));
        }

        $pages = $q('SELECT page, SUM(views) views FROM site_page_views WHERE day >= ? GROUP BY page ORDER BY views DESC LIMIT 25', [$since]);
        $sources = $q("SELECT source, COUNT(*) visitors FROM site_visitors WHERE day >= ? GROUP BY source ORDER BY visitors DESC LIMIT 15", [$since]);
        $players = $q('SELECT v.player_id, p.name, p.country, COUNT(*) days, SUM(v.views) views, MAX(v.last_seen) last_seen
                       FROM site_visitors v INNER JOIN players p ON p.id = v.player_id
                       WHERE v.day >= ? GROUP BY v.player_id, p.name, p.country ORDER BY days DESC, views DESC', [$since]);

        return [
            'days' => $days,
            'perDay' => array_reverse($perDay, true),
            'pages' => $pages,
            'sources' => $sources,
            'players' => $players,
            'returning' => count(array_filter($players, fn($p) => (int)$p['days'] > 1)),
        ];
    }
}
