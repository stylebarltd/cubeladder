<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;

class LayoutHelper extends Helper
{
    /** Shown for maps without a screenshot */
    public const NO_MAP_IMAGE = '/img/bullet.jpg';
    public const NO_MAP_THUMB = '/img/bullet-thumb.jpg';

    /**
     * URL of the map's screenshot, or the bullet artwork. Maps can have a
     * second picture (<map>-2.jpg): with a $seed (a game id) the game shows
     * picture 1 or 2 - always the same one for the same game.
     */
    public function mapImage(?string $mapName, ?string $seed = null): string
    {
        $file = $mapName ? self::mapPicture($mapName, $seed) : null;

        return $file !== null ? self::mapUrl($file) : self::NO_MAP_IMAGE;
    }

    /**
     * URL of a file in webroot/img/maps (or its thumbs/) with the file's
     * date as version: a replaced picture gets a new URL, so browsers and
     * Discord load the new one instead of their cached copy.
     */
    public static function mapUrl(string $file, bool $thumb = false): string
    {
        $dir = 'img/maps/' . ($thumb ? 'thumbs/' : '');
        $mtime = @filemtime(WWW_ROOT . $dir . $file);

        return '/' . $dir . rawurlencode($file) . ($mtime ? '?v=' . base_convert((string)$mtime, 10, 36) : '');
    }

    /**
     * File name (in webroot/img/maps) of the map's picture - picture 1, or
     * for a seed (game id) picture 1 or 2 when the map has two - or null.
     */
    public static function mapPicture(string $mapName, ?string $seed = null): ?string
    {
        if ($seed !== null && crc32($seed) % 2 === 1 && is_file(WWW_ROOT . 'img/maps/' . $mapName . '-2.jpg')) {
            return $mapName . '-2.jpg';
        }

        return is_file(WWW_ROOT . 'img/maps/' . $mapName . '.jpg') ? $mapName . '.jpg' : null;
    }

    /**
     * URL of the map's small thumbnail (bin/cake map_thumbs) - the full
     * screenshot when the thumb was not built yet, else the bullet thumb.
     */
    public function mapThumb(?string $mapName): string
    {
        if ($mapName && is_file(WWW_ROOT . 'img/maps/thumbs/' . $mapName . '.jpg')) {
            return self::mapUrl($mapName . '.jpg', true);
        }
        $image = $this->mapImage($mapName);

        return $image === self::NO_MAP_IMAGE ? self::NO_MAP_THUMB : $image;
    }

    /**
     * Message text as HTML: escaped, **bold** as <strong>, line breaks kept.
     */
    public function messageBody(string $body): string
    {
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', h($body));

        return nl2br($html);
    }

    /**
     * A Discord invite in a message: the invite URL, the **bold** title on the
     * first line and the text without both - or null when there is none.
     *
     * @return array{url: string, title: ?string, text: string}|null
     */
    public function discordInvite(string $body): ?array
    {
        if (!preg_match('#https?://(?:www\.)?discord(?:\.gg|(?:app)?\.com/invite)/[\w-]+#i', $body, $m)) {
            return null;
        }
        $title = null;
        $text = $body;
        if (preg_match('/^\*\*(.+?)\*\*\s*/', $text, $t)) {
            $title = $t[1];
            $text = substr($text, strlen($t[0]));
        }
        // drop the link (and a "...: " lead-in left dangling before it)
        $text = trim(preg_replace('#[ \t]*' . preg_quote($m[0], '#') . '#', '', $text));
        $text = rtrim($text, " \t:");

        return ['url' => $m[0], 'title' => $title, 'text' => $text];
    }

    /**
     * Minutes as "12 h 34 min" / "45 min".
     */
    public function duration(int $minutes): string
    {
        return $minutes >= 60
            ? intdiv($minutes, 60) . ' h ' . ($minutes % 60) . ' min'
            : $minutes . ' min';
    }

