<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

/**
 * Aim trainer (/play): 40-second rounds of targets on a map screenshot,
 * with 5 grenades.
 *
 * A round starts with a one-time token and a seed (the targets come from
 * the seed, the same in every browser); at the end the page sends the
 * round as events - h hit, s headshot, m missed shot, e target escaped,
 * n grenade exploded followed by a g per target it gibbed - plus the
 * reaction time of every shot that hit. Rounds of an earlier length (the
 * first were 60 s, then 30 s) are kept but not compared: the boards count rounds of
 * ROUND_SECONDS only. The score is computed here from the
 * events (the page's own number is never trusted) and the round is checked
 * for what a human can't do. It stays a game in the browser: the board is
 * "just for fun", apart from the ladder's numbers, and only recognised
 * players (identity) are on it.
 */
class AimTrainer
{
    public const ROUND_SECONDS = 40;
    /** Points: body hit, headshot, bonus for every 5 hits in a row */
    public const POINTS_HIT = 10;
    public const POINTS_HEADSHOT = 20;
    public const POINTS_STREAK = 10;
    public const STREAK = 5;
    /** Grenades per round; a gib, and the bonus for every further gib of the same grenade */
    public const GRENADES = 5;
    public const POINTS_GIB = 15;
    public const POINTS_MULTI_GIB = 10;
    /** Targets on screen at once (so a grenade gibs at most this many) */
    public const MAX_ALIVE = 4;
    /** More targets than the spawn schedule can make in a round */
    public const MAX_TARGETS = 90;
    /** Reaction time of one hit (ms): faster than a human / slower than a target lives.
     *  A few lucky ones under MIN_REACTION happen (firing where a target pops up):
     *  up to LUCKY_HITS + 10 % of the hits */
    public const MIN_REACTION = 100;
    public const LUCKY_HITS = 3;
    public const MAX_REACTION = 2500;
    /** Average reaction faster than this is not a human */
    public const MIN_AVG_REACTION = 180;
    /** A started round can be finished this long */
    private const TOKEN_TTL = 900;

