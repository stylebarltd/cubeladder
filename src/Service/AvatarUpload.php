<?php
declare(strict_types=1);

namespace App\Service;

use Psr\Http\Message\UploadedFileInterface;

/**
 * A player's own picture (QR hunt unlock 'upload', 7 points). The upload is
 * checked by its content, not by its name or the browser's type: only JPEG,
 * PNG or WebP up to MAX_BYTES and MAX_SIDE pixels (checked from the header
 * before anything is decoded). GD decodes it and writes a new square JPEG
 * (centre crop, SIZE pixels), so nothing of the original file - EXIF, GPS,
 * comments, hidden payloads - reaches the site. The file name is random:
 * img/players/upload-<24 hex>.jpg (not in git).
 */
class AvatarUpload
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_SIDE = 4096;
    public const MAX_PIXELS = 12_000_000;   // ~60 MB for GD at most
    public const SIZE = 256;
    public const QUALITY = 86;

    /** image type => label; anything else (SVG, GIF, BMP, ...) is refused */
    private const TYPES = [IMAGETYPE_JPEG => 'JPEG', IMAGETYPE_PNG => 'PNG', IMAGETYPE_WEBP => 'WebP'];

    /**
     * Checks and stores an upload.
     *
     * @return array{0: ?string, 1: ?string} [file name in img/players, error for the player]
     */
    public function store(?UploadedFileInterface $file): array
    {
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return [null, 'Pick a picture first.'];
        }
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return [null, 'That picture is too big: up to 2 MB, please.'];
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return [null, 'The upload did not work, please try again.'];
        }
        if ((int)$file->getSize() > self::MAX_BYTES) {
            return [null, 'That picture is too big: up to 2 MB, please.'];
        }
        $data = (string)$file->getStream()->getContents();
        if ($data === '' || strlen($data) > self::MAX_BYTES) {
            return [null, $data === '' ? 'That file is empty.' : 'That picture is too big: up to 2 MB, please.'];
        }

        // the real type and size from the file's header, before decoding anything
        $info = @getimagesizefromstring($data);
        if (!$info || !isset(self::TYPES[$info[2]])) {
            return [null, 'Only JPEG, PNG or WebP pictures, please.'];
        }
        [$w, $h] = [(int)$info[0], (int)$info[1]];
        if ($w < 1 || $h < 1 || $w > self::MAX_SIDE || $h > self::MAX_SIDE || $w * $h > self::MAX_PIXELS) {
            return [null, sprintf('That picture is too large: up to %d x %d pixels, please.', self::MAX_SIDE, self::MAX_SIDE)];
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromstring($data),
            IMAGETYPE_PNG => @imagecreatefromstring($data),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromstring($data) : false,
        };
        if (!$img || imagesx($img) !== $w || imagesy($img) !== $h) {
            return [null, 'That picture cannot be read. Try another one (JPEG, PNG or WebP).'];
        }
        if ($info[2] === IMAGETYPE_JPEG) {
            $img = $this->orient($img, $data);
            [$w, $h] = [imagesx($img), imagesy($img)];
        }

        // square centre crop on the site's dark background (transparent PNG / WebP)
        $side = min($w, $h);
        $out = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($out, 0, 0, imagecolorallocate($out, 24, 24, 27));
        imagecopyresampled($out, $img, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);
        imagedestroy($img);

        $dir = WWW_ROOT . 'img' . DS . 'players' . DS;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = QrHunt::UPLOAD_AVATAR_PREFIX . bin2hex(random_bytes(12)) . '.jpg';
        $tmp = $dir . '.' . $name . '.tmp';
        imageinterlace($out, true);
        $ok = imagejpeg($out, $tmp, self::QUALITY);
        imagedestroy($out);
        if (!$ok || !rename($tmp, $dir . $name)) {
            @unlink($tmp);

            return [null, 'Your picture could not be saved, please try again.'];
        }
        @chmod($dir . $name, 0644);

        return [$name, null];
    }

    /** Deletes an uploaded picture (only upload-*.jpg in img/players, nothing else) */
    public static function delete(?string $picture): void
    {
        $picture = basename((string)$picture);
        if (QrHunt::isUploadedAvatar($picture) && preg_match('/^upload-[0-9a-f]{24}\.jpg$/', $picture)) {
            @unlink(WWW_ROOT . 'img' . DS . 'players' . DS . $picture);
        }
    }

    /** Phone photos: turn the picture upright by its EXIF orientation (the EXIF itself is dropped) */
    private function orient(\GdImage $img, string $data): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($data));
        $angle = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
        if ($angle === 0) {
            return $img;
        }
        $rotated = imagerotate($img, $angle, 0);
        if ($rotated === false) {
            return $img;
        }
        imagedestroy($img);

        return $rotated;
    }
}
