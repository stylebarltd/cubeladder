<?php
declare(strict_types=1);

namespace App\Service;

use App\View\Helper\LayoutHelper;
use Cake\View\View;
use GdImage;
use RuntimeException;

/**
 * Renders the live scoreboard of one server as a JPEG for Discord (GD +
 * DejaVu fonts, no browser needed): the map screenshot as background like
 * on the Live page, map / mode / time left on top, then CLA (red) and RVSF
 * (blue) side by side with their team score – or one table outside team
 * modes – with flags, frags and deaths per player. The deciding column
 * (flags in flag modes, frags otherwise) is printed bold.
 */
class LiveScoreboardImage
{
    private const WIDTH = 1200;
    private const PAD = 32;
    private const ROW = 42;
    private const MAX_ROWS = 12;

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
     * @param array $s one polled server (DiscordLiveService::poll()); a
     *   finished game (DiscordResultsCommand) may override 'meta' (second
     *   header line), 'badge' ([big, small] top right), 'footer', 'team_scores'
     *   (final score per team) and 'winner' (team)
     * @return string JPEG bytes
     */
    public function render(array $s): string
    {
        $players = array_values(array_filter($s['players'], fn($p) => empty($p['is_spectator'])));
        $byFlags = in_array((int)$s['mode'], AcExtInfoService::FLAG_MODES, true);
        $teamMode = in_array((int)$s['mode'], AcExtInfoService::TEAM_MODES, true);
        $key = $byFlags ? 'flags' : 'frags';
        $sort = fn($a, $b) => [$b[$key], $b['frags'], $a['deaths']] <=> [$a[$key], $a['frags'], $b['deaths']];

        $columns = [];
        if ($teamMode) {
            $teams = ['CLA' => [], 'RVSF' => []];
            foreach ($players as $p) {
                $teams[strtoupper(trim($p['team']))][] = $p;
            }
            foreach (['CLA', 'RVSF'] as $team) {
                usort($teams[$team], $sort);
                $columns[] = ['team' => $team, 'players' => $teams[$team], 'score' => $s['team_scores'][$team] ?? array_sum(array_column($teams[$team], $key))];
            }
        } else {
            usort($players, $sort);
            $columns[] = ['team' => null, 'players' => $players, 'score' => null];
        }

        $rows = min(self::MAX_ROWS, max(array_map(fn($c) => count($c['players']), $columns) ?: [0]));
        $more = max(array_map(fn($c) => count($c['players']), $columns) ?: [0]) > self::MAX_ROWS;
        $tableTop = 150;
        $height = max(600, $tableTop + 64 + 36 + max(1, $rows) * self::ROW + ($more ? self::ROW : 0) + 70);

        $this->im = imagecreatetruecolor(self::WIDTH, $height);
        imagealphablending($this->im, true);
        $this->background((string)$s['map'], $height);

        // header: map, mode · server · time left
        $map = (new LayoutHelper(new View()))->cleanMapName((string)$s['map']) ?: (string)$s['map'];
        $this->text(strtoupper($map), self::PAD, 72, 44, $this->bold, [255, 255, 255]);
        $meta = $s['meta'] ?? strtoupper((string)($s['mode_name'] ?? '?')) . '  ·  ' . $s['name']
            . ($s['minremain'] !== null ? sprintf('  ·  %d min left', $s['minremain']) : '');
        $this->text($meta, self::PAD, 112, 20, $this->regular, [212, 212, 216]);
        [$count, $small] = $s['badge'] ?? [sprintf('%d/%d', (int)$s['numplayers'], (int)($s['maxclients'] ?? 0)), 'players'];
        $this->text($count, self::WIDTH - self::PAD - $this->width($count, 30, $this->bold), 72, 30, $this->bold, [134, 239, 172]);
        $this->text($small, self::WIDTH - self::PAD - $this->width($small, 16, $this->regular), 104, 16, $this->regular, [161, 161, 170]);

        $gap = 24;
        $colWidth = (int)((self::WIDTH - 2 * self::PAD - (count($columns) - 1) * $gap) / count($columns));
        $scores = array_column($columns, 'score');
        $leader = $teamMode && count(array_unique($scores)) > 1 ? max($scores) : null;
        foreach ($columns as $i => $c) {
            $x = self::PAD + $i * ($colWidth + $gap);
            $leads = array_key_exists('winner', $s)
                ? $c['team'] !== null && $c['team'] === $s['winner']
                : $c['score'] !== null && $c['score'] === $leader;
            $this->column($c, $x, $tableTop, $colWidth, $rows, $byFlags, $leads);
        }

        // no clock: an unchanged scoreboard must give the same image (Discord
        // shows "updated …" itself), so it is not uploaded again
        $foot = $s['footer'] ?? 'cubeladder.ovh/live';
        $this->text($foot, self::WIDTH - self::PAD - $this->width($foot, 15, $this->regular), $height - 24, 15, $this->regular, [161, 161, 170]);

        ob_start();
        imagejpeg($this->im, null, 85);
        imagedestroy($this->im);

        return (string)ob_get_clean();
    }

