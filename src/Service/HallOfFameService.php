<?php
declare(strict_types=1);

namespace App\Service;

use App\Controller\PlayersController;
use Cake\Cache\Cache;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Hall of Fame records that were set in games marked inaccurate. They never
 * count, but are listed as "not qualified" so it is visible what was dropped.
 */
class HallOfFameService
{
    use LocatorAwareTrait;

    /** A record is listed when it would have made this top of its category */
    public const TOP = 10;

    /**
     * Top 10 per Hall of Fame list (best single game per player), keyed
     * headshot / slashed / gibbed / kills / flags / streak / helper. Cached
     * with the rankings, which ProcessLogsCommand clears after every import.
     */
    public function topLists(): array
    {
        return Cache::remember('hall_of_fame', fn() => [
            'kills' => $this->topPlayers('kills'),
            'headshot' => $this->topPlayers('headshot'),
            'flags' => $this->topPlayers('scored_with_the_flag'),
            'streak' => $this->topPlayers('longest_streak'),
            'slashed' => $this->topPlayers('slashed'),
            'gibbed' => $this->topPlayers('gibbed'),
            'helper' => $this->topPlayers(['stole_the_flag', 'returned_the_flag']),
        ], 'rankings');
    }

    private function topPlayers(string|array $fields, int $limit = 10): array
    {
        // Subquery instead of a literal list of ~5000 game ids (twice per
        // category) - that list made the Hall of Fame take ~25s to render.
        $gameIds = $this->fetchTable('Games')->find()
            ->select(['Games.id'])
            ->where(['Games.started_at >=' => PlayersController::HOF_SINCE, 'Games.inaccurate' => false]);

        if (is_array($fields)) {
            $fieldExpr = implode(' + ', array_map(fn($f) => "PlayerStatsPerGame.$f", $fields));
        } else {
            $fieldExpr = "PlayerStatsPerGame.$fields";
        }

        $sub = $this->fetchTable('PlayerStatsPerGame')->find()
            ->select([
                'player_id' => 'PlayerStatsPerGame.player_id',
                'max_value' => "MAX($fieldExpr)"
            ])
            ->where([
                'PlayerStatsPerGame.game_id IN' => $gameIds
            ])
            ->group(['PlayerStatsPerGame.player_id']);

        $query = $this->fetchTable('PlayerStatsPerGame')->find()
            ->select([
                'player_id' => 'Players.id',
                'name' => 'Players.name',
                'country' => 'Players.country',
                'picture' => 'Players.picture',
                'value' => $fieldExpr,
                'game_id' => 'Games.id',
                'played_at' => 'Games.started_at',
                'map_name' => 'Maps.name',
                // Weapon usage from the record-setting game (for the HoF cards)
                'kills' => 'PlayerStatsPerGame.kills',
                'headshot' => 'PlayerStatsPerGame.headshot',
                'shredded' => 'PlayerStatsPerGame.shredded',
                'peppered' => 'PlayerStatsPerGame.peppered',
                'sprayed' => 'PlayerStatsPerGame.sprayed',
                'punctured' => 'PlayerStatsPerGame.punctured',
                'splattered' => 'PlayerStatsPerGame.splattered',
                'slashed' => 'PlayerStatsPerGame.slashed',
                'gibbed' => 'PlayerStatsPerGame.gibbed',
                'picked_off' => 'PlayerStatsPerGame.picked_off',
                'busted' => 'PlayerStatsPerGame.busted',
            ])
            ->innerJoin(
                ['sub' => $sub],
                [
                    'sub.player_id = PlayerStatsPerGame.player_id',
                    "sub.max_value = $fieldExpr",
                    'sub.max_value > 0',
                ]
            )
            ->innerJoinWith('Players')
            ->innerJoinWith('Games.Maps')
            ->where([
                'PlayerStatsPerGame.game_id IN' => $gameIds,
                'Players.track' => 1,
            ])
            ->order([
                'value' => 'DESC',
                'Games.started_at' => 'DESC'
            ])
            ->group(['Players.id'])
            ->limit($limit);

        return $query->all()->toArray();
    }


    /**
     * Records from inaccurate games that would rank in a Hall of Fame top 10,
     * best rank first. Each: category, title, value, rank, player_id, name,
     * country, game_id, played_at, map_name, reason.
     *
     * @param string|null $gameId Only records from this game
     */
    public function notQualified(?string $gameId = null): array
    {
        $all = Cache::remember('hof_not_qualified', fn() => $this->compute(), 'rankings');
        if ($gameId === null) {
            return $all;
        }

        return array_values(array_filter($all, fn($r) => $r['game_id'] === $gameId));
    }

    private function compute(): array
    {
        $psg = $this->fetchTable('PlayerStatsPerGame');
        $since = PlayersController::HOF_SINCE;
        $categories = PlayersController::HOF_CATEGORIES;
        $expr = fn(array $cat) => implode(' + ', array_map(fn($f) => "PlayerStatsPerGame.$f", $cat['fields']));

        // Qualified per-player maxima (inaccurate games are filtered out by the table)
        $select = ['player_id' => 'PlayerStatsPerGame.player_id'];
        foreach ($categories as $key => $cat) {
            $select[$key] = 'MAX(' . $expr($cat) . ')';
        }
        $maxima = $psg->find()
            ->select($select)
            ->innerJoinWith('Players')
            ->innerJoinWith('Games')
            ->where(['Games.started_at >=' => $since, 'Players.track' => 1])
            ->groupBy(['PlayerStatsPerGame.player_id'])
            ->enableHydration(false)
            ->all()
            ->toArray();

        $select = [
            'player_id' => 'Players.id',
            'name' => 'Players.name',
            'country' => 'Players.country',
            'game_id' => 'Games.id',
            'played_at' => 'Games.started_at',
            'reason' => 'Games.inaccurate_reason',
            'map_name' => 'Maps.name',
        ];
        foreach ($categories as $key => $cat) {
            $select[$key] = $expr($cat);
        }
        $candidates = $psg->find()
            ->applyOptions(['includeInaccurate' => true])
            ->select($select)
            ->innerJoinWith('Players')
            ->innerJoinWith('Games.Maps')
            ->where(['Games.inaccurate' => true, 'Games.started_at >=' => $since, 'Players.track' => 1])
            ->enableHydration(false)
            ->all()
            ->toArray();

        $records = [];
        foreach ($candidates as $row) {
            foreach ($categories as $key => $cat) {
                $value = (int)$row[$key];
                if ($value <= 0) {
                    continue;
                }
                $rank = 1;
                foreach ($maxima as $m) {
                    if ($m['player_id'] !== $row['player_id'] && (int)$m[$key] > $value) {
                        $rank++;
                    }
                }
                if ($rank > self::TOP) {
                    continue;
                }
                $records[] = [
                    'category' => $key,
                    'title' => $cat['title'],
                    'value' => $value,
                    'rank' => $rank,
                    'played_at' => $row['played_at']?->format('Y-m-d H:i:s'),
                ] + array_diff_key($row, $categories);
            }
        }
        usort($records, fn($a, $b) => [$a['rank'], $b['value']] <=> [$b['rank'], $a['value']]);

        return $records;
    }
}