    /** New round: token + seed */
    public function start(?string $playerId): array
    {
        $token = bin2hex(random_bytes(16));
        $seed = random_int(1, 0x7fffffff);
        $conn = ConnectionManager::get('default');
        $conn->execute('INSERT INTO aim_rounds (token, seed, seconds, player_id, started_at) VALUES (?, ?, ?, ?, ?)',
            [$token, $seed, self::ROUND_SECONDS, $playerId, gmdate('Y-m-d H:i:s')]);
        // rounds never finished are of no use after a day
        $conn->execute('DELETE FROM aim_rounds WHERE finished_at IS NULL AND started_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);

        return ['token' => $token, 'seed' => $seed, 'seconds' => self::ROUND_SECONDS];
    }

    /**
     * Score of a round from its events (the same rules as the page).
     *
     * hits = shots that hit (h, s); gibs count for the streak too.
     *
     * @return array{score:int, shots:int, hits:int, headshots:int, gibs:int, grenades:int, escaped:int}
     */
    public static function score(string $events): array
    {
        $score = $streak = $hits = $headshots = $shots = $escaped = $gibs = $grenades = $nadeGibs = 0;
        foreach ($events === '' ? [] : str_split($events) as $e) {
            if ($e === 'n') {
                $grenades++;
                $nadeGibs = 0;
                continue;
            }
            if ($e === 'g') {
                $gibs++;
                $score += self::POINTS_GIB + ($nadeGibs++ > 0 ? self::POINTS_MULTI_GIB : 0);
                if (++$streak % self::STREAK === 0) {
                    $score += self::POINTS_STREAK;
                }
                continue;
            }
            if ($e === 'h' || $e === 's') {
                $shots++;
                $hits++;
                $headshots += $e === 's' ? 1 : 0;
                $score += $e === 's' ? self::POINTS_HEADSHOT : self::POINTS_HIT;
                if (++$streak % self::STREAK === 0) {
                    $score += self::POINTS_STREAK;
                }
            } elseif ($e === 'm') {
                $shots++;
                $streak = 0;
            } elseif ($e === 'e') {
                $escaped++;
                $streak = 0;
            }
        }

        return compact('score', 'shots', 'hits', 'headshots', 'gibs', 'grenades', 'escaped');
    }

    /**
     * Finishes a round. Returns the result, or an error text when the round
     * is unknown, already finished, too short or not humanly possible.
     *
     * @param list<int> $reactions ms per hit, in order
     * @return array{error?: string, score?: int, ...}
     */
    public function finish(string $token, string $events, array $reactions): array
    {
        $conn = ConnectionManager::get('default');
        $round = $conn->execute('SELECT id, player_id, seconds, started_at, finished_at FROM aim_rounds WHERE token = ?', [$token])->fetch('assoc');
        if (!$round || $round['finished_at'] !== null) {
            return ['error' => 'This round is unknown or already over.'];
        }
        $elapsed = time() - strtotime($round['started_at'] . ' UTC');
        if ($elapsed < (int)$round['seconds'] - 2 || $elapsed > self::TOKEN_TTL) {
            return ['error' => 'This round took too short or too long.'];
        }
        // a grenade's gibs come right after it, at most MAX_ALIVE
        if (strlen($events) > 1000 || !preg_match('/^(?:[hsme]|ng{0,' . self::MAX_ALIVE . '})*$/', $events)) {
            return ['error' => 'This round makes no sense.'];
        }
        $r = self::score($events);
        $reactions = array_values(array_map('intval', $reactions));
        if ($r['grenades'] > self::GRENADES || $r['hits'] + $r['gibs'] + $r['escaped'] > self::MAX_TARGETS
            || count($reactions) !== $r['hits']
            || ($reactions && (count(array_filter($reactions, fn($ms) => $ms < self::MIN_REACTION)) > self::LUCKY_HITS + 0.1 * count($reactions)
                || min($reactions) < 0 || max($reactions) > self::MAX_REACTION
                || array_sum($reactions) / count($reactions) < self::MIN_AVG_REACTION))) {
            return ['error' => 'This round is not humanly possible.'];
        }
        $avg = $reactions ? (int)round(array_sum($reactions) / count($reactions)) : null;
        $conn->execute(
            'UPDATE aim_rounds SET finished_at = ?, score = ?, shots = ?, hits = ?, headshots = ?, gibs = ?, escapes = ?, avg_reaction_ms = ?
             WHERE id = ? AND finished_at IS NULL',
            [gmdate('Y-m-d H:i:s'), $r['score'], $r['shots'], $r['hits'], $r['headshots'], $r['gibs'], $r['escaped'], $avg, $round['id']]);

        $out = $r + ['avg_reaction_ms' => $avg, 'player' => $round['player_id'] !== null];
        if ($round['player_id'] !== null) {
            $out['rank'] = $this->rank($round['player_id'], self::weekStart());
            $out['best'] = (int)$conn->execute(
                'SELECT MAX(score) FROM aim_rounds WHERE player_id = ? AND seconds = ? AND finished_at IS NOT NULL',
                [$round['player_id'], self::ROUND_SECONDS])->fetchColumn(0);
        }

        return $out;
    }

    /** Monday 00:00 UTC of this week */
    public static function weekStart(): string
    {
        return gmdate('Y-m-d 00:00:00', strtotime('monday this week 00:00 UTC'));
    }

    /**
     * Top players since a date: each player's best round with its accuracy.
     *
     * @return list<array{player_id:string, name:string, country:?string, score:int, accuracy:int, headshots:int, gibs:int, avg_reaction_ms:?int}>
     */
    public function board(?string $since, int $limit = 10): array
    {
        $rows = ConnectionManager::get('default')->execute("
            SELECT player_id, name, country, score, shots, hits, headshots, gibs, avg_reaction_ms FROM (
                SELECT r.player_id, p.name, p.country, r.score, r.shots, r.hits, r.headshots, r.gibs, r.avg_reaction_ms,
                       ROW_NUMBER() OVER (PARTITION BY r.player_id ORDER BY r.score DESC, r.finished_at ASC) AS rn,
                       r.finished_at
                FROM aim_rounds r INNER JOIN players p ON p.id = r.player_id
                WHERE r.finished_at IS NOT NULL AND r.finished_at >= ? AND r.seconds = ? AND p.track = 1 AND p.cheater = 0
            ) best WHERE rn = 1
            ORDER BY score DESC, finished_at ASC LIMIT " . (int)$limit, [$since ?? '1970-01-01', self::ROUND_SECONDS])->fetchAll('assoc');

        return array_map(fn($r) => [
            'player_id' => $r['player_id'], 'name' => $r['name'], 'country' => $r['country'],
            'score' => (int)$r['score'], 'headshots' => (int)$r['headshots'], 'gibs' => (int)$r['gibs'],
            'accuracy' => (int)$r['shots'] ? (int)round(100 * $r['hits'] / $r['shots']) : 0,
            'avg_reaction_ms' => $r['avg_reaction_ms'] !== null ? (int)$r['avg_reaction_ms'] : null,
        ], $rows);
    }

    /**
     * The link preview of /play (PreviewsController::play, drawn by
     * HallOfFameImage): the all-time top 10 on a map screenshot that changes
     * every week, this week's leader as the challenge;
     * a short board shows open places up to five.
     */
    public function card(): array
    {
        $week = $this->board(self::weekStart(), 1);
        $allTime = $this->board(null);
        $maps = glob(WWW_ROOT . 'img/maps/*.jpg') ?: [];
        sort($maps);
        $rows = array_map(fn($r, $i) => [$i + 1, $r['name'], number_format($r['score']), ''], $allTime, array_keys($allTime));
        // a short board shows open places, up to five
        for ($i = count($rows); $i < 5; $i++) {
            $rows[] = [$i + 1, 'your name here?', '-', ''];
        }

        return [
            'tag' => 'PLAY IN YOUR BROWSER',
            'tagIcon' => 'play',
            'icon' => 'crosshairs',
            'title' => 'Aim trainer',
            'subtitle' => sprintf('%d seconds on AssaultCube maps  ·  head %d  ·  body %d  ·  %d grenades',
                self::ROUND_SECONDS, self::POINTS_HEADSHOT, self::POINTS_HIT, self::GRENADES),
            'headline' => $week
                ? sprintf('This week: %s leads with %s points  ·  can you beat it?', $week[0]['name'], number_format($week[0]['score']))
                : 'Nobody on this week\'s board yet  ·  your round could top it',
            'rows' => $rows,
            'background' => $maps ? $maps[crc32(self::weekStart()) % count($maps)] : null,
            'footer' => 'cubeladder.ovh/play',
        ];
    }

    /** A player's place by best score since a date (1 = best) */
    private function rank(string $playerId, string $since): int
    {
        $conn = ConnectionManager::get('default');
        $mine = (int)$conn->execute('SELECT MAX(score) FROM aim_rounds WHERE player_id = ? AND seconds = ? AND finished_at >= ?',
            [$playerId, self::ROUND_SECONDS, $since])->fetchColumn(0);

        return 1 + (int)$conn->execute(
            'SELECT COUNT(*) FROM (SELECT r.player_id FROM aim_rounds r INNER JOIN players p ON p.id = r.player_id
              WHERE r.finished_at >= ? AND r.seconds = ? AND p.track = 1 AND p.cheater = 0 AND r.player_id <> ?
              GROUP BY r.player_id HAVING MAX(r.score) > ?) better', [$since, self::ROUND_SECONDS, $playerId, $mine])->fetchColumn(0);
    }
}
