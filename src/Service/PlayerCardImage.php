<?php
declare(strict_types=1);

namespace App\Service;

use GdImage;
use RuntimeException;

/**
 * Link preview picture of a player page (og:image, 1200 x 630): their most
 * played map as background, avatar, name, the CTF rating box (rating, type,
 * rank, win rate, attack / defense / combat) and a few totals. GD + the
 * bundled DejaVu fonts, like LiveScoreboardImage.
 */
class PlayerCardImage
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    private const FONT_DIRS = [
        ROOT . '/resources/fonts/',
        '/usr/share/fonts/truetype/dejavu/',
    ];

    /** Rating colors as on the player page (text-sky-300 ... text-red-400) */
    private const RATING_COLORS = [
        [8.0, [125, 211, 252]], [7.0, [74, 222, 128]], [6.0, [190, 242, 100]],
        [5.0, [253, 224, 71]], [4.0, [251, 146, 60]], [0.0, [248, 113, 113]],
    ];

    private const TYPE_COLORS = [
        'All-Rounder' => [253, 224, 71], 'Flag Runner' => [252, 165, 165], 'Defender' => [147, 197, 253],
        'Fragger' => [253, 186, 116],
    ];

    private string $regular;
    private string $bold;
    private GdImage $im;

    public function __construct()
    {
        $this->regular = $this->font('DejaVuSansMono.ttf');
        $this->bold = $this->font('DejaVuSansMono-Bold.ttf');
    }

    /**
     * @param array $card name, country, background (file), avatar (file|null),
     *   rating (null or rating, type, weapon, rank, rated, win_rate,
     *   attack_pct, defense_pct, combat_pct) and stats ([label => value])
     * @return string JPEG bytes
     */
    public function render(array $card): string
    {
        $w = self::WIDTH;
        $h = self::HEIGHT;
        $this->im = imagecreatetruecolor($w, $h);
        imagealphablending($this->im, true);
        $this->background($card['background'] ?? null);

        $pad = 56;
        $white = [255, 255, 255];
        $grey = [161, 161, 170];

        // brand, top left
        $this->text('cube', $pad, 74, 30, $this->bold, [59, 130, 246]);
        $this->text('Ladder', $pad + $this->width('cube', 30, $this->bold), 74, 30, $this->bold, $white);

        // avatar + name
        $avatar = 180;
        $top = 130;
        $this->avatar($card['avatar'] ?? null, $pad, $top, $avatar);
        $nameX = $pad + $avatar + 36;
        $maxName = $w - $nameX - $pad;
        $size = 64;
        while ($size > 34 && $this->width($card['name'], $size, $this->bold) > $maxName) {
            $size -= 4;
        }
        $this->text($this->fit($card['name'], $size, $maxName), $nameX, $top + 78, $size, $this->bold, $white);
        $sub = trim(($card['country'] ? strtoupper($card['country']) . '  ·  ' : '') . ($card['subtitle'] ?? ''), ' ·');
        if ($sub !== '') {
            $this->text($sub, $nameX, $top + 130, 24, $this->regular, [212, 212, 216]);
        }

        // rating box
        $boxY = 360;
        $boxH = 210;
        $this->rect($pad, $boxY, $w - 2 * $pad, $boxH, [0, 0, 0], 40);
        $r = $card['rating'] ?? null;
        if ($r) {
            $value = number_format((float)$r['rating'], 1);
            $color = [255, 255, 255];
            foreach (self::RATING_COLORS as [$from, $rgb]) {
                if ((float)$r['rating'] >= $from) {
                    $color = $rgb;
                    break;
                }
            }
            $this->text($value, $pad + 40, $boxY + 130, 104, $this->bold, $color);
            $this->text('CTF RATING', $pad + 46, $boxY + 175, 18, $this->regular, $grey);

            $x = $pad + 360;
            $typeColor = self::TYPE_COLORS[$r['type']] ?? [228, 228, 231];
            $type = $r['type'] . ($r['weapon'] !== 'Mixed' ? '  ·  ' . $r['weapon'] : '');
            $this->text($type, $x, $boxY + 58, 32, $this->bold, $typeColor);
            $line = sprintf('#%d of %s rated players', (int)$r['rank'], number_format((int)$r['rated']))
                . ($r['win_rate'] !== null ? sprintf('  ·  %d%% won', round((float)$r['win_rate'] * 100)) : '');
            $this->text($line, $x, $boxY + 98, 22, $this->regular, [212, 212, 216]);

            $bars = ['ATTACK' => [(int)$r['attack_pct'], [248, 113, 113]], 'DEFENSE' => [(int)$r['defense_pct'], [96, 165, 250]], 'COMBAT' => [(int)$r['combat_pct'], [251, 146, 60]]];
            $by = $boxY + 128;
            $barX = $x + 140;
            $barW = $w - $pad - 40 - $barX;
            foreach ($bars as $label => [$pct, $rgb]) {
                $this->text($label, $x, $by + 14, 16, $this->regular, $grey);
                $this->rect($barX, $by, $barW, 14, [255, 255, 255], 105);
                $this->rect($barX, $by, max(6, (int)($barW * $pct / 100)), 14, $rgb, 0);
                $by += 26;
            }
        } else {
            // not rated (yet): the totals instead
            $x = $pad + 40;
            $this->text('Not rated yet', $x, $boxY + 70, 34, $this->bold, $white);
            $this->text('the CTF rating starts after 20 CTF games', $x, $boxY + 110, 22, $this->regular, $grey);
        }

        // totals, under the box
        $stats = $card['stats'] ?? [];
        $sx = $pad;
        foreach ($stats as $label => $value) {
            $text = $value . ' ' . $label;
            $this->text($text, $sx, $h - 26, 20, $this->regular, [212, 212, 216]);
            $sx += $this->width($text, 20, $this->regular) + 36;
        }

        ob_start();
        imagejpeg($this->im, null, 86);
        imagedestroy($this->im);

        return (string)ob_get_clean();
    }

    private function background(?string $file): void
    {
        $src = $file && is_file($file) ? @imagecreatefromstring((string)file_get_contents($file)) : false;
        if ($src) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $scale = max(self::WIDTH / $sw, self::HEIGHT / $sh);
            $cw = (int)(self::WIDTH / $scale);
            $ch = (int)(self::HEIGHT / $scale);
            imagecopyresampled($this->im, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), self::WIDTH, self::HEIGHT, $cw, $ch);
            imagedestroy($src);
        } else {
            $this->rect(0, 0, self::WIDTH, self::HEIGHT, [24, 24, 27], 0);
        }
        $this->rect(0, 0, self::WIDTH, self::HEIGHT, [0, 0, 0], 50);
    }

    /** Round avatar (the cube logo when the player has none) */
    private function avatar(?string $file, int $x, int $y, int $size): void
    {
        $src = $file && is_file($file) ? @imagecreatefromstring((string)file_get_contents($file)) : false;
        if (!$src) {
            return;
        }
        $square = imagecreatetruecolor($size, $size);
        $sw = imagesx($src);
        $sh = imagesy($src);
        $side = min($sw, $sh);
        imagecopyresampled($square, $src, 0, 0, (int)(($sw - $side) / 2), (int)(($sh - $side) / 2), $size, $size, $side, $side);
        imagedestroy($src);
        // copy only the pixels inside the circle, then a thin ring
        $r = $size / 2;
        for ($py = 0; $py < $size; $py++) {
            for ($px = 0; $px < $size; $px++) {
                if (($px - $r + .5) ** 2 + ($py - $r + .5) ** 2 <= $r * $r) {
                    imagesetpixel($this->im, $x + $px, $y + $py, imagecolorat($square, $px, $py));
                }
            }
        }
        imagedestroy($square);
        imagesetthickness($this->im, 3);
        imageellipse($this->im, $x + (int)$r, $y + (int)$r, $size, $size, imagecolorallocatealpha($this->im, 255, 255, 255, 70));
        imagesetthickness($this->im, 1);
    }

    private function rect(int $x, int $y, int $w, int $h, array $rgb, int $alpha): void
    {
        imagefilledrectangle($this->im, $x, $y, $x + $w - 1, $y + $h - 1, imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha));
    }

    private function text(string $s, int $x, int $y, int $size, string $font, array $rgb): void
    {
        imagettftext($this->im, $size, 0, $x + 2, $y + 2, imagecolorallocatealpha($this->im, 0, 0, 0, 40), $font, $s);
        imagettftext($this->im, $size, 0, $x, $y, imagecolorallocate($this->im, $rgb[0], $rgb[1], $rgb[2]), $font, $s);
    }

    private function width(string $s, int $size, string $font): int
    {
        $box = imagettfbbox($size, 0, $font, $s);

        return $box ? abs($box[2] - $box[0]) : 0;
    }

    private function fit(string $s, int $size, int $max): string
    {
        if ($this->width($s, $size, $this->bold) <= $max) {
            return $s;
        }
        while (mb_strlen($s) > 1 && $this->width($s . '…', $size, $this->bold) > $max) {
            $s = mb_substr($s, 0, -1);
        }

        return $s . '…';
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
