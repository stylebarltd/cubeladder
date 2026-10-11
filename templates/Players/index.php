<?php
/**
 * STAT CONFIG
 * key = sort value
 */

use Cake\Core\Configure;

$stats = [
    'points' => [
        'label' => 'Points',
        'value' => fn($p) => number_format((float)$p->total_score),
    ],
    'rating' => [
        'label' => 'Rating',
        // HTML: the rating plus its trend arrow
        'html' => true,
        'value' => fn($p) => $p->rating !== null
            ? $this->Layout->trendArrow($p->rating_trend ?? null, 'mr-1 text-[10px]') . number_format($p->rating, 1)
            : '–',
        'title' => 'CTF rating 0-10 (last 100 CTF games, from 20 on) - see About',
    ],
    'skill' => [
        'label' => 'Skill',
        // HTML: the skill rating plus its trend arrow
        'html' => true,
        'value' => fn($p) => $p->skill !== null
            ? $this->Layout->trendArrow($p->skill_trend ?? null, 'mr-1 text-[10px]', 'games') . number_format($p->skill, 1)
            : '–',
        'title' => 'Skill rating 0-10: individual, last 100 games of any mode - kills, headshot / knife / grenade kills, K/D, flags, streaks, team wins, lobby strength - see About',
    ],
    'kd' => [
        'label' => 'KDR',
        'value' => fn($p) => $p->stats['kd_ratio'] ?? 0,
    ],
    'kills' => [
        'label' => 'Kills',
        'value' => fn($p) => $p->stats['kills'] ?? 0,
    ],
    'headshot' => [
        'label' => 'HS',
        'value' => fn($p) => $p->stats['headshot'] ?? 0,
    ],
    'gibbed' => [
        'label' => 'Gib',
        'value' => fn($p) => $p->stats['gibbed'] ?? 0,
    ],
    'slashed' => [
        'label' => 'Slash',
        'value' => fn($p) => $p->stats['slashed'] ?? 0,
    ],
    'scored_with_the_flag' => [
        'label' => 'Flags',
        'value' => fn($p) => $p->stats['scored_with_the_flag'] ?? 0,
    ],
    'teamkills' => [
        'label' => 'TK',
        'value' => fn($p) => $p->stats['teamkills'] ?? 0,
    ],
];

// Default: rates instead of the year's totals (PlayersController::rateRanking) -
// the rate big, the total small under it; ?view=totals switches back
if (!$totals) {
    $withTotal = fn(string $rate, $total) => $rate . '<span class="block text-[10px] font-normal text-zinc-500">' . h($total) . '</span>';
    $rated = [
        'points' => ['Pts/game', 0, 'Points per game (total under it)', fn($p) => number_format((float)$p->total_score)],
        'kills' => ['Kills/10m', 1, 'Kills per 10 minutes played (total under it)', fn($p) => number_format($p->stats['kills'])],
        'headshot' => ['HS %', 1, 'Headshot kills, % of all kills (total under it)', fn($p) => number_format($p->stats['headshot'])],
        'gibbed' => ['Gib %', 1, 'Grenade kills, % of all kills (total under it)', fn($p) => number_format($p->stats['gibbed'])],
        'slashed' => ['Slash %', 1, 'Knife kills, % of all kills (total under it)', fn($p) => number_format($p->stats['slashed'])],
        'scored_with_the_flag' => ['Flags/h', 1, 'Flags scored per hour played (total under it)', fn($p) => number_format($p->stats['scored_with_the_flag'])],
        'teamkills' => ['TK/100', 1, 'Teamkills per 100 kills - fewest first (total under it)', fn($p) => number_format($p->stats['teamkills'])],
    ];
    foreach ($rated as $key => [$label, $decimals, $title, $total]) {
        $stats[$key] = [
            'label' => $label,
            'title' => $title,
            'html' => true,
            'value' => fn($p) => $withTotal(number_format($p->rates[$key] ?? 0, $decimals), $total($p)),
        ];
    }
}

$activeStat = $stats[$sort] ?? $stats['points'];

/**
 * Achievement labels
 */
$achievementLabels = [
    'total_score' => 'Most Points last week',
    'games' => 'Most Games Played last week',
    'teamkills' => 'Most Teamkills last week',
    'kd_ratio' => 'Best KD Ratio last week',
    'gibbed' => 'Most Gibs last week',
    'slashed' => 'Most Slashes last week',
    'scored_with_the_flag' => 'Most Flags scored last week',
    'headshot' => 'Most Headshots last week',
];
?>

