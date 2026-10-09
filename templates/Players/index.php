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

<?= $this->element('ranking_board', compact('players', 'stats', 'sort', 'activeStat', 'achievementPlayers', 'achievementLabels') + [
    'title' => 'All Time Ranking',
    'meta' => [['fa-filter', 'min 5,000 pts this year']],
]) ?>
