<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;
use DateTimeImmutable;

/**
 * Radar (/radar): which map is this? A round is QUESTIONS maps; each shows a
 * piece of the map's floor plan with CHOICES map names. The piece grows by
 * itself (STEPS: two thirds, three quarters, then the whole map, STEP_SECONDS
 * apart) and the question ends after QUESTION_SECONDS.
 *
 * The plans are drawn offline from the map files (tools/radar/radar_plans.py)
 * into resources/radar/: <map>.png, <map>.json (size, author, centres =
 * walkable spots a piece is centred on), layouts.json (maps with the same
 * layout are one answer: kasa is ac_casa) and pool.json (bin/cake
 * radar_pool: the POOL_SIZE answers most played in the last 90 days).
 *
 * Fair play: the questions are drawn at the start and stay on the server.
 * The page gets a question's choices, then the piece as an image from
 * piece() - a bigger step only once its time has come - and sends a choice;
 * the server keeps the time (from handing out the question to the answer)
 * and computes the points, the page's own clock and number are never
 * trusted. Like the aim trainer it is just for fun, only recognised players
 * (identity) are on the boards. The week's best round is a weekly
 * achievement (event_type radar), the all-time top 10 a Hall of Fame
 * category ('radar').
 */
class Radar
{
    public const QUESTIONS = 10;
    public const CHOICES = 4;
    public const POOL_SIZE = 16;
    /** Piece sizes: share of the plan's longer side (1 = the whole map). Easier since 2026-11-07: was 1/3, 1/2, 3/4 at 5 s, then 1/2 */
    public const STEPS = [2 / 3, 3 / 4, 1];
    public const STEP_SECONDS = 6;
    public const QUESTION_SECONDS = 18;
    /** Points per step: at the step's start, at its end (falling in between) */
    public const POINTS = [[100, 70], [60, 40], [30, 15]];
    /** Multiplier by the map's place in the pool (rarer maps are worth more): up to place => factor */
    public const RARITY = [5 => 1.0, 10 => 1.25, 16 => 1.5];
    /** Bonus for every STREAK right answers in a row */
    public const STREAK = 3;
    public const POINTS_STREAK = 20;
    /** The piece as the page gets it (px, square) */
    public const PIECE_PX = 480;
    /** Not human: right answers faster than this on average (ms), from MIN_FAST_COUNT right answers on */
    public const MIN_AVG_MS = 600;
    public const MIN_FAST_COUNT = 5;
    /** A started round can be played this long (s) */
    private const TOKEN_TTL = 1800;

    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? ROOT . '/resources/radar';
    }

    /** The pool (bin/cake radar_pool): list of answer, maps, plays, place, factor */
    public function pool(): array
    {
        $file = $this->dir . '/pool.json';

        return is_file($file) ? (array)json_decode((string)file_get_contents($file), true) : [];
    }

    public function ready(): bool
    {
        return count($this->pool()) >= self::CHOICES;
    }

    /** A plan's meta data (radar_plans.py): name, title, author, scale, width, height, centres */
    public function meta(string $map): ?array
    {
        if (!preg_match('/^[\w.-]{1,64}$/', $map) || !is_file($this->dir . "/$map.json")) {
            return null;
        }

        return (array)json_decode((string)file_get_contents($this->dir . "/$map.json"), true);
    }

    /** New round: draws the questions; the page gets the token and the number of questions */
    public function start(?string $playerId): array
    {
        $pool = $this->pool();
        if (count($pool) < self::CHOICES) {
            return ['error' => 'Radar is not ready yet.'];
        }
        // more often the maps people play, at most once per round (weights soften the top: square root of the plays)
        $left = $pool;
        $questions = [];
        while ($left && count($questions) < self::QUESTIONS) {
            $i = $this->weighted(array_map(fn($p) => sqrt(max(1, (int)$p['plays'])), $left));
            $p = array_values($left)[$i];
            unset($left[array_keys($left)[$i]]);
            $meta = $this->meta($p['answer']);
            if (!$meta || !$meta['centres']) {
                continue;
            }
            $others = array_values(array_filter(array_column($pool, 'answer'), fn($a) => $a !== $p['answer']));
            shuffle($others);
            $choices = array_merge([$p['answer']], array_slice($others, 0, self::CHOICES - 1));
            shuffle($choices);
            $questions[] = [
                'map' => $p['answer'],
                'factor' => (float)$p['factor'],
                'centre' => $meta['centres'][random_int(0, count($meta['centres']) - 1)],
                'choices' => $choices,
                'shown' => null, 'answer' => null, 'ms' => null, 'points' => null,
            ];
        }
        $token = bin2hex(random_bytes(16));
        $conn = ConnectionManager::get('default');
        $conn->execute('INSERT INTO radar_rounds (token, player_id, started_at, questions) VALUES (?, ?, ?, ?)',
            [$token, $playerId, gmdate('Y-m-d H:i:s'), json_encode($questions)]);
        // rounds never finished are of no use after a day
        $conn->execute('DELETE FROM radar_rounds WHERE finished_at IS NULL AND started_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);

        return ['token' => $token, 'questions' => count($questions), 'steps' => count(self::STEPS),
                'stepSeconds' => self::STEP_SECONDS, 'questionSeconds' => self::QUESTION_SECONDS];
    }

    /**
     * Hands out question $q (0-based): its choices. The clock starts now.
     * Only the next unanswered question, once (asking again gives the same
     * choices and keeps the first time).
     */
    public function question(string $token, int $q): array
    {
        [$round, $questions] = $this->load($token);
        if (!$round) {
            return ['error' => 'This round is unknown or already over.'];
        }
        if ($q !== $this->current($questions) || !isset($questions[$q])) {
            return ['error' => 'Not this question.'];
        }
        if ($questions[$q]['shown'] === null) {
            $questions[$q]['shown'] = microtime(true);
            $this->save((int)$round['id'], $questions);
        }

        return ['q' => $q, 'choices' => $questions[$q]['choices'], 'elapsed' => microtime(true) - $questions[$q]['shown']];
    }

    /**
     * The piece of question $q at step $s as PNG bytes, or null when it may
     * not be seen (yet): a step only once its time has come, while the
     * question is open; after the answer the whole plan (step 'full').
     */
    public function piece(string $token, int $q, string $step): ?string
    {
        [$round, $questions] = $this->load($token, true);
        $question = $questions[$q] ?? null;
        if (!$round || !$question || $question['shown'] === null) {
            return null;
        }
        $meta = $this->meta($question['map']);
        $png = $this->dir . '/' . $question['map'] . '.png';
        if (!$meta || !is_file($png)) {
            return null;
        }
        if ($step === 'full') {
            return $question['answer'] !== null ? $this->render($png, null, 0) : null;
        }
        $s = (int)$step;
        $elapsed = microtime(true) - $question['shown'];
        // 0.3 s early is fine: the page's timer and the request take a moment
        if ($question['answer'] !== null || !isset(self::STEPS[$s]) || $elapsed + 0.3 < $s * self::STEP_SECONDS
            || $elapsed > self::QUESTION_SECONDS + 5) {
            return null;
        }
        $side = (int)round(self::STEPS[$s] * max($meta['width'], $meta['height']));

        return $this->render($png, $question['centre'], $side);
    }

    /**
     * The answer to question $q ($choice null: time ran out). Returns
     * whether it was right, the right map, points, streak and running score.
     */
    public function answer(string $token, int $q, ?string $choice): array
    {
        [$round, $questions] = $this->load($token);
        if (!$round) {
            return ['error' => 'This round is unknown or already over.'];
        }
        if ($q !== $this->current($questions) || $questions[$q]['shown'] === null) {
            return ['error' => 'Not this question.'];
        }
        $ms = (int)round(1000 * (microtime(true) - $questions[$q]['shown']));
        $right = $choice !== null && $choice === $questions[$q]['map'] && $ms <= self::QUESTION_SECONDS * 1000;
        $questions[$q]['answer'] = $choice ?? '';
        $questions[$q]['ms'] = $ms;
        $questions[$q]['points'] = $right ? self::points($ms, (float)$questions[$q]['factor']) : 0;
        $this->save((int)$round['id'], $questions);
        $r = self::score($questions);
        $meta = $this->meta($questions[$q]['map']);

        return [
            'right' => $right, 'map' => $questions[$q]['map'], 'title' => $meta['title'] ?? '', 'author' => $meta['author'] ?? '',
            'points' => $questions[$q]['points'], 'ms' => $ms, 'streak' => $r['streak'], 'streakBonus' => $r['lastBonus'],
            'score' => $r['score'], 'last' => $q === count($questions) - 1,
        ];
    }

    /** Points of a right answer after $ms: by step, falling within it, times the map's factor */
    public static function points(int $ms, float $factor): int
    {
        $s = min(count(self::STEPS) - 1, intdiv($ms, self::STEP_SECONDS * 1000));
        [$from, $to] = self::POINTS[$s];
        $into = min(1.0, ($ms - $s * self::STEP_SECONDS * 1000) / (self::STEP_SECONDS * 1000));

        return (int)round(($from - ($from - $to) * $into) * $factor);
    }

    /** A round's score from its answered questions: points plus streak bonuses */
    public static function score(array $questions): array
    {
        $score = $streak = $correct = $lastBonus = 0;
        $ms = [];
        foreach ($questions as $question) {
            if ($question['answer'] === null) {
                break;
            }
            $lastBonus = 0;
            if ((int)$question['points'] > 0) {
                $correct++;
                $ms[] = (int)$question['ms'];
                $score += (int)$question['points'];
                if (++$streak % self::STREAK === 0) {
                    $score += self::POINTS_STREAK;
                    $lastBonus = self::POINTS_STREAK;
                }
            } else {
                $streak = 0;
            }
        }

        return compact('score', 'streak', 'correct', 'lastBonus', 'ms');
    }

    /**
     * Finishes a round once every question is answered: the score from the
     * stored answers, checked for what a human can't do.
     */
    public function finish(string $token): array
    {
        [$round, $questions] = $this->load($token);
        if (!$round) {
            return ['error' => 'This round is unknown or already over.'];
        }
        if ($this->current($questions) !== null) {
            return ['error' => 'This round is not over yet.'];
        }
        $r = self::score($questions);
        if (count($r['ms']) >= self::MIN_FAST_COUNT && array_sum($r['ms']) / count($r['ms']) < self::MIN_AVG_MS) {
            return ['error' => 'This round is not humanly possible.'];
        }
        $avg = $r['ms'] ? (int)round(array_sum($r['ms']) / count($r['ms'])) : null;
        $conn = ConnectionManager::get('default');
        $conn->execute('UPDATE radar_rounds SET finished_at = ?, score = ?, correct = ?, avg_ms = ? WHERE id = ? AND finished_at IS NULL',
            [gmdate('Y-m-d H:i:s'), $r['score'], $r['correct'], $avg, $round['id']]);

        $out = ['score' => $r['score'], 'correct' => $r['correct'], 'questions' => count($questions), 'avg_ms' => $avg,
                'player' => $round['player_id'] !== null];
        if ($round['player_id'] !== null) {
            $out['rank'] = $this->rank($round['player_id'], self::weekStart());
            $out['allTimeRank'] = $this->rank($round['player_id'], '1970-01-01');
            $out['best'] = (int)$conn->execute('SELECT MAX(score) FROM radar_rounds WHERE player_id = ? AND finished_at IS NOT NULL',
                [$round['player_id']])->fetchColumn(0);
            if ($out['best'] === $r['score']) {
                Cache::delete('hall_of_fame', 'rankings');
            }
        }

        return $out;
    }

    /** Monday 00:00 UTC of this week */
    public static function weekStart(): string
    {
        return gmdate('Y-m-d 00:00:00', strtotime('monday this week 00:00 UTC'));
    }

    /**
     * Top players since a date (until a date): each player's best round.
     * The earlier of two equal scores ranks first.
     *
     * @return list<array{player_id:string, name:string, country:?string, picture:?string, score:int, correct:int, avg_ms:?int, finished_at:string}>
     */
    public function board(?string $since, int $limit = 10, ?string $until = null): array
    {
        $rows = ConnectionManager::get('default')->execute("
            SELECT player_id, name, country, picture, score, correct, avg_ms, finished_at FROM (
                SELECT r.player_id, p.name, p.country, p.picture, r.score, r.correct, r.avg_ms, r.finished_at,
                       ROW_NUMBER() OVER (PARTITION BY r.player_id ORDER BY r.score DESC, r.finished_at ASC) AS rn
                FROM radar_rounds r INNER JOIN players p ON p.id = r.player_id
                WHERE r.finished_at IS NOT NULL AND r.finished_at >= ? AND r.finished_at <= ? AND p.track = 1 AND p.cheater = 0
            ) best WHERE rn = 1
            ORDER BY score DESC, finished_at ASC LIMIT " . (int)$limit,
            [$since ?? '1970-01-01', $until ?? '9999-12-31'])->fetchAll('assoc');

        return array_map(fn($r) => [
            'player_id' => $r['player_id'], 'name' => $r['name'], 'country' => $r['country'], 'picture' => $r['picture'],
            'score' => (int)$r['score'], 'correct' => (int)$r['correct'],
            'avg_ms' => $r['avg_ms'] !== null ? (int)$r['avg_ms'] : null,
            'finished_at' => (string)$r['finished_at'],
        ], $rows);
    }

    /** The best round finished between two days (inclusive, UTC) - the weekly achievement -, or null */
    public function bestBetween(string $firstDay, string $lastDay): ?array
    {
        $row = $this->board($firstDay . ' 00:00:00', 1, $lastDay . ' 23:59:59')[0] ?? null;

        return $row ? ['player_id' => $row['player_id'], 'name' => $row['name'], 'score' => $row['score']] : null;
    }

    /** The all-time top 10 as a Hall of Fame list (same row shape as AimTrainer::hallOfFame) */
    public function hallOfFame(int $limit = 10): array
    {
        return array_map(fn($r) => (object)[
            'player_id' => $r['player_id'], 'name' => $r['name'], 'country' => $r['country'], 'picture' => $r['picture'],
            'value' => $r['score'], 'game_id' => '', 'map_name' => '',
            'played_at' => new DateTimeImmutable($r['finished_at'] . ' UTC'),
            'correct' => $r['correct'], 'avg_ms' => $r['avg_ms'],
            'steals' => 0, 'returns' => 0,
        ], $this->board(null, $limit));
    }

    /** A player's all-time best round for the personal records on their page, or null */
    public function personalRecord(string $playerId): ?array
    {
        $conn = ConnectionManager::get('default');
        $best = $conn->execute('SELECT score, finished_at FROM radar_rounds WHERE player_id = ? AND finished_at IS NOT NULL
            ORDER BY score DESC, finished_at ASC LIMIT 1', [$playerId])->fetch('assoc');
        if (!$best) {
            return null;
        }
        $players = (int)$conn->execute('SELECT COUNT(DISTINCT r.player_id) FROM radar_rounds r INNER JOIN players p ON p.id = r.player_id
            WHERE r.finished_at IS NOT NULL AND p.track = 1 AND p.cheater = 0')->fetchColumn(0);

        return [
            'title' => 'Radar',
            'value' => (int)$best['score'],
            'rank' => $this->rank($playerId, '1970-01-01'),
            'players' => $players,
            'game_id' => null,
            'map_name' => 'radar',
            'played_at' => new DateTimeImmutable($best['finished_at'] . ' UTC'),
            'url' => '/radar',
        ];
    }

    /** A player's place by best score since a date (1 = best) */
    private function rank(string $playerId, string $since): int
    {
        $conn = ConnectionManager::get('default');
        $mine = (int)$conn->execute('SELECT MAX(score) FROM radar_rounds WHERE player_id = ? AND finished_at >= ?',
            [$playerId, $since])->fetchColumn(0);

        return 1 + (int)$conn->execute(
            'SELECT COUNT(*) FROM (SELECT r.player_id FROM radar_rounds r INNER JOIN players p ON p.id = r.player_id
              WHERE r.finished_at >= ? AND p.track = 1 AND p.cheater = 0 AND r.player_id <> ?
              GROUP BY r.player_id HAVING MAX(r.score) > ?) better', [$since, $playerId, $mine])->fetchColumn(0);
    }

    /** The open round of a token (finished ones too with $any) and its questions */
    private function load(string $token, bool $any = false): array
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            return [null, []];
        }
        $round = ConnectionManager::get('default')->execute(
            'SELECT id, player_id, started_at, finished_at, questions FROM radar_rounds WHERE token = ?', [$token])->fetch('assoc');
        if (!$round || (!$any && ($round['finished_at'] !== null || time() - strtotime($round['started_at'] . ' UTC') > self::TOKEN_TTL))) {
            return [null, []];
        }

        return [$round, (array)json_decode($round['questions'], true)];
    }

    private function save(int $id, array $questions): void
    {
        ConnectionManager::get('default')->execute('UPDATE radar_rounds SET questions = ? WHERE id = ? AND finished_at IS NULL',
            [json_encode($questions), $id]);
    }

    /** The first unanswered question, null when all are answered */
    private function current(array $questions): ?int
    {
        foreach ($questions as $i => $question) {
            if ($question['answer'] === null) {
                return $i;
            }
        }

        return null;
    }

    /** Index drawn with the given weights */
    private function weighted(array $weights): int
    {
        $r = random_int(0, PHP_INT_MAX - 1) / PHP_INT_MAX * array_sum($weights);
        foreach (array_values($weights) as $i => $w) {
            if (($r -= $w) < 0) {
                return $i;
            }
        }

        return count($weights) - 1;
    }

    /**
     * PNG of a square piece of the plan ($side px around $centre, the
     * background colour beyond the plan), PIECE_PX wide; the whole plan
     * fitted into PIECE_PX without a centre.
     */
    private function render(string $png, ?array $centre, int $side): string
    {
        $src = imagecreatefrompng($png);
        $w = imagesx($src);
        $h = imagesy($src);
        $out = imagecreatetruecolor(self::PIECE_PX, self::PIECE_PX);
        $bg = imagecolorallocate($out, 30, 26, 22);
        imagefill($out, 0, 0, $bg);
        if ($centre === null) {
            $k = self::PIECE_PX / max($w, $h);
            $dw = (int)round($w * $k);
            $dh = (int)round($h * $k);
            imagecopyresampled($out, $src, intdiv(self::PIECE_PX - $dw, 2), intdiv(self::PIECE_PX - $dh, 2), 0, 0, $dw, $dh, $w, $h);
        } else {
            // keep the piece inside the plan where it fits, so it shows as much map as it can
            $x = (int)$centre[0] - intdiv($side, 2);
            $y = (int)$centre[1] - intdiv($side, 2);
            $x = $side <= $w ? max(0, min($x, $w - $side)) : intdiv($w - $side, 2);
            $y = $side <= $h ? max(0, min($y, $h - $side)) : intdiv($h - $side, 2);
            $sx = max(0, $x);
            $sy = max(0, $y);
            $sw = min($w, $x + $side) - $sx;
            $sh = min($h, $y + $side) - $sy;
            $k = self::PIECE_PX / $side;
            imagecopyresampled($out, $src, (int)round(($sx - $x) * $k), (int)round(($sy - $y) * $k), $sx, $sy,
                (int)round($sw * $k), (int)round($sh * $k), $sw, $sh);
        }
        ob_start();
        imagepng($out, null, 6);

        return (string)ob_get_clean();
    }
}