    public function cleanMapName(string $mapName): string
    {
        $name = strtolower($mapName);

        // Remove common prefixes
        $name = preg_replace('/^ac[_-]/', '', $name);

        // Remove version numbers (3.0, 5.1 etc)
        $name = preg_replace('/[_-]\d+(\.\d+)?/', '', $name);

        // Remove standalone numbers at beginning or end
        $name = preg_replace('/^\d+[_-]?/', '', $name);
        $name = preg_replace('/[_-]?\d+$/', '', $name);

        // Remove gamemode words
        $name = preg_replace('/\b(ctf|tdm|dm)\b/', '', $name);

        // Replace separators with space
        $name = str_replace(['-', '_'], ' ', $name);

        // Remove extra spaces
        $name = preg_replace('/\s+/', ' ', $name);

        return ucwords(trim($name));
    }
    public function gameModeIcon(string|null $modeName, array $options = []): string
    {
        if($modeName == null) return '';

        $mode = strtolower(trim($modeName));

        $map = [
            'deathmatch' => 'dm',
            'team deathmatch' => 'tdm',
            'capture the flag' => 'ctf',
            'team capture the flag' => 'ctf',
            'hunt the flag' => 'htf',
            'keep the flag' => 'ktf',
            'team keep the flag' => 'tktf',
            'last swiss standing' => 'lss',
            'team last swiss standing' => 'tlss',
            'one shot, one kill' => 'osok',
            'team one shot one kill' => 'tosok',
            'pistol frenzy' => 'pf',
            'team pistol frenzy' => 'tpf',
            'survivor' => 'surv',
            'team survivor' => 'tsurv',
        ];

        $code = $map[$mode] ?? $mode;

        $icon = $this->iconFor($code);
        $label = strtoupper($code);


        return '<i class="' . $icon . ' mr-1"></i>
' . h($label);
    }

    protected function iconFor(string $code): string
    {
        $base = str_starts_with($code, 't') ? substr($code, 1) : $code;

        return match ($base) {
            'dm'   => 'fa-solid fa-skull-crossbones',
            'ctf'  => 'fa-solid fa-flag',
            'htf'  => 'fa-solid fa-flag-checkered',
            'ktf'  => 'fa-solid fa-shield-halved',
            'surv' => 'fa-solid fa-heart-pulse',
            'osok' => 'fa-solid fa-crosshairs',
            'pf'   => 'fa-solid fa-burst',
            'lss'  => 'fa-solid fa-trophy',
            default => 'fa-solid fa-circle-question',
        };
    }



    public function serverName($type): string
    {
        if(!$type){
            $type = 'unknown';
        }

        $serverName = [
            'acka-europa'    => '[aCKa] Europa',
            'acka-custom'   => '[aCKa] Custom',
            'acka-nostalgic'    => '[aCKa] Nostalgic',
            'acka-assault'   => '[aCKa] Assault',
            'chobbz-banana'    => 'Banana',
            'chobbz-potato'   => 'POTATO',
        ];

        $label = $serverName[$type];

        return $label;
    }

    public function gameMode($type): string
    {
if(!$type){
    $type = 'unknown';
}

        $gameMode = [
            'dm'    => 'deathmatch',
            'lss'   => 'last swiss standing',
            'osok'  => 'one shot, one kill',
            'pf'    => 'pistol frenzy',
            'surv'  => 'survivor',
            'htf'   => 'hunt the flag',
            'ktf'   => 'keep the flag',
            'tdm'   => 'team deathmatch',
            'tlss'  => 'team last swiss standing',
            'tosok' => 'team one shot one kill',
            'tpf'   => 'team pistol frenzy',
            'tsurv' => 'team survivor',
            'tktf'  => 'team keep the flag',
            'ctf'   => 'capture the flag',
            'unknown'   => 'unknown',
        ];

        $label = $gameMode[$type];

        //dd($gameMode['tktf']);

        return $label;
    }


    public function playerPicture($player = null, $achievementPlayers = []): string
    {

        if(empty($player->picture)){
            return "/img/acl.png";
        }
        return "/img/players/" . $player->picture;
    }

