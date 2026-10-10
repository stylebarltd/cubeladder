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

$regionUrl = fn(array $q) => $this->Url->build(['?' => array_filter(['sort' => $sort !== 'rating' ? $sort : null] + $q)]);
$regionLabel = $country ? Continents::countryName($country) . ' ' . $this->Layout->flag($country)
    : ($continent ? Continents::NAMES[$continent] : null);
$this->start('rankingFilter'); ?>
    <label class="flex items-center gap-2 rounded-lg border border-white/15 bg-black/60 px-3 py-2 text-sm backdrop-blur-[2px]">
        <i class="fa-solid fa-earth-europe text-zinc-400"></i>
        <select onchange="location.href = this.value" aria-label="Continent or country"
                class="flex-1 bg-transparent font-semibold text-white focus:outline-none [&>optgroup]:bg-zinc-900 [&_option]:bg-zinc-900">
            <option value="<?= h($regionUrl([])) ?>">Everywhere</option>
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
    ])),
    'keepQuery' => ['continent' => $continent ? strtolower(str_replace(' ', '-', Continents::NAMES[$continent])) : null, 'country' => $country ? strtolower($country) : null],
]) ?>
