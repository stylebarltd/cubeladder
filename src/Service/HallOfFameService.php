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
     * The categories, as on the page: key (topLists) => slug (the address
     * /players/hall_of_fame/<slug>), title, unit, emoji (Discord), icon
     * (HallOfFameImage: Font Awesome name or png:<weapon>). The game
     * categories are the best single game since PlayersController::HOF_SINCE;
     * 'aim' is the best round in the browser aim trainer (AimTrainer, /play),
     * its rows have no game and no map ('scope' says what counts instead).
     */
    public const CATEGORIES = [
        'kills' => ['slug' => 'kills', 'title' => 'Most Kills', 'unit' => 'kills', 'emoji' => '💀', 'icon' => 'skull'],
        'headshot' => ['slug' => 'headshots', 'title' => 'Most Headshots', 'unit' => 'headshots', 'emoji' => '🎯', 'icon' => 'crosshairs'],
        'flags' => ['slug' => 'flags', 'title' => 'Most Flags scored', 'unit' => 'flags', 'emoji' => '🚩', 'icon' => 'flag'],
        'streak' => ['slug' => 'streak', 'title' => 'Longest Streak', 'unit' => 'kills in a row', 'emoji' => '🔥', 'icon' => 'fire'],
        'slashed' => ['slug' => 'slashes', 'title' => 'Most Slashes', 'unit' => 'slashes', 'emoji' => '🔪', 'icon' => 'png:knife'],
        'gibbed' => ['slug' => 'gibs', 'title' => 'Most Gibbed', 'unit' => 'gibs', 'emoji' => '💣', 'icon' => 'png:grenade'],
        'helper' => ['slug' => 'flag-helper', 'title' => 'Flag Helper', 'unit' => 'steals + returns', 'emoji' => '🤝', 'icon' => 'handshake'],
        'aim' => ['slug' => 'aim-trainer', 'title' => 'Aim Trainer', 'unit' => 'points', 'emoji' => '🕹️', 'icon' => 'bullseye',
            'scope' => 'Best ' . AimTrainer::ROUND_SECONDS . '-second round in the aim trainer', 'link' => '/play'],
    ];

    /** The page of a category's records: the game, or the aim trainer */
    public static function where(string $key): string
    {
        return self::CATEGORIES[$key]['link'] ?? '';
    }

    /** Category key of an address slug, or null */
    public static function keyOf(string $slug): ?string
    {
        foreach (self::CATEGORIES as $key => $c) {
            if ($c['slug'] === $slug) {
                return $key;
            }
        }

        return null;
    }

    /**
     * What HallOfFameImage draws for one category: its top 10, a headline
     * (default: the #1 and their record) and the players to highlight.
     *
     * @param list<array{pos:int, name:string, value:int, map?:string}> $list
     * @param array<string, string> $marks player_id => NEW / RECORD / ▲2 / ...
     */
    public static function card(string $key, array $list, ?string $headline = null, array $marks = [], ?string $background = null): array
    {
        $c = self::CATEGORIES[$key];
        $first = $list[0] ?? null;

        return [
            'title' => $c['title'],
            'subtitle' => ($c['scope'] ?? 'Best single game since ' . date('F Y', strtotime(PlayersController::HOF_SINCE))) . ' · ' . $c['unit'],
            'icon' => $c['icon'],
            'headline' => $headline ?? ($first
                ? sprintf('#1 %s · %s %s%s', $first['name'], number_format($first['value']), $c['unit'],
                    !empty($first['map']) ? ' on ' . $first['map'] : (isset($c['link']) ? ' · cubeladder.ovh' . $c['link'] : ''))
                : ''),
            'background' => $background,
            'footer' => 'cubeladder.ovh/players/hall_of_fame/' . $c['slug'],
            'rows' => array_map(fn($e) => [$e['pos'], $e['name'], number_format($e['value']), $marks[$e['player_id'] ?? ''] ?? ''], $list),
        ];
    }

    /** One topLists() list as plain rows for card() */
    public static function rows(array $list): array
    {
        return array_map(fn($row, $i) => [
            'pos' => $i + 1,
            'player_id' => (string)$row->player_id,
            'name' => (string)$row->name,
            'value' => (int)$row->value,
            'map' => (string)$row->map_name,
            'picture' => (string)$row->picture,
        ], array_values($list), array_keys(array_values($list)));
    }

    /**
     * Top 10 per Hall of Fame list (best single game per player), keyed
     * headshot / slashed / gibbed / kills / flags / streak / helper, and
     * 'aim' (best aim trainer round per player). Cached with the rankings,
     * which ProcessLogsCommand clears after every import and AimTrainer
     * after a new personal best.
     */
    public function topLists(): array
    {
        return Cache::remember('hall_of_fame', fn() => $this->freshTopLists(), 'rankings');
    }

    /**
     * The same lists, not cached (DiscordHofCommand compares them after
     * every import).
     */
    public function freshTopLists(): array
    {
        return [
            'kills' => $this->topPlayers('kills'),
            'headshot' => $this->topPlayers('headshot'),
            'flags' => $this->topPlayers('scored_with_the_flag', modes: PlayersController::HOF_CATEGORIES['scored_with_the_flag']['modes']),
            'streak' => $this->topPlayers('longest_streak'),
            'slashed' => $this->topPlayers('slashed'),
            'gibbed' => $this->topPlayers('gibbed'),
            'helper' => $this->topPlayers(['stole_the_flag', 'returned_the_flag']),
            'aim' => (new AimTrainer())->hallOfFame(self::TOP),
        ];
    }

    /** @param list<string> $modes only games of these modes (all when empty) */
    private function topPlayers(string|array $fields, int $limit = 10, array $modes = []): array
    {
        // Subquery instead of a literal list of ~5000 game ids (twice per
        // category) - that list made the Hall of Fame take ~25s to render.
        $gameIds = $this->fetchTable('Games')->find()
            ->select(['Games.id'])
            ->where(['Games.started_at >=' => PlayersController::HOF_SINCE, 'Games.inaccurate' => false]);
        if ($modes) {
            $gameIds->where(['Games.mode IN' => $modes]);
        }

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
            ->groupBy(['PlayerStatsPerGame.player_id']);

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
                // Flag Helper's two parts (Discord: "12 steals + 9 returns")
                'steals' => 'PlayerStatsPerGame.stole_the_flag',
                'returns' => 'PlayerStatsPerGame.returned_the_flag',
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
                'Players.cheater' => false,
            ])
            ->orderBy([
                'value' => 'DESC',
                'Games.started_at' => 'DESC'
            ])
            ->groupBy(['Players.id'])
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
        $expr = fn(array $cat) => PlayersController::hofExpr($cat);

        // Qualified per-player maxima (inaccurate games are filtered out by the table)
        $select = ['player_id' => 'PlayerStatsPerGame.player_id'];
        foreach ($categories as $key => $cat) {
            $select[$key] = 'MAX(' . $expr($cat) . ')';
        }
        $maxima = $psg->find()
            ->select($select)
            ->innerJoinWith('Players')
            ->innerJoinWith('Games')
            ->where(['Games.started_at >=' => $since, 'Players.track' => 1, 'Players.cheater' => false])
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
            ->where(['Games.inaccurate' => true, 'Games.started_at >=' => $since, 'Players.track' => 1, 'Players.cheater' => false])
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
