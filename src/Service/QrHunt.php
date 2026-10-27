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

    /**
     * What a player's points unlock: the one place that decides it (reorder
     * here, the features follow). key => [points, title, what it does, built].
     * Unbuilt ones are shown greyed as a preview of what is coming.
     */
    public const UNLOCKS = [
        'card' => [1, 'Your share card', 'Pick the colour of your share card and add a tagline after your name.', true],
        'bio' => [2, 'About me', 'A short text about yourself on your player page.', true],
        'nemesis' => [3, 'Nemesis advanced stats', 'Your duels in detail: per weapon, per map and over time.', false],
        'stats' => [4, 'Special stats', 'Accuracy and K/D over time, your best and worst maps, your hours.', false],
        'avatars1' => [5, 'New avatars', 'A set of avatars only explorers can pick.', false],
        'weapons' => [6, 'Weapon colours', 'Tint your weapon icons on your profile and in the rankings.', false],
        'upload' => [7, 'Your own avatar', 'Upload your own picture.', false],
        'name' => [8, 'Name style', 'A coloured frame around your name in the rankings.', false],
        'banner' => [9, 'Profile banner', 'One of our maps behind your profile header.', false],
        'avatars2' => [10, 'The last avatars', 'The final avatar set and a gold badge: all codes found.', false],
    ];

    /** Share card colours (a fixed palette, so every card stays readable): key => [label, rgb] */
    public const CARD_COLORS = [
        'blue' => ['Blue', [59, 130, 246]], 'red' => ['Red', [239, 68, 68]], 'orange' => ['Orange', [249, 115, 22]],
        'gold' => ['Gold', [234, 179, 8]], 'green' => ['Green', [34, 197, 94]], 'teal' => ['Teal', [20, 184, 166]],
        'purple' => ['Purple', [168, 85, 247]], 'pink' => ['Pink', [236, 72, 153]],
    ];

    /** Lengths of the texts a player writes */
    public const TAGLINE_MAX = 40;
    public const BIO_MAX = 160;

    public static function unlocked(int $level, string $key): bool
    {
        return isset(self::UNLOCKS[$key]) && $level >= self::UNLOCKS[$key][0];
    }

    /**
     * A player's text cleaned for display: one line, no control characters,
     * cut to $max. Returns null and an error when it contains a link (no
     * advertising, no phishing on the ladder).
     *
     * @return array{0: ?string, 1: ?string} [text, error]
     */
    public static function cleanText(string $text, int $max): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text)));
        if ($text === '') {
            return [null, null];
        }
        if (preg_match('~(https?:|www\.|://|\b[a-z0-9-]+\.(?:com|net|org|io|gg|ru|de|xyz|me|tv|ovh|co|info|biz|app|link|ly)\b)~i', $text)) {
            return [null, 'No links, please.'];
        }

        return [mb_substr($text, 0, $max), null];
    }

    /** A player's profile settings (player_profiles), or defaults */
    public function profile(string $playerId): array
    {
        $row = ConnectionManager::get('default')->execute('SELECT card_color, card_tagline, bio FROM player_profiles WHERE player_id = ?', [$playerId])->fetch('assoc');

        return $row ?: ['card_color' => null, 'card_tagline' => null, 'bio' => null];
    }

    /** Saves some of a player's profile fields (others stay) */
    public function saveProfile(string $playerId, array $fields): void
    {
        $fields = array_intersect_key($fields, ['card_color' => 1, 'card_tagline' => 1, 'bio' => 1]);
        $p = array_merge($this->profile($playerId), $fields);
        ConnectionManager::get('default')->execute(
            'INSERT INTO player_profiles (player_id, card_color, card_tagline, bio, modified) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE card_color = VALUES(card_color), card_tagline = VALUES(card_tagline), bio = VALUES(bio), modified = VALUES(modified)',
            [$playerId, $p['card_color'], $p['card_tagline'], $p['bio'], date('Y-m-d H:i:s')]);
    }

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