    /**
     * One table: team bar (team mode), header row, player rows.
     */
    private function column(array $c, int $x, int $y, int $w, int $rows, bool $byFlags, bool $leads): void
    {
        $tint = match ($c['team']) {
            'CLA' => [220, 38, 38],
            'RVSF' => [37, 99, 235],
            default => [82, 82, 91],
        };

        // translucent panel behind the whole table
        $panelHeight = 64 + 36 + max(1, $rows) * self::ROW + (count($c['players']) > self::MAX_ROWS ? self::ROW : 0) + 8;
        $this->rect($x, $y, $w, $panelHeight, [0, 0, 0], 50);

        // team bar: name left, score right
        $this->rect($x, $y, $w, 64, $tint, $c['team'] ? 30 : 60);
        $label = $c['team'] ? ($leads ? $c['team'] . '  ★' : $c['team']) : 'SCOREBOARD';
        $this->text($label, $x + 18, $y + 44, 28, $this->bold, [255, 255, 255]);
        if ($c['score'] !== null) {
            $score = (string)$c['score'];
            $this->text($score, $x + $w - 18 - $this->width($score, 36, $this->bold), $y + 48, 36, $this->bold, [255, 255, 255]);
            $unit = $byFlags ? 'flags' : 'frags';
            $this->text($unit, $x + $w - 26 - $this->width($score, 36, $this->bold) - $this->width($unit, 15, $this->regular), $y + 44, 15, $this->regular, [228, 228, 231]);
        }

        // header row: # player | flags frags deaths (numbers right-aligned)
        $num = 78;
        $cols = [['flags', 'FL'], ['frags', 'FR'], ['deaths', 'DE']];
        $hy = $y + 64 + 26;
        $grey = [161, 161, 170];
        $this->text('#', $x + 14, $hy, 15, $this->bold, $grey);
        $this->text('PLAYER', $x + 52, $hy, 15, $this->bold, $grey);
        foreach ($cols as $j => [$field, $head]) {
            $right = $x + $w - 18 - (count($cols) - 1 - $j) * $num;
            $decides = ($field === 'flags') === $byFlags && $field !== 'deaths';
            $this->text($head, $right - $this->width($head, 15, $this->bold), $hy, 15, $this->bold, $decides ? [255, 255, 255] : $grey);
        }

        $nameWidth = $w - 52 - 18 - count($cols) * $num - 10;
        $ry = $y + 64 + 36;
        foreach (array_slice($c['players'], 0, self::MAX_ROWS) as $i => $p) {
            if ($i % 2 === 1) {
                $this->rect($x, $ry, $w, self::ROW, [255, 255, 255], 120);
            }
            $base = $ry + 29;
            $this->text((string)($i + 1), $x + 14, $base, 18, $this->regular, $i === 0 ? [253, 224, 71] : $grey);
            $this->text($this->fit((string)$p['name'], 21, $nameWidth), $x + 52, $base, 21, $this->bold, [255, 255, 255]);
            foreach ($cols as $j => [$field]) {
                $value = (string)(int)$p[$field];
                $decides = ($field === 'flags') === $byFlags && $field !== 'deaths';
                $font = $decides ? $this->bold : $this->regular;
                $color = $decides ? [255, 255, 255] : ($field === 'deaths' ? [161, 161, 170] : [212, 212, 216]);
                $right = $x + $w - 18 - (count($cols) - 1 - $j) * $num;
                $this->text($value, $right - $this->width($value, 21, $font), $base, 21, $font, $color);
            }
            $ry += self::ROW;
        }
        if (!$c['players']) {
            $this->text('nobody', $x + 52, $ry + 29, 19, $this->regular, $grey);
        }
        if (count($c['players']) > self::MAX_ROWS) {
            $this->text(sprintf('+%d more', count($c['players']) - self::MAX_ROWS), $x + 52, $ry + 29, 17, $this->regular, $grey);
        }
    }

    /**
     * The map screenshot (or the bullet artwork) cropped to cover the image,
     * darkened so the tables stay readable.
     */
    private function background(string $map, int $height): void
    {
        $file = is_file(WWW_ROOT . 'img/maps/' . $map . '.jpg')
            ? WWW_ROOT . 'img/maps/' . $map . '.jpg'
            : WWW_ROOT . 'img/bullet-wide.jpg';
        $src = is_file($file) ? @imagecreatefromjpeg($file) : false;
        if ($src) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $scale = max(self::WIDTH / $sw, $height / $sh);
            $cw = (int)(self::WIDTH / $scale);
            $ch = (int)($height / $scale);
            imagecopyresampled($this->im, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), self::WIDTH, $height, $cw, $ch);
            imagedestroy($src);
        } else {
            $this->rect(0, 0, self::WIDTH, $height, [24, 24, 27], 0);
        }
        // overall darkening + a darker band behind the header
        $this->rect(0, 0, self::WIDTH, $height, [0, 0, 0], 60);
        for ($i = 0; $i < 140; $i += 4) {
            $this->rect(0, $i, self::WIDTH, 4, [0, 0, 0], 70 + (int)($i / 140 * 57));
        }
    }

    /** Filled rectangle; $alpha 0 (opaque) … 127 (transparent) */
    private function rect(int $x, int $y, int $w, int $h, array $rgb, int $alpha): void
    {
        imagefilledrectangle($this->im, $x, $y, $x + $w - 1, $y + $h - 1, imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha));
    }

    private function text(string $s, int $x, int $y, int $size, string $font, array $rgb): void
    {
        // soft shadow, then the text
        imagettftext($this->im, $size, 0, $x + 2, $y + 2, imagecolorallocatealpha($this->im, 0, 0, 0, 40), $font, $s);
        imagettftext($this->im, $size, 0, $x, $y, imagecolorallocate($this->im, $rgb[0], $rgb[1], $rgb[2]), $font, $s);
    }

    private function width(string $s, int $size, string $font): int
    {
        $box = imagettfbbox($size, 0, $font, $s);

        return $box ? abs($box[2] - $box[0]) : 0;
    }

    /** Shorten $s with "…" until it fits $max pixels */
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
        throw new RuntimeException("Font {$file} not found (looked in " . implode(', ', self::FONT_DIRS) . ')');
    }
}
