<?php
declare(strict_types=1);

namespace App\Service;

use GdImage;
use RuntimeException;

/**
 * Link preview picture of a player page (og:image, 1200 x 630), in the
 * dashboard style of the site: a framed dark card with the brand, a headline
 * (rating, trend, type, rank, time played), the player panel (avatar, name,
 * weapons of choice on their most played map) and the stats panel (CTF
 * rating, type, win rate, attack / defense / combat, totals with icons).
 * The better the CTF rating, the more epic the card (TIERS: Silver, Gold,
 * Diamond, Legend - frame, glow, light rays, sparkles), and a player's
 * character (Butcher, Headhunter, ...) shows as an emblem on the map.
 * GD + the bundled DejaVu Sans and Font Awesome fonts (resources/fonts).
 */
class PlayerCardImage
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    private const FONT_DIRS = [
        ROOT . '/resources/fonts/',
        '/usr/share/fonts/truetype/dejavu/',
    ];

    private const BG = [11, 17, 32];
    private const PANEL = [15, 23, 42];
    private const LINE = [30, 41, 59];
    private const BLUE = [59, 130, 246];
    private const WHITE = [241, 245, 249];
    private const MUTED = [148, 163, 184];
    private const SOFT = [125, 145, 180];

    /** Rating colors as on the site (text-sky-300 ... text-red-400) */
    private const RATING_COLORS = [
        [8.0, [125, 211, 252]], [7.0, [74, 222, 128]], [6.0, [190, 242, 100]],
        [5.0, [253, 224, 71]], [4.0, [251, 146, 60]], [0.0, [248, 113, 113]],
    ];

    private const TYPE_COLORS = [
        'All-Rounder' => [190, 242, 100], 'Flag Runner' => [252, 165, 165], 'Defender' => [147, 197, 253],
        'Fragger' => [253, 186, 116],
    ];

    /** Font Awesome 6 solid code points */
    private const ICONS = [
        'gamepad' => 0xf11b, 'crosshairs' => 0xf05b, 'clock' => 0xf017, 'skull' => 0xf54c,
        'flag' => 0xf024, 'trophy' => 0xf091, 'star' => 0xf005, 'moon' => 0xf186,
        'crown' => 0xf521, 'gem' => 0xf3a5, 'medal' => 0xf5a2, 'shield' => 0xf3ed,
    ];

    /**
     * Card tiers by CTF rating, best first (below 5.0 or unrated: the plain
     * card): frame gradient stops, frame width, background tint, glow,
     * light rays behind the avatar, number of sparkles
     */
    public const TIERS = [
        'legend' => ['min' => 8.0, 'name' => 'LEGEND', 'icon' => 'crown', 'frame' => 6, 'rays' => true, 'sparkles' => 22,
            'stops' => [[255, 225, 120], [251, 146, 60], [225, 29, 72]], 'bg' => [46, 16, 12], 'glow' => [251, 146, 60]],
        'diamond' => ['min' => 7.0, 'name' => 'DIAMOND', 'icon' => 'gem', 'frame' => 5, 'rays' => true, 'sparkles' => 12,
            'stops' => [[103, 232, 249], [129, 140, 248], [217, 70, 239]], 'bg' => [24, 18, 56], 'glow' => [129, 140, 248]],
        'gold' => ['min' => 6.0, 'name' => 'GOLD', 'icon' => 'medal', 'frame' => 4, 'rays' => false, 'sparkles' => 5,
            'stops' => [[254, 240, 138], [234, 179, 8], [161, 98, 7]], 'bg' => [34, 26, 6], 'glow' => [234, 179, 8]],
        'silver' => ['min' => 5.0, 'name' => 'SILVER', 'icon' => 'shield', 'frame' => 3, 'rays' => false, 'sparkles' => 0,
            'stops' => [[248, 250, 252], [148, 163, 184], [71, 85, 105]], 'bg' => null, 'glow' => [148, 163, 184]],
    ];

    /** Character emblem colors (webroot/img/characters, PNGs in resources/characters) */
    private const ARCHETYPE_COLORS = [
        'Headhunter' => [125, 211, 252], 'Butcher' => [252, 165, 165], 'Bomber' => [253, 186, 116],
        'Pistolero' => [110, 231, 183], 'Berserker' => [216, 180, 254],
    ];

    private string $regular;
    private string $bold;
    private string $icons;
    private GdImage $im;
    private ?array $tier = null;

    public function __construct()
    {
        $this->regular = $this->font('DejaVuSans.ttf');
        $this->bold = $this->font('DejaVuSans-Bold.ttf');
        $this->icons = $this->font('fa-solid-900.ttf');
    }

    /**
     * @param array $card name, country, background (file), avatar (file|null),
     *   hours, games, played ("50 h 23 min"), inactive (bool),
     *   rating (null or rating, trend, type, type_label, rank, rated, win_rate,
     *   attack_pct, defense_pct, combat_pct, archetype, archetype_pct,
     *   archetype_rank), weapons ([[key, name, pct]]), totals (kills, flags,
     *   wins, mvp)
     * @return string JPEG bytes
     */
    public function render(array $card): string
    {
        $w = self::WIDTH;
        $h = self::HEIGHT;
        $this->im = imagecreatetruecolor($w, $h);
        imagealphablending($this->im, true);
        imageantialias($this->im, true);
        $this->rect(0, 0, $w, $h, self::BG);
        $r = $card['rating'] ?? null;
        $this->tier = self::tier($r ? (float)$r['rating'] : null);
        $t = $this->tier;

        // frame: plain blue, or the tier's gradient with a glow inside
        if ($t) {
            if ($t['bg']) {
                // the tier's color rising from the bottom
                for ($y = 0; $y < $h; $y++) {
                    $this->rect(0, $y, $w, 1, $t['bg'], (int)(127 - 127 * ($y / $h) ** 1.6));
                }
            }
            $this->ring(10, 10, $w - 20, $h - 20, 18, $t['frame'], $t['frame'] + 34,
                fn($px, $py, $d) => [$t['glow'], 0.32 * (1 - ($d - $t['frame']) / 34) ** 2]);
            $this->ring(10, 10, $w - 20, $h - 20, 18, 0, $t['frame'],
                fn($px, $py, $d) => [$this->gradient($t['stops'], (($px - 10) / ($w - 20) + ($py - 10) / ($h - 20)) / 2), 1.0]);
            if ($t['frame'] >= 5) {
                // a thin second line inside the frame
                $this->ring(10, 10, $w - 20, $h - 20, 18, $t['frame'] + 4, $t['frame'] + 5,
                    fn($px, $py, $d) => [$t['stops'][0], 0.55]);
            }
            $this->sparkles(crc32($card['name']), $t['sparkles'], $t['stops'][0]);
        } else {
            $this->roundRect(10, 10, $w - 20, $h - 20, 16, null, [37, 99, 235], 2);
        }

        // brand
        $this->logo(WWW_ROOT . 'img/brand/cubeladder-mark-transparent.png', 30, 20, 44);
        $this->text('cube', 84, 55, 24, $this->bold, self::BLUE);
        $this->text('Ladder', 84 + $this->width('cube', 24, $this->bold), 55, 24, $this->bold, self::WHITE);
        $this->rect(12 + ($t['frame'] ?? 0), 78, $w - 24 - 2 * ($t['frame'] ?? 0), 1, $t ? $t['stops'][2] : [30, 58, 138]);
        if ($t) {
            $this->tierPill($t, $r, $w - 30 - ($t['frame'] ?? 0), 24);
        }

        $pad = 40;

        // headline
        $this->text($this->fit($card['name'] . ' · cubeLadder', 34, $w - 2 * $pad, $this->bold), $pad, 128, 34, $this->bold, self::BLUE);
        $x = $pad;
        if ($r) {
            $x = $this->run($x, 162, 19, $this->regular, self::WHITE, 'CTF rating ' . number_format((float)$r['rating'], 1) . ' ');
            if (!empty($r['trend'])) {
                $up = $r['trend'] === 'up';
                $x = $this->run($x, 162, 17, $this->regular, $up ? [74, 222, 128] : [248, 113, 113], $up ? '▲' : '▼');
                $x = $this->run($x, 162, 19, $this->regular, self::WHITE, $up ? ' on the rise' : ' going down');
            }
            $this->run($x, 162, 19, $this->regular, self::WHITE, '  ·  ' . ($r['type_label'] ?? $r['type']));
            $line3 = sprintf('#%d of %s rated players', (int)$r['rank'], number_format((int)$r['rated']));
        } else {
            $this->text(!empty($card['inactive']) ? 'Inactive - no game in the last 90 days' : 'Not rated yet - the CTF rating starts after 20 CTF games',
                $pad, 162, 19, $this->regular, self::WHITE);
            $line3 = '';
        }
        $played = $card['played'] ?? '';
        $this->text(trim($line3 . ($line3 !== '' && $played !== '' ? '  ·  ' : '') . ($played !== '' ? $played . ' played' : '')),
            $pad, 191, 17, $this->regular, self::SOFT);
        $this->rect($pad, 206, $w - 2 * $pad, 1, self::LINE);

        $this->playerPanel($card, $pad, 218, $w - 2 * $pad, 138);
        $this->statsPanel($card, $pad, 368, $w - 2 * $pad, 234);

        ob_start();
        imagejpeg($this->im, null, 88);
        imagedestroy($this->im);

        return (string)ob_get_clean();
    }

    /** Avatar, name, country / hours / games and the weapons of choice, on the map */
    private function playerPanel(array $card, int $x, int $y, int $w, int $h): void
    {
        $this->roundRect($x, $y, $w, $h, 12, self::PANEL, self::LINE, 1);
        $this->mapBackground($card['background'] ?? null, $x + (int)($w * 0.35), $y + 1, (int)($w * 0.65) - 1, $h - 2);

        $avatar = 106;
        $ax = $x + 24;
        $ay = $y + (int)(($h - $avatar) / 2);
        if ($this->tier) {
            $this->halo($ax + (int)($avatar / 2), $ay + (int)($avatar / 2), $avatar, [$x + 1, $y + 1, $x + (int)($w * 0.35) + 60, $y + $h - 2]);
        }
        $this->avatar($card['avatar'] ?? null, $ax, $ay, $avatar);

        $archetype = $card['rating']['archetype'] ?? null;
        $tx = $x + 24 + $avatar + 30;
        $nameMax = $w - ($tx - $x) - 20 - ($archetype ? 360 : 0);
        $this->text($this->fit($card['name'], 38, $nameMax, $this->bold), $tx, $y + 50, 38, $this->bold, [255, 255, 255]);
        if ($archetype) {
            $this->archetype($card['rating'], $x + $w - 20, $y, $h);
        }

        $meta = implode('   ·   ', array_filter([
            $card['country'] ? strtoupper($card['country']) : '',
            !empty($card['hours']) ? number_format((float)$card['hours']) . ' h played' : '',
            !empty($card['games']) ? number_format((int)$card['games']) . ' games' : '',
        ]));
        $this->icon('gamepad', $tx, $y + 80, 17, self::MUTED);
        $this->text($meta, $tx + 34 + 8, $y + 80, 16, $this->regular, [203, 213, 225]);

        // weapons of choice, side by side
        $wx = $tx;
        foreach (array_slice($card['weapons'] ?? [], 0, 3) as $i => [$key, $label, $pct]) {
            if ($i > 0) {
                $this->rect($wx - 18, $y + 96, 1, 34, [51, 65, 85]);
            }
            $this->png(ROOT . '/resources/weapons/' . $key . '.png', $wx, $y + 96, 34);
            $lx = $wx + 44;
            $name = strtoupper($label);
            $this->text($name, $lx, $y + 111, 13, $this->bold, self::WHITE);
            $this->text($pct . '% of kills', $lx, $y + 130, 12, $this->regular, [191, 203, 222]);
            $wx = $lx + max($this->width($name, 13, $this->bold), $this->width($pct . '% of kills', 12, $this->regular)) + 36;
            if ($wx > $x + $w - ($archetype ? 520 : 180)) {
                break;
            }
        }
    }

    /** CTF rating | type, win rate, bars | totals */
    private function statsPanel(array $card, int $x, int $y, int $w, int $h): void
    {
        $this->roundRect($x, $y, $w, $h, 12, self::PANEL, self::LINE, 1);
        $r = $card['rating'] ?? null;

        // the rating
        $col1 = $x + 30;
        if ($r) {
            $value = number_format((float)$r['rating'], 1);
            $color = $this->ratingColor((float)$r['rating']);
            if ($this->tier && $this->tier['rays']) {
                $this->glowText($value, $col1, $y + 128, 84, $this->bold, $this->tier['glow']);
            }
            $this->text($value, $col1, $y + 128, 84, $this->bold, $color);
            if (!empty($r['trend'])) {
                $up = $r['trend'] === 'up';
                $this->text($up ? '▲' : '▼', $col1 + $this->width($value, 84, $this->bold) + 8, $y + ($up ? 58 : 124), 22, $this->regular,
                    $up ? [74, 222, 128] : [248, 113, 113]);
            }
        } else {
            $this->icon('moon', $col1 + 30, $y + 118, 60, self::MUTED);
        }
        $this->text('CTF RATING', $col1 + 2, $y + 172, 16, $this->regular, self::MUTED);

        $d1 = $x + 266;
        $this->rect($d1, $y + 28, 1, $h - 56, self::LINE);

        // type, win rate, bars
        $col2 = $d1 + 30;
        $barsEnd = $x + 590;
        if ($r) {
            $typeColor = self::TYPE_COLORS[$r['type']] ?? [228, 228, 231];
            // long types ("Offensive Team Player") get a smaller font, not cut off
            $type = $r['type_label'] ?? $r['type'];
            $typeSize = 26;
            while ($typeSize > 16 && $this->width($type, $typeSize, $this->bold) > $barsEnd - $col2) {
                $typeSize--;
            }
            $this->text($this->fit($type, $typeSize, $barsEnd - $col2, $this->bold), $col2, $y + 56, $typeSize, $this->bold, $typeColor);
            if ($r['win_rate'] !== null) {
                $this->text(sprintf('%d%% won', round((float)$r['win_rate'] * 100)), $col2, $y + 92, 20, $this->regular, self::WHITE);
            }
            $by = $y + 128;
            foreach (['ATTACK' => [(int)$r['attack_pct'], [248, 113, 113]], 'DEFENSE' => [(int)$r['defense_pct'], [96, 165, 250]],
                         'COMBAT' => [(int)$r['combat_pct'], [251, 191, 36]]] as $label => [$pct, $rgb]) {
                $this->text($label, $col2, $by + 10, 12, $this->regular, self::MUTED);
                $bx = $col2 + 92;
                $bw = $barsEnd - $bx;
                $this->bar($bx, $by, $bw, 10, [30, 41, 59]);
                $this->bar($bx, $by, max(10, (int)($bw * $pct / 100)), 10, $rgb);
                $by += 28;
            }
        } else {
            $this->text(!empty($card['inactive']) ? 'Inactive' : 'Not rated yet', $col2, $y + 70, 26, $this->bold, self::WHITE);
            $this->text(!empty($card['inactive']) ? 'back with the next game' : 'from 20 CTF games on', $col2, $y + 104, 18, $this->regular, self::MUTED);
        }

        $d2 = $barsEnd + 30;
        $this->rect($d2, $y + 28, 1, $h - 56, self::LINE);

        // totals: games and time on top, kills / flags / wins / MVP below
        $sx = $d2 + 26;
        $sw = $x + $w - 24 - $sx;
        $half = (int)($sw / 2);
        foreach ([['crosshairs', number_format((int)($card['games'] ?? 0)), 'GAMES'], ['clock', number_format((float)($card['hours'] ?? 0)) . ' h', 'PLAYED']] as $i => [$ic, $num, $label]) {
            $cx = $sx + $i * $half;
            if ($i > 0) {
                $this->rect($cx - 14, $y + 30, 1, 52, self::LINE);
            }
            $this->icon($ic, $cx, $y + 70, 26, self::BLUE);
            $this->text($num, $cx + 44, $y + 58, 24, $this->bold, self::WHITE);
            $this->text($label, $cx + 44, $y + 80, 13, $this->regular, self::MUTED);
        }
        $this->rect($sx, $y + 100, $sw, 1, self::LINE);

        $totals = $card['totals'] ?? [];
        $cells = [['skull', 'kills', 'KILLS'], ['flag', 'flags', 'FLAGS'], ['trophy', 'wins', 'WINS'], ['star', 'mvp', 'TIMES MVP']];
        $cw = (int)($sw / 4);
        foreach ($cells as $i => [$ic, $key, $label]) {
            $cx = $sx + $i * $cw;
            if ($i > 0) {
                $this->rect($cx - 8, $y + 118, 1, 92, self::LINE);
            }
            $this->icon($ic, $cx, $y + 146, 20, self::BLUE);
            $num = number_format((int)($totals[$key] ?? 0));
            $numSize = 19;
            while ($numSize > 12 && $this->width($num, $numSize, $this->bold) > $cw - 16) {
                $numSize--;
            }
            $this->text($num, $cx, $y + 180, $numSize, $this->bold, self::WHITE);
            $this->text($this->fit($label, 10, $cw - 12, $this->regular), $cx, $y + 200, 10, $this->regular, self::MUTED);
        }
    }

    /** The most played map on the right of the player panel, fading in from the left */
    private function mapBackground(?string $file, int $x, int $y, int $w, int $h): void
    {
        $src = $file && is_file($file) ? @imagecreatefromstring((string)file_get_contents($file)) : false;
        if (!$src) {
            return;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cw = (int)($w / $scale);
        $ch = (int)($h / $scale);
        imagecopyresampled($this->im, $src, $x, $y, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), $w, $h, $cw, $ch);
        imagedestroy($src);
        // darken, and fade the left edge into the panel
        $this->rect($x, $y, $w, $h, [0, 0, 0], 60);
        for ($i = 0; $i < $w; $i++) {
            $alpha = (int)min(127, 127 * $i / ($w * 0.6));
            if ($alpha < 127) {
                // a 1 px rectangle, not imageline: anti-aliased lines ignore the alpha on some GD builds
                $this->rect($x + $i, $y, 1, $h, self::PANEL, $alpha);
            }
        }
    }

    /** Round avatar (just the ring when the player has no picture) */
    private function avatar(?string $file, int $x, int $y, int $size): void
    {
        $src = $file && is_file($file) ? @imagecreatefromstring((string)file_get_contents($file)) : false;
        if ($src) {
            $square = imagecreatetruecolor($size, $size);
            $sw = imagesx($src);
            $sh = imagesy($src);
            $side = min($sw, $sh);
            imagecopyresampled($square, $src, 0, 0, (int)(($sw - $side) / 2), (int)(($sh - $side) / 2), $size, $size, $side, $side);
            imagedestroy($src);
            $r = $size / 2;
            for ($py = 0; $py < $size; $py++) {
                for ($px = 0; $px < $size; $px++) {
                    if (($px - $r + .5) ** 2 + ($py - $r + .5) ** 2 <= $r * $r) {
                        imagesetpixel($this->im, $x + $px, $y + $py, imagecolorat($square, $px, $py));
                    }
                }
            }
            imagedestroy($square);
        }
        $t = $this->tier;
        if ($t) {
            // the tier's gradient ring
            $c = $size / 2;
            $this->ringCircle($x + $c, $y + $c, $c + 1, $t['frame'] - 1,
                fn($px, $py) => $this->gradient($t['stops'], (($px - $x) / $size + ($py - $y) / $size) / 2));
        } else {
            imagesetthickness($this->im, 2);
            imageellipse($this->im, $x + (int)($size / 2), $y + (int)($size / 2), $size, $size, imagecolorallocate($this->im, 71, 85, 105));
            imagesetthickness($this->im, 1);
        }
    }

    /** The card tier of a CTF rating (TIERS), null for the plain card */
    public static function tier(?float $rating): ?array
    {
        if ($rating === null) {
            return null;
        }
        foreach (self::TIERS as $key => $tier) {
            if ($rating >= $tier['min']) {
                return $tier + ['key' => $key];
            }
        }

        return null;
    }

    /** "LEGEND" (with the place for the top 3) in the top right corner, right-aligned to $right */
    private function tierPill(array $t, ?array $r, int $right, int $y): void
    {
        $label = $t['name'] . ($r && (int)$r['rank'] <= 3 ? '  ·  #' . (int)$r['rank'] : '');
        $tw = $this->width($label, 15, $this->bold);
        $pw = 22 + 24 + $tw + 18;
        $px = $right - $pw;
        $this->roundRect($px, $y, $pw, 36, 18, $t['bg'] ?? [30, 41, 59], null);
        $this->ring($px, $y, $pw, 36, 18, 0, 2,
            fn($qx, $qy, $d) => [$this->gradient($t['stops'], ($qx - $px) / $pw), 1.0]);
        $this->icon($t['icon'], $px + 18, $y + 25, 15, $t['stops'][0]);
        $this->text($label, $px + 22 + 24, $y + 25, 15, $this->bold, $t['stops'][0]);
    }

    /**
     * Behind the avatar: a soft glow in the tier's color, and light rays on
     * Diamond and Legend cards, clipped to $clip [x1, y1, x2, y2]
     */
    private function halo(int $cx, int $cy, int $avatar, array $clip): void
    {
        $t = $this->tier;
        imagesetclip($this->im, ...$clip);
        if ($t['rays']) {
            $n = $t['key'] === 'legend' ? 20 : 14;
            $c = imagecolorallocatealpha($this->im, ...[...$t['stops'][0], 118]);
            for ($i = 0; $i < $n; $i++) {
                $a = 2 * M_PI * $i / $n + 0.1;
                // overlapping lengths: bright near the avatar, fading out
                foreach ([0.9, 1.2, 1.5] as $k) {
                    $len = $avatar * $k;
                    $spread = 0.11 / $k;
                    imagefilledpolygon($this->im, [
                        $cx, $cy,
                        (int)($cx + $len * cos($a - $spread)), (int)($cy + $len * sin($a - $spread)),
                        (int)($cx + $len * cos($a + $spread)), (int)($cy + $len * sin($a + $spread)),
                    ], $c);
                }
            }
        }
        for ($d = (int)($avatar * 1.9); $d > $avatar; $d -= 6) {
            imagefilledellipse($this->im, $cx, $cy, $d, $d, imagecolorallocatealpha($this->im, ...[...$t['glow'], 121]));
        }
        imagesetclip($this->im, 0, 0, self::WIDTH - 1, self::HEIGHT - 1);
    }

    /**
     * The character: emblem on the right of the player panel (over the map),
     * its name and what it stands for right-aligned next to it
     */
    private function archetype(array $r, int $right, int $y, int $h): void
    {
        $key = strtolower($r['archetype']);
        $size = 112;
        $ex = $right - $size;
        $ey = $y + (int)(($h - $size) / 2);
        $rgb = self::ARCHETYPE_COLORS[$r['archetype']] ?? self::WHITE;
        // a soft glow in its color behind the emblem
        for ($d = (int)($size * 1.5); $d > (int)($size * 0.6); $d -= 8) {
            imagefilledellipse($this->im, $ex + (int)($size / 2), $ey + (int)($size / 2), $d, $d, imagecolorallocatealpha($this->im, ...[...$rgb, 120]));
        }
        $this->png(ROOT . '/resources/characters/' . $key . '.png', $ex, $ey, $size);

        $rank = isset($r['archetype_rank']) ? (int)$r['archetype_rank'] : null;
        $name = strtoupper(\App\Command\CalculateRatingsCommand::archetypeLabel($r['archetype'], $rank));
        $text = \App\Command\CalculateRatingsCommand::archetypeText($r['archetype'], isset($r['archetype_pct']) ? (int)$r['archetype_pct'] : null, $rank);
        $tx = $ex - 14;
        $this->text('CHARACTER', $tx - $this->width('CHARACTER', 11, $this->regular), $y + 46, 11, $this->regular, self::MUTED);
        $this->text($name, $tx - $this->width($name, 26, $this->bold), $y + 82, 26, $this->bold, $rgb);
        $this->text($text, $tx - $this->width($text, 13, $this->regular), $y + 106, 13, $this->regular, [226, 232, 240]);
        if ($rank === 1) {
            $this->icon('crown', $ex + (int)($size / 2) - 13, $ey + 6, 22, [255, 225, 120]);
        }
    }

    /**
     * Pixels between $d0 and $d1 inside a rounded rectangle (anti-aliased),
     * colored by $color($px, $py, $d) => [rgb, opacity 0-1]
     */
    private function ring(int $x, int $y, int $w, int $h, int $r, float $d0, float $d1, callable $color): void
    {
        $band = (int)ceil($d1) + 1;
        $hw = $w / 2;
        $hh = $h / 2;
        $cx = $x + $hw;
        $cy = $y + $hh;
        for ($py = $y; $py <= $y + $h; $py++) {
            $edge = $py - $y < $band + $r || $y + $h - $py < $band + $r;
            $ranges = $edge ? [[$x, $x + $w]] : [[$x, $x + $band], [$x + $w - $band, $x + $w]];
            foreach ($ranges as [$from, $to]) {
                for ($px = $from; $px <= $to; $px++) {
                    // distance from the outer edge, inwards
                    $qx = abs($px + .5 - $cx) - ($hw - $r);
                    $qy = abs($py + .5 - $cy) - ($hh - $r);
                    $d = -(sqrt(max($qx, 0) ** 2 + max($qy, 0) ** 2) + min(max($qx, $qy), 0) - $r);
                    $cover = max(0, min(1, $d - $d0 + .5)) * max(0, min(1, $d1 - $d + .5));
                    if ($cover <= 0) {
                        continue;
                    }
                    [$rgb, $opacity] = $color($px, $py, $d);
                    $alpha = 127 - (int)round(127 * $cover * max(0, min(1, $opacity)));
                    if ($alpha < 127) {
                        imagesetpixel($this->im, $px, $py, imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha));
                    }
                }
            }
        }
    }

    /** A circle ring of $thickness inside radius $radius, colored by $color($px, $py) => rgb */
    private function ringCircle(float $cx, float $cy, float $radius, int $thickness, callable $color): void
    {
        $thickness = max(2, $thickness);
        for ($py = (int)floor($cy - $radius); $py <= (int)ceil($cy + $radius); $py++) {
            for ($px = (int)floor($cx - $radius); $px <= (int)ceil($cx + $radius); $px++) {
                $dist = sqrt(($px + .5 - $cx) ** 2 + ($py + .5 - $cy) ** 2);
                $cover = max(0, min(1, $radius - $dist + .5)) * max(0, min(1, $dist - ($radius - $thickness) + .5));
                if ($cover > 0) {
                    $rgb = $color($px, $py);
                    imagesetpixel($this->im, $px, $py, imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], 127 - (int)round(127 * $cover)));
                }
            }
        }
    }

    /** Four-point stars, always in the same places for a player, away from the text */
    private function sparkles(int $seed, int $n, array $rgb): void
    {
        mt_srand($seed);
        // free areas: right of the headline, the top band, the bottom edge
        $zones = [[640, 96, 1150, 200], [300, 22, 900, 70], [60, 606, 1140, 616]];
        for ($i = 0; $i < $n; $i++) {
            [$x1, $y1, $x2, $y2] = $zones[$i % 4 === 3 ? 2 : $i % 2];
            $cx = mt_rand($x1, $x2);
            $cy = mt_rand($y1, $y2);
            $s = mt_rand(4, 11);
            $k = $s * 0.22;
            $c = imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], mt_rand(20, 70));
            imagefilledpolygon($this->im, [
                $cx, $cy - $s, (int)($cx + $k), (int)($cy - $k), $cx + $s, $cy, (int)($cx + $k), (int)($cy + $k),
                $cx, $cy + $s, (int)($cx - $k), (int)($cy + $k), $cx - $s, $cy, (int)($cx - $k), (int)($cy - $k),
            ], $c);
            if ($s >= 9) {
                imagefilledellipse($this->im, $cx, $cy, $s * 2, $s * 2, imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], 118));
            }
        }
        mt_srand();
    }

    /** A soft glow around big text (drawn under it) */
    private function glowText(string $s, int $x, int $y, int $size, string $font, array $rgb): void
    {
        foreach ([8 => 122, 5 => 118, 2 => 112] as $radius => $alpha) {
            $c = imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha);
            for ($a = 0; $a < 360; $a += 30) {
                imagettftext($this->im, $size, 0, $x + (int)round($radius * cos(deg2rad($a))), $y + (int)round($radius * sin(deg2rad($a))), $c, $font, $s);
            }
        }
    }

    /** Color at $t (0-1) along gradient stops */
    private function gradient(array $stops, float $t): array
    {
        $t = max(0, min(1, $t)) * (count($stops) - 1);
        $i = min((int)floor($t), count($stops) - 2);
        $f = $t - $i;

        return array_map(fn($a, $b) => (int)round($a + ($b - $a) * $f), $stops[$i], $stops[$i + 1]);
    }

    /** The cube mark (transparent PNG), height $h */
    private function logo(string $file, int $x, int $y, int $h): void
    {
        $src = is_file($file) ? @imagecreatefrompng($file) : false;
        if (!$src) {
            return;
        }
        $w = (int)round(imagesx($src) * $h / imagesy($src));
        imagecopyresampled($this->im, $src, $x, $y, 0, 0, $w, $h, imagesx($src), imagesy($src));
        imagedestroy($src);
    }

    /** A transparent PNG (weapon icon), $size square */
    private function png(string $file, int $x, int $y, int $size): void
    {
        $src = is_file($file) ? @imagecreatefrompng($file) : false;
        if (!$src) {
            return;
        }
        imagecopyresampled($this->im, $src, $x, $y, 0, 0, $size, $size, imagesx($src), imagesy($src));
        imagedestroy($src);
    }

    private function icon(string $name, int $x, int $baseline, int $size, array $rgb): void
    {
        imagettftext($this->im, $size, 0, $x, $baseline, imagecolorallocate($this->im, $rgb[0], $rgb[1], $rgb[2]), $this->icons, mb_chr(self::ICONS[$name], 'UTF-8'));
    }

    private function rect(int $x, int $y, int $w, int $h, array $rgb, int $alpha = 0): void
    {
        imagefilledrectangle($this->im, $x, $y, $x + $w - 1, $y + $h - 1, imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha));
    }

    /** Rounded rectangle: optional fill, optional border */
    private function roundRect(int $x, int $y, int $w, int $h, int $r, ?array $fill, ?array $border, int $thickness = 1): void
    {
        if ($fill) {
            $c = imagecolorallocate($this->im, $fill[0], $fill[1], $fill[2]);
            imagefilledrectangle($this->im, $x + $r, $y, $x + $w - $r, $y + $h, $c);
            imagefilledrectangle($this->im, $x, $y + $r, $x + $w, $y + $h - $r, $c);
            foreach ([[$x + $r, $y + $r], [$x + $w - $r, $y + $r], [$x + $r, $y + $h - $r], [$x + $w - $r, $y + $h - $r]] as [$cx, $cy]) {
                imagefilledellipse($this->im, $cx, $cy, 2 * $r, 2 * $r, $c);
            }
        }
        if ($border) {
            $c = imagecolorallocate($this->im, $border[0], $border[1], $border[2]);
            for ($t = 0; $t < $thickness; $t++) {
                $xi = $x + $t;
                $yi = $y + $t;
                $wi = $w - 2 * $t;
                $hi = $h - 2 * $t;
                $ri = max(1, $r - $t);
                imageline($this->im, $xi + $ri, $yi, $xi + $wi - $ri, $yi, $c);
                imageline($this->im, $xi + $ri, $yi + $hi, $xi + $wi - $ri, $yi + $hi, $c);
                imageline($this->im, $xi, $yi + $ri, $xi, $yi + $hi - $ri, $c);
                imageline($this->im, $xi + $wi, $yi + $ri, $xi + $wi, $yi + $hi - $ri, $c);
                imagearc($this->im, $xi + $ri, $yi + $ri, 2 * $ri, 2 * $ri, 180, 270, $c);
                imagearc($this->im, $xi + $wi - $ri, $yi + $ri, 2 * $ri, 2 * $ri, 270, 360, $c);
                imagearc($this->im, $xi + $ri, $yi + $hi - $ri, 2 * $ri, 2 * $ri, 90, 180, $c);
                imagearc($this->im, $xi + $wi - $ri, $yi + $hi - $ri, 2 * $ri, 2 * $ri, 0, 90, $c);
            }
        }
    }

    /** A bar with round ends */
    private function bar(int $x, int $y, int $w, int $h, array $rgb): void
    {
        $c = imagecolorallocate($this->im, $rgb[0], $rgb[1], $rgb[2]);
        $r = (int)($h / 2);
        imagefilledrectangle($this->im, $x + $r, $y, $x + $w - $r, $y + $h - 1, $c);
        imagefilledellipse($this->im, $x + $r, $y + $r, $h, $h, $c);
        imagefilledellipse($this->im, $x + $w - $r, $y + $r, $h, $h, $c);
    }

    private function text(string $s, int $x, int $y, int $size, string $font, array $rgb): void
    {
        imagettftext($this->im, $size, 0, $x, $y, imagecolorallocate($this->im, $rgb[0], $rgb[1], $rgb[2]), $font, $s);
    }

    /** Text that continues a line: returns where the next part starts */
    private function run(int $x, int $y, int $size, string $font, array $rgb, string $s): int
    {
        $this->text($s, $x, $y, $size, $font, $rgb);

        return $x + $this->width($s, $size, $font);
    }

    private function width(string $s, int $size, string $font): int
    {
        $box = imagettfbbox($size, 0, $font, $s);

        return $box ? abs($box[2] - $box[0]) : 0;
    }

    private function fit(string $s, int $size, int $max, string $font): string
    {
        if ($this->width($s, $size, $font) <= $max) {
            return $s;
        }
        while (mb_strlen($s) > 1 && $this->width($s . '…', $size, $font) > $max) {
            $s = mb_substr($s, 0, -1);
        }

        return $s . '…';
    }

    private function ratingColor(float $rating): array
    {
        foreach (self::RATING_COLORS as [$from, $rgb]) {
            if ($rating >= $from) {
                return $rgb;
            }
        }

        return self::WHITE;
    }

    private function font(string $file): string
    {
        foreach (self::FONT_DIRS as $dir) {
            if (is_file($dir . $file)) {
                return $dir . $file;
            }
        }
        throw new RuntimeException("Font {$file} not found");
    }
}
