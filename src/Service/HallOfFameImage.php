<?php
declare(strict_types=1);

namespace App\Service;

use GdImage;
use RuntimeException;

/**
 * Picture of a Hall of Fame change for Discord (1200 x 675): a random player
 * photo as background (like the Hall of Fame page), the category with its
 * icon, the news as headline and the whole top 10 with values - the players
 * the news is about highlighted. GD + the bundled DejaVu / Font Awesome
 * fonts, like MapCardImage.
 */
class HallOfFameImage
{
    public const WIDTH = 1200;
    public const HEIGHT = 675;

    private const FONT_DIRS = [
        ROOT . '/resources/fonts/',
        '/usr/share/fonts/truetype/dejavu/',
    ];

    /** Font Awesome code points (fa-solid-900.ttf) */
    public const ICONS = [
        'skull' => 0xf54c, 'crosshairs' => 0xf05b, 'flag' => 0xf024, 'fire' => 0xf06d,
        'handshake' => 0xf2b5, 'trophy' => 0xf091, 'play' => 0xf04b,
    ];

    private const GOLD = [250, 204, 21];
    private const WHITE = [255, 255, 255];
    private const MUTED = [161, 161, 170];

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
     * @param array $card title, subtitle, icon (ICONS key or resources/weapons/<name>.png
     *   as "png:<name>"), headline, background (file|null), tag + tagIcon (top right,
     *   default HALL OF FAME with the trophy; the aim trainer's card uses its own),
     *   rows: list of [pos, name, value (text), mark (''|NEW|▲2|▼1|RECORD)]
     * @return string JPEG bytes
     */
    public function render(array $card): string
    {
        $w = self::WIDTH;
        $h = self::HEIGHT;
        $pad = 52;
        $this->im = imagecreatetruecolor($w, $h);
        imagealphablending($this->im, true);
        $this->background($card['background'] ?? null);

        // brand + tag
        $this->text('cube', $pad, 66, 26, $this->bold, [59, 130, 246]);
        $this->text('Ladder', $pad + $this->width('cube', 26, $this->bold), 66, 26, $this->bold, self::WHITE);
        $tag = (string)($card['tag'] ?? 'HALL OF FAME');
        $this->icon((string)($card['tagIcon'] ?? 'trophy'), $w - $pad - $this->width($tag, 20, $this->bold) - 34, 64, 20, self::GOLD);
        $this->text($tag, $w - $pad - $this->width($tag, 20, $this->bold), 64, 20, $this->bold, self::GOLD);

        // category: icon, title, subtitle
        $icon = (string)($card['icon'] ?? 'trophy');
        if (str_starts_with($icon, 'png:')) {
            $this->png(ROOT . '/resources/weapons/' . substr($icon, 4) . '.png', $pad, 98, 76);
        } else {
            $this->icon($icon, $pad + 4, 164, 56, self::GOLD);
        }
        $tx = $pad + 100;
        $this->text($this->fit(strtoupper($card['title']), 54, $w - $tx - $pad), $tx, 156, 54, $this->bold, self::WHITE);
        $this->text($card['subtitle'] ?? '', $tx, 194, 19, $this->regular, [212, 212, 216]);

        // the news
        $this->rect($pad, 222, $w - 2 * $pad, 64, [234, 179, 8], 88);
        $this->rect($pad, 222, 6, 64, self::GOLD, 0);
        $size = 25;
        while ($size > 17 && $this->width((string)$card['headline'], $size, $this->bold) > $w - 2 * $pad - 40) {
            $size--;
        }
        $this->text($this->fit((string)$card['headline'], $size, $w - 2 * $pad - 40), $pad + 24, 264, $size, $this->bold, self::WHITE);

        // top 10: two columns of five
        $gap = 18;
        $cw = (int)(($w - 2 * $pad - $gap) / 2);
        $rh = 62;
        $top = 306;
        foreach (array_slice($card['rows'], 0, 10) as $i => [$pos, $name, $value, $mark]) {
            $x = $pad + intdiv($i, 5) * ($cw + $gap);
            $y = $top + ($i % 5) * ($rh + 8);
            // the news glows; a player only pushed down gets a quiet badge
            $hot = $mark !== '' && !str_starts_with($mark, '▼');
            $this->rect($x, $y, $cw, $rh, $hot ? [234, 179, 8] : [0, 0, 0], $hot ? 80 : 50);
            if ($hot) {
                $this->rect($x, $y, 4, $rh, self::GOLD, 0);
            }
            $rankColor = match ((int)$pos) { 1 => self::GOLD, 2 => [226, 232, 240], 3 => [251, 146, 60], default => self::MUTED };
            $this->text('#' . $pos, $x + 16, $y + 41, 22, $this->bold, $rankColor);
            $vw = $this->width($value, 24, $this->bold);
            $this->text($value, $x + $cw - 16 - $vw, $y + 42, 24, $this->bold, $hot ? self::GOLD : [125, 211, 252]);
            $mw = $mark !== '' ? $this->width($mark, 14, $this->bold) + 18 : 0;
            $nameMax = $cw - 90 - $vw - 32 - $mw;
            $this->text($this->fit($name, 21, $nameMax), $x + 90, $y + 41, 21, $this->bold, self::WHITE);
            if ($mark !== '') {
                $mx = $x + 90 + $this->width($this->fit($name, 21, $nameMax), 21, $this->bold) + 12;
                $this->rect($mx, $y + 21, $mw - 4, 26, $hot ? self::GOLD : [82, 82, 91], 0);
                $this->text($mark, $mx + 7, $y + 40, 14, $this->bold, $hot ? [0, 0, 0] : [228, 228, 231], false);
            }
        }

        $foot = (string)($card['footer'] ?? 'cubeladder.ovh/players/hall_of_fame');
        $this->text($foot, $w - $pad - $this->width($foot, 15, $this->regular), $h - 14, 15, $this->regular, self::MUTED);

        ob_start();
        imagejpeg($this->im, null, 86);
        imagedestroy($this->im);

        return (string)ob_get_clean();
    }

    /** A random player photo from webroot/img/players (not in git), or null */
    public static function randomBackground(): ?string
    {
        $files = glob(WWW_ROOT . 'img/players/*.jpg') ?: [];

        return $files ? $files[array_rand($files)] : null;
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
        // darker on the left, like the Hall of Fame page's gradient
        for ($x = 0; $x < self::WIDTH; $x += 8) {
            $alpha = (int)(25 + 40 * $x / self::WIDTH);
            $this->rect($x, 0, 8, self::HEIGHT, [0, 0, 0], $alpha);
        }
    }

    private function rect(int $x, int $y, int $w, int $h, array $rgb, int $alpha): void
    {
        imagefilledrectangle($this->im, $x, $y, $x + $w - 1, $y + $h - 1, imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha));
    }

    private function text(string $s, int $x, int $y, int $size, string $font, array $rgb, bool $shadow = true): void
    {
        if ($shadow) {
            imagettftext($this->im, $size, 0, $x + 2, $y + 2, imagecolorallocatealpha($this->im, 0, 0, 0, 40), $font, $s);
        }
        imagettftext($this->im, $size, 0, $x, $y, imagecolorallocate($this->im, $rgb[0], $rgb[1], $rgb[2]), $font, $s);
    }

    private function icon(string $name, int $x, int $baseline, int $size, array $rgb): void
    {
        imagettftext($this->im, $size, 0, $x, $baseline, imagecolorallocate($this->im, $rgb[0], $rgb[1], $rgb[2]),
            $this->icons, mb_chr(self::ICONS[$name] ?? self::ICONS['trophy'], 'UTF-8'));
    }

    private function png(string $file, int $x, int $y, int $size): void
    {
        $src = is_file($file) ? @imagecreatefrompng($file) : false;
        if (!$src) {
            return;
        }
        imagecopyresampled($this->im, $src, $x, $y, 0, 0, $size, $size, imagesx($src), imagesy($src));
        imagedestroy($src);
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