<?php
// Link previews: the top 10 card (PreviewsController::ranking), a new picture URL every hour
$ogSite = rtrim((string)Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh', '/');
$this->assign('title', 'All Time Ranking · cubeLadder');
$this->start('meta'); ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="cubeLadder">
    <meta property="og:title" content="All Time Ranking · cubeLadder">
    <meta property="og:description" content="The AssaultCube players of <?= date('Y') ?> by CTF rating and points - every number straight from the game servers' logs.">
    <meta property="og:url" content="<?= h($ogSite . '/players') ?>">
    <meta property="og:image" content="<?= h($ogSite . '/previews/ranking?v=' . date('YmdH')) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
<?php $this->end(); ?>

<?php
// Region filter: a continent or a country (PlayersController::index)
use App\Utility\Continents;

$regionUrl = fn(array $q) => $this->Url->build(['?' => array_filter(['sort' => $sort !== 'rating' ? $sort : null, 'view' => $totals ? 'totals' : null] + $q)]);
// the switch between rates and totals, keeping the sort and the region
$viewUrl = fn(bool $t) => $this->Url->build(['?' => array_filter(['sort' => $sort !== 'rating' ? $sort : null, 'view' => $t ? 'totals' : null,
    'continent' => $this->request->getQuery('continent'), 'country' => $this->request->getQuery('country')])]);
$viewBtn = fn(bool $on) => 'rounded-md px-3 py-1 font-semibold transition ' . ($on ? 'bg-blue-600 text-white' : 'text-zinc-400 hover:text-white');
$regionLabel = $country ? Continents::countryName($country) . ' ' . $this->Layout->flag($country)
    : ($continent ? Continents::NAMES[$continent] : null);
$this->start('rankingFilter'); ?>
    <div class="flex items-center gap-1 rounded-lg border border-white/15 bg-black/60 p-1 text-sm backdrop-blur-[2px]" role="group" aria-label="Rates or totals">
        <a href="<?= h($viewUrl(false)) ?>" class="<?= $viewBtn(!$totals) ?>" title="Kills per 10 min, headshot / gib / slash kills in % of the kills, flags per hour, teamkills per 100 kills, points per game - sorting by them needs <?= \App\Controller\PlayersController::RATE_MIN_MINUTES / 60 ?> hours played">Rates</a>
        <a href="<?= h($viewUrl(true)) ?>" class="<?= $viewBtn($totals) ?>" title="The totals of the year">Totals</a>
    </div>
    <label class="flex items-center gap-2 rounded-lg border border-white/15 bg-black/60 px-3 py-2 text-sm backdrop-blur-[2px]">
        <i class="fa-solid fa-earth-europe text-zinc-400"></i>
        <select onchange="location.href = this.value" aria-label="Continent or country"
                class="flex-1 bg-transparent font-semibold text-white focus:outline-none [&>optgroup]:bg-zinc-900 [&_option]:bg-zinc-900">
            <option value="<?= h($regionUrl([])) ?>">Global</option>
            <optgroup label="Continents">
                <?php foreach ($regionCounts['continents'] as $code => $n):
                    $slug = strtolower(str_replace(' ', '-', Continents::NAMES[$code])); ?>
                    <option value="<?= h($regionUrl(['continent' => $slug])) ?>" <?= $continent === $code ? 'selected' : '' ?>><?= h(Continents::NAMES[$code]) ?> (<?= $n ?>)</option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="Countries">
                <?php
                $countryNames = [];
                foreach ($regionCounts['countries'] as $code => $n) {
                    $countryNames[$code] = Continents::countryName($code);
                }
                asort($countryNames);
                foreach ($countryNames as $code => $name): ?>
                    <option value="<?= h($regionUrl(['country' => strtolower($code)])) ?>" <?= $country === $code ? 'selected' : '' ?>><?= $this->Layout->flag($code) ?> <?= h($name) ?> (<?= $regionCounts['countries'][$code] ?>)</option>
                <?php endforeach; ?>
            </optgroup>
        </select>
    </label>
<?php $this->end(); ?>

<?= $this->element('ranking_board', compact('players', 'stats', 'sort', 'activeStat', 'achievementPlayers', 'achievementLabels') + [
    'title' => 'All Time Ranking',
    'meta' => array_values(array_filter([
        $regionLabel ? ['fa-earth-europe', $regionLabel] : null,
        ['fa-filter', 'min ' . number_format($minPoints) . ' pts this year'],
        ['fa-bolt', 'played in the last ' . \App\Utility\Activity::ACTIVE_DAYS . ' days'],
        !$totals && in_array($sort, ['points', 'kills', 'headshot', 'gibbed', 'slashed', 'scored_with_the_flag', 'teamkills'], true)
            ? ['fa-clock', 'min ' . (\App\Controller\PlayersController::RATE_MIN_MINUTES / 60) . ' h played'] : null,
    ])),
    'keepQuery' => ['view' => $totals ? 'totals' : null, 'continent' => $continent ? strtolower(str_replace(' ', '-', Continents::NAMES[$continent])) : null, 'country' => $country ? strtolower($country) : null],
]) ?>
