<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\QrHunt;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * The QR hunt avatar sets, 400x400 JPGs in webroot/img/players:
 * - explorer avatars (unlock 'avatars1', 5 points): the illustrated portraits
 *   img/illustrated_players/ai-generated-*.png as explorer-01.jpg .. explorer-NN.jpg;
 * - the last avatars (unlock 'avatars2', 10 points, every code): the emblems
 *   img/illustrated_players/zombie-*.png (not used by the explorer set) as
 *   legend-01.jpg .. legend-NN.jpg, on a dark gold ground with a gold ring and
 *   a gold tint, so they stand out from every other avatar.
 * Both folders are not in git; run this on every machine after copying the
 * source pictures. The numbering follows the sorted source names, so a
 * re-run keeps every number (players.picture stores it). Existing files are
 * kept unless --force.
 *
 *   bin/cake explorer_avatars [--force] [--set explorer|legend]
 */
class ExplorerAvatarsCommand extends Command
{
    public const SIZE = 400;
    public const QUALITY = 88;

    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Build the explorer avatars (img/players/explorer-NN.jpg) from img/illustrated_players.')
            ->addOption('force', ['boolean' => true, 'help' => 'Rebuild existing files'])
            ->addOption('set', ['help' => 'Only this set (explorer or legend); both by default', 'choices' => ['explorer', 'legend']]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $set = $args->getOption('set');
        $code = static::CODE_SUCCESS;
        if ($set === null || $set === 'explorer') {
            $code = $this->explorer($args, $io);
        }
        if ($set === null || $set === 'legend') {
            $code = max($code, $this->legend($args, $io));
        }

        return $code;
    }

    /** The explorer avatars (unlock 'avatars1') */
    private function explorer(Arguments $args, ConsoleIo $io): int
    {
        $sources = glob(WWW_ROOT . 'img' . DS . 'illustrated_players' . DS . 'ai-generated-*.png') ?: [];
        sort($sources, SORT_STRING);
        if (!$sources) {
            $io->error('No source pictures in webroot/img/illustrated_players/ (ai-generated-*.png).');

            return static::CODE_ERROR;
        }
        $dst = WWW_ROOT . 'img' . DS . 'players' . DS;
        if (!is_dir($dst)) {
            mkdir($dst, 0775, true);
        }

        $built = 0;
        foreach ($sources as $i => $file) {
            $target = $dst . sprintf('%s%02d.jpg', QrHunt::EXPLORER_AVATAR_PREFIX, $i + 1);
            if (!$args->getOption('force') && is_file($target)) {
                continue;
            }
            // some "png" files are JPEGs: let GD sniff the format
            $img = @imagecreatefromstring((string)file_get_contents($file));
            if (!$img) {
                $io->warning('Cannot read ' . basename($file));
                continue;
            }
            // square cover-crop, transparent parts on the site's dark background
            [$w, $h] = [imagesx($img), imagesy($img)];
            $side = min($w, $h);
            $out = imagecreatetruecolor(self::SIZE, self::SIZE);
            imagefill($out, 0, 0, imagecolorallocate($out, 24, 24, 27));
            imagecopyresampled($out, $img, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2), self::SIZE, self::SIZE, $side, $side);
            imageinterlace($out, true);
            imagejpeg($out, $target, self::QUALITY);
            imagedestroy($img);
            imagedestroy($out);
            $io->verbose(basename($file) . ' -> ' . basename($target));
            $built++;
        }
        $io->success(sprintf('%d explorer avatar(s) built, %d sources.', $built, count($sources)));

