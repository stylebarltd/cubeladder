<?php
declare(strict_types=1);

namespace App\Service;

use GdImage;
use RuntimeException;

/**
 * Link preview picture of a map page (og:image, 1200 x 630): the map's
 * screenshot as background, its name, rank and games, the six single-game
 * records with their holders and the top players by points. GD + the
 * bundled DejaVu fonts, like PlayerCardImage.
 */
class MapCardImage
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    private const FONT_DIRS = [
        ROOT . '/resources/fonts/',
        '/usr/share/fonts/truetype/dejavu/',
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
     * @param array $card title, name (file name of the map), subtitle,
     *   background (file|null), records ([[label, value, holder]]),
     *   top ([[name, points]])
     * @return string JPEG bytes
     */
    public function render(array $card): string
    {
        $w = self::WIDTH;
        $h = self::HEIGHT;
        $pad = 56;
        $white = [255, 255, 255];
        $grey = [161, 161, 170];
        $this->im = imagecreatetruecolor($w, $h);
        imagealphablending($this->im, true);
        $this->background($card['background'] ?? null);

        $this->text('cube', $pad, 74, 30, $this->bold, [59, 130, 246]);
        $this->text('Ladder', $pad + $this->width('cube', 30, $this->bold), 74, 30, $this->bold, $white);
        $tag = 'MAP';
        $this->text($tag, $w - $pad - $this->width($tag, 22, $this->bold), 70, 22, $this->bold, [147, 197, 253]);

        // title
        $size = 76;
        while ($size > 40 && $this->width($card['title'], $size, $this->bold) > $w - 2 * $pad) {
            $size -= 4;
        }
        $this->text($this->fit($card['title'], $size, $w - 2 * $pad), $pad, 190, $size, $this->bold, $white);
        $this->text($card['subtitle'], $pad, 238, 24, $this->regular, [212, 212, 216]);

        // records: 3 x 2 boxes
        $records = array_slice($card['records'] ?? [], 0, 6);
        $gap = 16;
        $bw = (int)(($w - 2 * $pad - 2 * $gap) / 3);
        $bh = 104;
        $top = 282;
        foreach ($records as $i => [$label, $value, $holder]) {
            $x = $pad + ($i % 3) * ($bw + $gap);
            $y = $top + intdiv($i, 3) * ($bh + $gap);
            $this->rect($x, $y, $bw, $bh, [0, 0, 0], 45);
            $this->text(strtoupper($label), $x + 18, $y + 28, 15, $this->regular, $grey);
            $this->text($value, $x + 18, $y + 66, 30, $this->bold, [125, 211, 252]);
            $this->text($this->fit($holder, 20, $bw - 36, $this->bold), $x + 18, $y + 94, 20, $this->bold, $white);
        }

        // top players
        if (!empty($card['top'])) {
            $line = 'Top players ';
            $this->text($line, $pad, $h - 30, 18, $this->regular, $grey);
            $x = $pad + $this->width($line, 18, $this->regular);
            foreach ($card['top'] as $i => [$name, $points]) {
                $text = ($i + 1) . '. ' . $name . ' ' . $points;
                if ($x + $this->width($text, 18, $this->bold) > $w - $pad) {
                    break;
                }
                $this->text($text, $x, $h - 30, 18, $this->bold, $i === 0 ? [253, 224, 71] : [228, 228, 231]);
                $x += $this->width($text, 18, $this->bold) + 24;
            }
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
        $this->rect(0, 0, self::WIDTH, self::HEIGHT, [0, 0, 0], 55);
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
