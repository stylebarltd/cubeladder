<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Small 16:9 thumbnails of the map screenshots (webroot/img/maps/*.jpg, up
 * to 4K / 8 MB each) for the maps dropdown: webroot/img/maps/thumbs/.
 * Only missing or outdated thumbs are (re)built - run after adding maps.
 *
 *   bin/cake map_thumbs [--force]
 */
class MapThumbsCommand extends Command
{
    public const WIDTH = 320;
    public const HEIGHT = 180;

    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Build thumbnails for the map screenshots.')
            ->addOption('force', ['boolean' => true, 'help' => 'Rebuild all thumbnails']);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $src = WWW_ROOT . 'img' . DS . 'maps' . DS;
        $dst = $src . 'thumbs' . DS;
        if (!is_dir($dst)) {
            mkdir($dst, 0775, true);
        }

        $built = 0;
        foreach (glob($src . '*.jpg') as $file) {
            $target = $dst . basename($file);
            if (!$args->getOption('force') && is_file($target) && filemtime($target) >= filemtime($file)) {
                continue;
            }
            $img = @imagecreatefromjpeg($file);
            if (!$img) {
                $io->warning('Cannot read ' . basename($file));
                continue;
            }
            // cover-crop to 16:9
            [$w, $h] = [imagesx($img), imagesy($img)];
            $cropW = min($w, (int)round($h * self::WIDTH / self::HEIGHT));
            $cropH = (int)round($cropW * self::HEIGHT / self::WIDTH);
            $thumb = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
            imagecopyresampled(
                $thumb, $img, 0, 0,
                (int)(($w - $cropW) / 2), (int)(($h - $cropH) / 2),
                self::WIDTH, self::HEIGHT, $cropW, $cropH
            );
            imagejpeg($thumb, $target, 80);
            imagedestroy($img);
            imagedestroy($thumb);
            $built++;
        }
        $io->out("# Map thumbs: $built built");

        return self::CODE_SUCCESS;
    }
}
