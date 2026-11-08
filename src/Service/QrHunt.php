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
    public const LEVELS = [1 => 'Explorer', 2 => 'Temple Raider', 3 => 'Relic Hunter', 4 => 'Castaway', 5 => 'Dharma Recruit', 6 => 'Island Survivor',
        7 => 'Cube Climber', 8 => 'Passage Seeker', 9 => 'Chamber Keeper', 10 => 'Cube Master'];

    /**
     * What a player's points unlock: the one place that decides it (reorder
     * here, the features follow). key => [points, title, what it does, built].
     * Unbuilt ones are shown greyed as a preview of what is coming.
     */
    public const UNLOCKS = [
        'card' => [1, 'Your share card', 'Pick the colour of your share card and add a tagline after your name.', true],
        'bio' => [2, 'About me', 'A short text about yourself on your player page.', true],
        'nemesis' => [3, 'Nemesis advanced stats', 'Your duels in detail: per weapon, per map and over time.', true],
        'stats' => [4, 'Special stats', 'Accuracy and K/D over time, your best and worst maps, your hours.', true],
        'avatars1' => [5, 'New avatars', 'A set of avatars only explorers can pick.', true],
        'weapons' => [6, 'Weapon colours', 'Tint your weapon icons on your profile and in the rankings.', true],
        'upload' => [7, 'Your own avatar', 'Upload your own picture.', true],
        'name' => [8, 'Name style', 'A coloured frame around your name in the rankings.', true],
        'banner' => [9, 'Profile banner', 'One of our maps behind your profile header.', true],
        'avatars2' => [10, 'The last avatars', 'The final avatar set and a gold badge: all codes found.', true],
    ];

    /** Share card colours (a fixed palette, so every card stays readable): key => [label, rgb] */
    public const CARD_COLORS = [
        'blue' => ['Blue', [59, 130, 246]], 'red' => ['Red', [239, 68, 68]], 'orange' => ['Orange', [249, 115, 22]],
        'gold' => ['Gold', [234, 179, 8]], 'green' => ['Green', [34, 197, 94]], 'teal' => ['Teal', [20, 184, 166]],
        'purple' => ['Purple', [168, 85, 247]], 'pink' => ['Pink', [236, 72, 153]],
    ];

    /** Weapon icon tints (unlock 'weapons'), bright enough for the dark pages: key => [label, rgb] */
    public const WEAPON_COLORS = [
        'gold' => ['Gold', [250, 204, 21]], 'orange' => ['Orange', [251, 146, 60]], 'red' => ['Red', [248, 113, 113]],
        'pink' => ['Pink', [244, 114, 182]], 'purple' => ['Purple', [192, 132, 252]], 'blue' => ['Blue', [96, 165, 250]],
        'cyan' => ['Cyan', [34, 211, 238]], 'green' => ['Green', [74, 222, 128]], 'lime' => ['Lime', [190, 242, 100]],
    ];

    /** Avatars only explorers can pick (unlock 'avatars1'): img/players/explorer-NN.jpg, made by bin/cake explorer_avatars */
    public const EXPLORER_AVATAR_PREFIX = 'explorer-';

    /** The last avatars (unlock 'avatars2'): img/players/legend-NN.jpg, made by bin/cake explorer_avatars --legend */
    public const LEGEND_AVATAR_PREFIX = 'legend-';

    /**
     * A player's own picture (unlock 'upload'): img/players/upload-<random>.jpg,
     * re-encoded by AvatarUpload. Never in the avatar lists; deleted when replaced.
     */
    public const UPLOAD_AVATAR_PREFIX = 'upload-';

    /** Name frame colours (unlock 'name'), bright enough for the dark pages: key => [label, rgb] */
    public const NAME_COLORS = [
        'gold' => ['Gold', [250, 204, 21]], 'orange' => ['Orange', [251, 146, 60]], 'red' => ['Red', [248, 113, 113]],
        'pink' => ['Pink', [244, 114, 182]], 'purple' => ['Purple', [192, 132, 252]], 'blue' => ['Blue', [96, 165, 250]],
        'cyan' => ['Cyan', [34, 211, 238]], 'green' => ['Green', [74, 222, 128]], 'white' => ['White', [244, 244, 245]],
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
        $row = ConnectionManager::get('default')->execute('SELECT card_color, card_tagline, bio, weapon_color, name_color, banner_map FROM player_profiles WHERE player_id = ?', [$playerId])->fetch('assoc');

        return $row ?: ['card_color' => null, 'card_tagline' => null, 'bio' => null, 'weapon_color' => null, 'name_color' => null, 'banner_map' => null];
    }

    /** Saves some of a player's profile fields (others stay) */
    public function saveProfile(string $playerId, array $fields): void
    {
        $fields = array_intersect_key($fields, ['card_color' => 1, 'card_tagline' => 1, 'bio' => 1, 'weapon_color' => 1, 'name_color' => 1, 'banner_map' => 1]);
        $p = array_merge($this->profile($playerId), $fields);
        ConnectionManager::get('default')->execute(
            'INSERT INTO player_profiles (player_id, card_color, card_tagline, bio, weapon_color, name_color, banner_map, modified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE card_color = VALUES(card_color), card_tagline = VALUES(card_tagline), bio = VALUES(bio),
                weapon_color = VALUES(weapon_color), name_color = VALUES(name_color), banner_map = VALUES(banner_map), modified = VALUES(modified)',
            [$playerId, $p['card_color'], $p['card_tagline'], $p['bio'], $p['weapon_color'], $p['name_color'], $p['banner_map'], date('Y-m-d H:i:s')]);
    }

    /**
     * Weapon icon tints for a list of players in one query: only players who
     * picked a colour and have the 'weapons' level now (a retired code can
     * drop a level, so it is checked on every read).
     *
     * @param list<string> $playerIds
     * @return array<string, array{0:int,1:int,2:int}> player id => rgb
     */
    public static function weaponColors(array $playerIds): array
    {
        $playerIds = array_values(array_unique(array_filter(array_map('strval', $playerIds))));
        if (!$playerIds) {
            return [];
        }
        $rows = ConnectionManager::get('default')->execute(
            'SELECT pp.player_id, pp.weapon_color FROM player_profiles pp
             WHERE pp.player_id IN (' . implode(',', array_fill(0, count($playerIds), '?')) . ') AND pp.weapon_color IS NOT NULL
               AND (SELECT COUNT(*) FROM qr_finds f WHERE f.player_id = pp.player_id) >= ' . (int)self::UNLOCKS['weapons'][0],
            $playerIds)->fetchAll('assoc');
        $out = [];
        foreach ($rows as $r) {
            if (isset(self::WEAPON_COLORS[$r['weapon_color']])) {
                $out[$r['player_id']] = self::WEAPON_COLORS[$r['weapon_color']][1];
            }
        }

        return $out;
    }

    /**
     * Name frame and gold badge for a list of players in one query: the frame
     * colour (unlock 'name', null when none is picked) and the gold badge of
     * the players with every code (unlock 'avatars2'). Only players who have
     * one of the two now are in the result (a retired code can drop a level).
     *
     * @param list<string> $playerIds
     * @return array<string, array{frame: ?array{0:int,1:int,2:int}, gold: bool}> player id => style
     */
    public static function nameStyles(array $playerIds): array
    {
        $playerIds = array_values(array_unique(array_filter(array_map('strval', $playerIds))));
        if (!$playerIds) {
            return [];
        }
        $min = min(self::UNLOCKS['name'][0], self::UNLOCKS['avatars2'][0]);
        $rows = ConnectionManager::get('default')->execute(
            'SELECT f.player_id, COUNT(*) n, MAX(pp.name_color) name_color FROM qr_finds f
             LEFT JOIN player_profiles pp ON pp.player_id = f.player_id
             WHERE f.player_id IN (' . implode(',', array_fill(0, count($playerIds), '?')) . ')
             GROUP BY f.player_id HAVING n >= ' . (int)$min,
            $playerIds)->fetchAll('assoc');
        $out = [];
        foreach ($rows as $r) {
            $level = (int)$r['n'];
            $frame = self::unlocked($level, 'name') ? (self::NAME_COLORS[(string)$r['name_color']][1] ?? null) : null;
            $gold = self::unlocked($level, 'avatars2');
            if ($frame || $gold) {
                $out[$r['player_id']] = ['frame' => $frame, 'gold' => $gold];
            }
        }

        return $out;
    }

    /** Inline CSS of a name frame (unlock 'name'): a coloured border and a faint fill; '' for none */
    public static function nameFrameCss(?array $rgb): string
    {
        if (!$rgb) {
            return '';
        }
        $c = implode(',', array_map('intval', $rgb));

        return "border-color: rgb($c); background-color: rgba($c, .14);";
    }

    /**
     * Maps a player can put behind their profile header (unlock 'banner'):
     * the maps we track that have a screenshot img/maps/<name>.jpg.
     *
     * @return array<string, string> map name => picture url, sorted by name
     */
    public static function bannerMaps(): array
    {
        $files = [];
        foreach (glob(WWW_ROOT . 'img' . DS . 'maps' . DS . '*.jpg') ?: [] as $file) {
            $name = basename($file, '.jpg');
            // map names only: not the dated screenshots, not the second pictures (<map>-2.jpg)
            if (preg_match('/^[A-Za-z][\w.]*(?:-\w+)*$/', $name) && !preg_match('/-2$/', $name) && $name !== 'placeholder') {
                $files[$name] = true;
            }
        }
        if (!$files) {
            return [];
        }
        $names = array_keys($files);
        $known = ConnectionManager::get('default')->execute(
            'SELECT name FROM maps WHERE name IN (' . implode(',', array_fill(0, count($names), '?')) . ')', $names)->fetchAll('num');
        $out = [];
        foreach ($known as [$name]) {
            if (isset($files[$name])) {
                $out[$name] = \App\View\Helper\LayoutHelper::mapUrl($name . '.jpg');
            }
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    /** An explorer avatar (unlock 'avatars1')? By file name in img/players. */
    public static function isExplorerAvatar(?string $picture): bool
    {
        return $picture !== null && str_starts_with($picture, self::EXPLORER_AVATAR_PREFIX);
    }

    /** One of the last avatars (unlock 'avatars2')? By file name in img/players. */
    public static function isLegendAvatar(?string $picture): bool
    {
        return $picture !== null && str_starts_with($picture, self::LEGEND_AVATAR_PREFIX);
    }

    /** A player's own uploaded picture (unlock 'upload')? By file name in img/players. */
    public static function isUploadedAvatar(?string $picture): bool
    {
        return $picture !== null && str_starts_with($picture, self::UPLOAD_AVATAR_PREFIX);
    }

    /** Any QR hunt picture (explorer, legend, upload): kept out of the plain avatar list and the random backgrounds */
    public static function isHuntAvatar(?string $picture): bool
    {
        return self::isExplorerAvatar($picture) || self::isLegendAvatar($picture) || self::isUploadedAvatar($picture);
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