        return static::CODE_SUCCESS;
    }

    /** The last avatars (unlock 'avatars2'): gold ground, gold ring, gold-tinted emblem */
    private function legend(Arguments $args, ConsoleIo $io): int
    {
        $sources = glob(WWW_ROOT . 'img' . DS . 'illustrated_players' . DS . 'zombie-*.png') ?: [];
        sort($sources, SORT_STRING);
        if (!$sources) {
            $io->error('No source pictures in webroot/img/illustrated_players/ (zombie-*.png).');

            return static::CODE_ERROR;
        }
        $dst = WWW_ROOT . 'img' . DS . 'players' . DS;
        if (!is_dir($dst)) {
            mkdir($dst, 0775, true);
        }

        $n = self::SIZE;
        $c = ($n - 1) / 2;
        $ring = $n / 2 - 2;          // outer edge of the gold ring (avatars show round)
        $ringW = 14;
        $built = 0;
        foreach ($sources as $i => $file) {
            $target = $dst . sprintf('%s%02d.jpg', QrHunt::LEGEND_AVATAR_PREFIX, $i + 1);
            if (!$args->getOption('force') && is_file($target)) {
                continue;
            }
            $img = @imagecreatefromstring((string)file_get_contents($file));
            if (!$img) {
                $io->warning('Cannot read ' . basename($file));
                continue;
            }
            // the emblem, square, scaled into the middle (alpha kept for the transparent ones)
            [$w, $h] = [imagesx($img), imagesy($img)];
            $side = min($w, $h);
            // an emblem on white or transparent floats on the gold ground; one with its own
            // (dark) ground fills the whole circle, so no square shows
            $corner = imagecolorsforindex($img, imagecolorat($img, 2, 2));
            $floats = $corner['alpha'] > 100 || min($corner['red'], $corner['green'], $corner['blue']) > 225;
            $emblem = $floats ? (int)round($n * 0.78) : $n;
            $em = imagecreatetruecolor($emblem, $emblem);
            imagealphablending($em, false);
            imagesavealpha($em, true);
            imagefill($em, 0, 0, imagecolorallocatealpha($em, 255, 255, 255, 127));
            imagecopyresampled($em, $img, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2), $emblem, $emblem, $side, $side);
            imagedestroy($img);

            $out = imagecreatetruecolor($n, $n);
            $off = (int)(($n - $emblem) / 2);
            for ($y = 0; $y < $n; $y++) {
                for ($x = 0; $x < $n; $x++) {
                    $d = sqrt(($x - $c) ** 2 + ($y - $c) ** 2);
                    // ground: warm dark gold in the middle, near black at the edge
                    $t = min(1.0, $d / $ring);
                    $bg = [72 - 58 * $t, 50 - 42 * $t, 12 - 8 * $t];
                    $px = $bg;
                    $ex = $x - $off;
                    $ey = $y - $off;
                    if ($ex >= 0 && $ey >= 0 && $ex < $emblem && $ey < $emblem) {
                        $rgba = imagecolorat($em, $ex, $ey);
                        $a = 1 - (($rgba >> 24) & 0x7F) / 127;
                        [$r, $g, $b] = [($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF];
                        // a white ground (the JPEG sources) counts as transparent
                        $lo = min($r, $g, $b);
                        if ($lo > 225 && max($r, $g, $b) - $lo < 24) {
                            $a *= max(0.0, (245 - $lo) / 20);
                        }
                        // gold tint: 40% towards a gold ramp of the pixel's brightness
                        $l = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
                        $gold = [255 * min(1, $l * 1.15), 205 * $l, 70 * $l];
                        $tint = [$r * 0.6 + $gold[0] * 0.4, $g * 0.6 + $gold[1] * 0.4, $b * 0.6 + $gold[2] * 0.4];
                        $px = [$bg[0] + ($tint[0] - $bg[0]) * $a, $bg[1] + ($tint[1] - $bg[1]) * $a, $bg[2] + ($tint[2] - $bg[2]) * $a];
                    }
                    // the gold ring, lighter at the top left, with a soft edge
                    if ($d > $ring - $ringW - 1 && $d < $ring + 1) {
                        $edge = min(1.0, $d - ($ring - $ringW - 1), $ring + 1 - $d);
                        $shine = 0.75 + 0.25 * (($c - $x) + ($c - $y)) / (2 * $c);
                        $ringC = [250 * $shine, 204 * $shine, 21 + 60 * $shine];
                        $px = [$px[0] + ($ringC[0] - $px[0]) * $edge, $px[1] + ($ringC[1] - $px[1]) * $edge, $px[2] + ($ringC[2] - $px[2]) * $edge];
                    } elseif ($d >= $ring + 1) {
                        $px = [10, 8, 4];
                    }
                    [$r, $g, $b] = array_map(fn($v) => max(0, min(255, (int)round($v))), $px);
                    imagesetpixel($out, $x, $y, ($r << 16) | ($g << 8) | $b);
                }
            }
            imagedestroy($em);
            imageinterlace($out, true);
            imagejpeg($out, $target, self::QUALITY);
            imagedestroy($out);
            $io->verbose(basename($file) . ' -> ' . basename($target));
            $built++;
        }
        $io->success(sprintf('%d legend avatar(s) built, %d sources.', $built, count($sources)));

        return static::CODE_SUCCESS;
    }
}