    /**
     * Display a country flag from ISO2 code
     * @param string $iso2 ISO2 country code
     * @return string HTML for the flag
     */
    public function flag($iso2): string
    {
        if(empty($iso2))return '';
        $iso2 = strtoupper($iso2);
        $offset = 0x1F1E6 - ord('A');

        return mb_chr(ord($iso2[0]) + $offset, 'UTF-8') .
            mb_chr(ord($iso2[1]) + $offset, 'UTF-8');
    }

//    public function weapon(array $stats, float $hybridThreshold = 0.2): array
//    {
//        $weapons = [
//            'rifle'    => 0,
//            'smg'      => 0,
//            'sniper'   => 0,
//            'shotgun'  => 0,
//            'carabine' => 0,
//            'grenade' => 0,
//            'knife' => 0,
//            'busted' => 0,
//        ];
//
//        $map = [
//            'shredded'   => 'rifle',
//            'sprayed'    => 'smg',
//            'punctured'  => 'sniper',
//            'headshot'   => 'sniper',
//            'splattered' => 'shotgun',
//            'peppered' => 'shotgun',
//            'picked_off' => 'carabine',
//            'gibbed' => 'grenade',
//            'slashed' => 'knife',
//            'busted' => 'pistol',
//        ];
//
//        foreach ($map as $field => $weapon) {
//            if (!empty($stats[$field])) {
//                $weapons[$weapon] += (int)$stats[$field];
//            }
//        }
//
//        arsort($weapons);
//
//        $values = array_values($weapons);
//        $keys   = array_keys($weapons);
//
//        $mainWeapon  = $keys[0];
//        $mainCount   = $values[0];
//        $secondWeapon = $keys[1];
//        $secondCount  = $values[1];
//
//        // No data at all
//        if ($mainCount === 0) {
//            return [
//                'label' => 'unknown',
//                'weapons' => [],
//            ];
//        }
//
//        // Hybrid detection
//        if ($secondCount > 0 && $secondCount >= ($mainCount * $hybridThreshold)) {
//            return [
//                'label' => $mainWeapon . ' / ' . $secondWeapon,
//                'weapons' => [
//                    $mainWeapon   => $mainCount,
//                    $secondWeapon => $secondCount,
//                ],
//                'hybrid' => true,
//            ];
//        }
//
//        return [
//            'label' => $mainWeapon,
//            'weapons' => [
//                $mainWeapon => $mainCount,
//            ],
//            'hybrid' => false,
//        ];
//    }
    public function weapon(
        array $stats,
        float $hybridThreshold = 0.65,
        float $specialThreshold = 0.2
    ): array {
        $primary = [
            'rifle'    => 0,
            'smg'      => 0,
            'sniper'   => 0,
            'shotgun'  => 0,
            'carabine' => 0,
        ];

        $special = [
            'knife'   => 0,
            'grenade' => 0,
        ];

        $map = [
            // primary
            'shredded'   => ['type' => 'primary', 'weapon' => 'rifle'],
            'sprayed'    => ['type' => 'primary', 'weapon' => 'smg'],
            'punctured'  => ['type' => 'primary', 'weapon' => 'sniper'],
            'headshot'   => ['type' => 'primary', 'weapon' => 'sniper'],
            'splattered' => ['type' => 'primary', 'weapon' => 'shotgun'],
            'picked_off' => ['type' => 'primary', 'weapon' => 'carabine'],

            // special
            'slashed' => ['type' => 'special', 'weapon' => 'knife'],
            'gibbed'  => ['type' => 'special', 'weapon' => 'grenade'],
        ];

        foreach ($map as $field => $cfg) {
            if (!empty($stats[$field])) {
                if ($cfg['type'] === 'primary') {
                    $primary[$cfg['weapon']] += (int)$stats[$field];
                } else {
                    $special[$cfg['weapon']] += (int)$stats[$field];
                }
            }
        }

        arsort($primary);

        $primaryKeys   = array_keys($primary);
        $primaryValues = array_values($primary);

        $mainWeapon   = $primaryKeys[0] ?? null;
        $mainCount    = $primaryValues[0] ?? 0;
        $secondWeapon = $primaryKeys[1] ?? null;
        $secondCount  = $primaryValues[1] ?? 0;

        // No primary-weapon kills at all: fall back to any special weapons used
        // (e.g. a pure knife or grenade game) instead of showing nothing.
        if ($mainCount === 0) {
            $only = [];
            if ($special['knife'] > 0) {
                $only[] = 'knife';
            }
            if ($special['grenade'] > 0) {
                $only[] = 'grenade';
            }

            return ['weapons' => $only];
        }

        $weapons = [$mainWeapon];

        // Primary hybrid
        if ($secondCount > 0 && $secondCount >= ($mainCount * $hybridThreshold)) {
            $weapons[] = $secondWeapon;
        }

        // Special weapons
        if ($special['knife'] > 0 && $special['knife'] >= ($mainCount * $specialThreshold)) {
            $weapons[] = 'knife';
        }

        if ($special['grenade'] > 0 && $special['grenade'] >= ($mainCount * $specialThreshold)) {
            $weapons[] = 'grenade';
        }

        return [
            'weapons' => $weapons,
        ];
    }

}
