<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\SiteCardImage;
use Cake\Cache\Cache;
use Cake\Http\Response;

/**
 * Link preview pictures (og:image) of the pages without their own:
 *   /previews/site     the site card - front page, Live, About, ... (layout default)
 *   /previews/ranking  the All Time Ranking's top 10
 * Cached on disk until something on them (or the drawing code) changes.
 */
class PreviewsController extends AppController
{
    public function site(): Response
    {
        $totals = Cache::remember('hero_stats', fn() => [
            'games' => $this->fetchTable('Games')->find()->where(['inaccurate' => false, 'ended_at IS NOT' => null])->count(),
            'players' => $this->fetchTable('Players')->find()->where(['track' => 1])
                ->innerJoinWith('PlayerStatsPerGame')->distinct(['Players.id'])->count(),
            'maps' => $this->fetchTable('Games')->find()->select(['map_id'])->distinct(['map_id'])
                ->where(['inaccurate' => false])->count(),
        ], 'rankings');

        $top = $this->fetchTable('PlayerRatings')->find()
            ->contain(['Players'])
            ->orderBy(['PlayerRatings.rank' => 'ASC'])
            ->limit(3)
            ->all()
            ->map(fn($r) => [(string)$r->player->name, (float)$r->rating, (string)$r->type])
            ->toList();

        $card = [
            'totals' => [
                'games' => number_format((int)$totals['games']),
                'players' => number_format((int)$totals['players']),
                'maps' => number_format((int)$totals['maps']),
            ],
            'top' => $top,
        ];

        return $this->picture('site', $card, fn() => (new SiteCardImage())->site($card));
    }

    public function ranking(): Response
    {
        // the All Time Ranking's players (this year, 5,000+ points), by CTF
        // rating as on the page (PlayersController::index), points as tie-break
        $rows = $this->fetchTable('Players')->find()
            ->select([
                'Players.name',
                'points' => 'SUM(PlayerStatsPerGame.total_score)',
                'rating' => 'MAX(r.rating)',
            ])
            ->innerJoinWith('PlayerStatsPerGame.Games', fn($q) => $q->where(['Games.started_at >=' => date('Y') . '-01-01']))
            ->leftJoin(['r' => 'player_ratings'], ['r.player_id = Players.id'])
            ->where(['Players.track' => 1])
            ->groupBy(['Players.id', 'Players.name'])
            ->having(['SUM(PlayerStatsPerGame.total_score) >=' => 5000])
            ->orderBy(['rating IS NULL' => 'ASC', 'rating' => 'DESC', 'points' => 'DESC'])
            ->limit(10)
            ->disableHydration()
            ->all()
            ->toList();

        $card = [
            'title' => 'All Time Ranking',
            'subtitle' => 'Top 10 by CTF rating  ·  ' . date('Y') . ', from 5,000 points',
            'rows' => array_map(fn($r, $i) => [
                $i + 1,
                (string)$r['name'],
                $r['rating'] !== null ? (float)$r['rating'] : null,
                number_format((int)$r['points']),
            ], $rows, array_keys($rows)),
        ];

        return $this->picture('ranking', $card, fn() => (new SiteCardImage())->ranking($card));
    }

    /**
     * Serve a cached picture, drawing it when its content or the drawing
     * code changed.
     */
    private function picture(string $name, array $card, callable $draw): Response
    {
        $dir = CACHE . 'cards' . DS;
        $file = $dir . $name . '-' . md5((string)json_encode($card) . filemtime(ROOT . '/src/Service/SiteCardImage.php')) . '.jpg';
        if (!is_file($file)) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            foreach (glob($dir . $name . '-*.jpg') ?: [] as $old) {
                @unlink($old);
            }
            file_put_contents($file, $draw());
        }

        return $this->response
            ->withType('jpg')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withFile($file);
    }
}
