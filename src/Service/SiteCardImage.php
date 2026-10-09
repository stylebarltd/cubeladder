<?php
declare(strict_types=1);

namespace App\Service;

use GdImage;
use RuntimeException;

/**
 * Link preview pictures (1200 x 630) of the pages without their own: the
 * site card (logo, tagline, totals, top 3 by CTF rating) and the All Time
 * Ranking card (its top 10). GD + the bundled DejaVu fonts.
 */
class SiteCardImage
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    private const FONT_DIRS = [
        ROOT . '/resources/fonts/',
        '/usr/share/fonts/truetype/dejavu/',
    ];

    /** Rating colors as on the player page */
    private const RATING_COLORS = [
        [8.0, [125, 211, 252]], [7.0, [74, 222, 128]], [6.0, [190, 242, 100]],
        [5.0, [253, 224, 71]], [4.0, [251, 146, 60]], [0.0, [248, 113, 113]],
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
     * @param array $card totals ([label => value]) and top ([[name, rating, type]], best first)
     */
    public function site(array $card): string
    {
        $this->canvas(WWW_ROOT . 'img/bullet-wide.jpg', 45);
        $pad = 64;

        $this->logo(WWW_ROOT . 'img/brand/cubeladder-mark-transparent.png', $pad - 10, 70, 200);
        $x = $pad + 220;
        $this->text('cube', $x, 160, 72, $this->bold, [59, 130, 246]);
        $this->text('Ladder', $x + $this->width('cube', 72, $this->bold), 160, 72, $this->bold, [255, 255, 255]);
        $this->text('AssaultCube rankings, CTF ratings,', $x + 4, 212, 26, $this->regular, [228, 228, 231]);
        $this->text('Hall of Fame and live games', $x + 4, 248, 26, $this->regular, [228, 228, 231]);

        // totals
        $tx = $pad;
        foreach ($card['totals'] ?? [] as $label => $value) {
            $this->text($value, $tx, 372, 44, $this->bold, [255, 255, 255]);
            $this->text(strtoupper($label), $tx + 2, 402, 16, $this->regular, [161, 161, 170]);
            $tx += max($this->width($value, 44, $this->bold), $this->width(strtoupper($label), 16, $this->regular)) + 64;
        }

        // top 3 by CTF rating
        $y = 440;
        $this->rect($pad, $y, self::WIDTH - 2 * $pad, 140, [0, 0, 0], 45);
        $this->text('TOP CTF RATING', $pad + 24, $y + 34, 16, $this->regular, [161, 161, 170]);
        $colW = (int)((self::WIDTH - 2 * $pad - 48) / 3);
        foreach (array_slice($card['top'] ?? [], 0, 3) as $i => [$name, $rating, $type]) {
            $cx = $pad + 24 + $i * $colW;
            $value = number_format((float)$rating, 1);
            $this->text(($i + 1) . '.', $cx, $y + 96, 20, $this->bold, $i === 0 ? [253, 224, 71] : [161, 161, 170]);
            $this->text($value, $cx + 34, $y + 98, 30, $this->bold, $this->ratingColor((float)$rating));
            $nx = $cx + 34 + $this->width($value, 30, $this->bold) + 12;
            $this->text($this->fit($name, 20, $colW - ($nx - $cx) - 8), $nx, $y + 84, 20, $this->bold, [255, 255, 255]);
            $this->text($this->fit($type, 15, $colW - ($nx - $cx) - 8, $this->regular), $nx, $y + 108, 15, $this->regular, [161, 161, 170]);
        }

        $this->text('cubeladder.ovh', self::WIDTH - $pad - $this->width('cubeladder.ovh', 18, $this->regular), self::HEIGHT - 20, 18, $this->regular, [161, 161, 170]);

        return $this->jpeg();
    }

    /**
     * @param array $card title, subtitle, rows ([[rank, name, rating|null, points]])
     */
    public function ranking(array $card): string
    {
        $this->canvas(WWW_ROOT . 'img/bullet-wide.jpg', 55);
        $pad = 56;
        $this->logo(WWW_ROOT . 'img/brand/cubeladder-mark-transparent.png', $pad - 6, 34, 72);
        $this->text('cube', $pad + 84, 82, 30, $this->bold, [59, 130, 246]);
        $this->text('Ladder', $pad + 84 + $this->width('cube', 30, $this->bold), 82, 30, $this->bold, [255, 255, 255]);
        $this->text($card['title'], $pad, 160, 52, $this->bold, [255, 255, 255]);
        $this->text($card['subtitle'], $pad + 2, 198, 20, $this->regular, [212, 212, 216]);

        // two columns of five
        $rows = array_slice($card['rows'] ?? [], 0, 10);
        $gap = 24;
        $colW = (int)((self::WIDTH - 2 * $pad - $gap) / 2);
        $rowH = 70;
        $top = 236;
        foreach ($rows as $i => [$rank, $name, $rating, $points]) {
            $x = $pad + intdiv($i, 5) * ($colW + $gap);
            $y = $top + ($i % 5) * $rowH;
            $this->rect($x, $y, $colW, $rowH - 8, [0, 0, 0], $i % 2 ? 60 : 45);
            // rank | name with the points under it | rating
            $this->text((string)$rank, $x + 16, $y + 42, 24, $this->bold, $rank <= 3 ? [253, 224, 71] : [161, 161, 170]);
            $ratingText = $rating !== null ? number_format((float)$rating, 1) : '–';
            $rw = $this->width('9.9', 30, $this->bold);
            $this->text($this->fit($name, 22, $colW - 66 - $rw - 36), $x + 66, $y + 29, 22, $this->bold, [255, 255, 255]);
            $this->text($points . ' points', $x + 66, $y + 52, 15, $this->regular, [125, 211, 252]);
            $this->text($ratingText, $x + $colW - 16 - $rw, $y + 44, 30, $this->bold, $rating !== null ? $this->ratingColor((float)$rating) : [161, 161, 170]);
        }

        $this->text('cubeladder.ovh/players', self::WIDTH - $pad - $this->width('cubeladder.ovh/players', 18, $this->regular), self::HEIGHT - 16, 18, $this->regular, [161, 161, 170]);

        return $this->jpeg();
    }

    private function canvas(string $background, int $darken): void
    {
        $this->im = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($this->im, true);
        $src = is_file($background) ? @imagecreatefromstring((string)file_get_contents($background)) : false;
        if ($src) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $scale = max(self::WIDTH / $sw, self::HEIGHT / $sh);
            $cw = (int)(self::WIDTH / $scale);
            $ch = (int)(self::HEIGHT / $scale);
            imagecopyresampled($this->im, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), self::WIDTH, self::HEIGHT, $cw, $ch);
            imagedestroy($src);
        } else {
            $this->rect(0, 0, self::WIDTH, self::HEIGHT, [11, 18, 37], 0);
        }
        $this->rect(0, 0, self::WIDTH, self::HEIGHT, [0, 0, 0], $darken);
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

    private function ratingColor(float $rating): array
    {
        foreach (self::RATING_COLORS as [$from, $rgb]) {
            if ($rating >= $from) {
                return $rgb;
            }
        }

        return [255, 255, 255];
    }

    private function jpeg(): string
    {
        ob_start();
        imagejpeg($this->im, null, 86);
        imagedestroy($this->im);

        return (string)ob_get_clean();
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

    private function fit(string $s, int $size, int $max, ?string $font = null): string
    {
        $font ??= $this->bold;
        if ($this->width($s, $size, $font) <= $max) {
            return $s;
        }
        while (mb_strlen($s) > 1 && $this->width($s . '…', $size, $font) > $max) {
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
