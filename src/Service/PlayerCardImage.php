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
    ];

    private string $regular;
    private string $bold;
    private string $icons;
    private GdImage $im;

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
     *   attack_pct, defense_pct, combat_pct), weapons ([[key, name, pct]]),
     *   totals (kills, flags, wins, mvp)
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

        // frame
        $this->roundRect(10, 10, $w - 20, $h - 20, 16, null, [37, 99, 235], 2);

        // brand
        $this->logo(WWW_ROOT . 'img/brand/cubeladder-mark-transparent.png', 30, 20, 44);
        $this->text('cube', 84, 55, 24, $this->bold, self::BLUE);
        $this->text('Ladder', 84 + $this->width('cube', 24, $this->bold), 55, 24, $this->bold, self::WHITE);
        $this->rect(12, 78, $w - 24, 1, [30, 58, 138]);

        $r = $card['rating'] ?? null;
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
        $this->avatar($card['avatar'] ?? null, $x + 24, $y + (int)(($h - $avatar) / 2), $avatar);

        $tx = $x + 24 + $avatar + 30;
        $this->text($this->fit($card['name'], 38, $w - ($tx - $x) - 20, $this->bold), $tx, $y + 50, 38, $this->bold, [255, 255, 255]);

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
            if ($wx > $x + $w - 180) {
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
                imageline($this->im, $x + $i, $y, $x + $i, $y + $h - 1,
                    imagecolorallocatealpha($this->im, self::PANEL[0], self::PANEL[1], self::PANEL[2], $alpha));
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
        imagesetthickness($this->im, 2);
        imageellipse($this->im, $x + (int)($size / 2), $y + (int)($size / 2), $size, $size, imagecolorallocate($this->im, 71, 85, 105));
        imagesetthickness($this->im, 1);
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
