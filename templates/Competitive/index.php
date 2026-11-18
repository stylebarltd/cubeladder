<?php
/**
 * The competitive ladder: Elo of the clan match servers' games
 * (CompetitiveTracker / CompetitiveRatings) and the latest games. Top 3 as a podium, the rest in the same
 * scoreboard style as the All Time Ranking (ranking_board).
 *
 * @var \App\View\AppView $this
 * @var array $ratings comp_ratings rows (+ profile_name, country, picture, kd, acc, gained, lost, expected, score, opp): ranked
 * @var array $provisional active players with fewer than $minGames games
 * @var int $inactive ranked players without a game in $activeDays days
 * @var int $activeDays
 * @var array $games comp_games rows, latest first
 * @var array $players comp_player_stats by game id
 * @var array $servers
 * @var int $minGames
 * @var bool $public
 * @var array|null $example a rated player of the latest counted game, for "How the Elo works"
 * @var array $k K factors: new (first newGames games) / normal
 * @var float $minShare part of a game a player needs to be rated
 * @var array $profiles ratings / achievements / styles by player id (CompetitiveController::profileBadges)
 * @var string|null $viewer the logged-in player's id
 */
use App\Service\QrHunt;

$this->assign('title', 'Inter Games · cubeLadder');
$start = (int)\App\Controller\CompetitiveController::start();
$serverName = fn(string $key) => $this->Layout->serverName($key);
$name = fn(array $r) => (string)($r['profile_name'] ?? $r['name']);
$pic = fn(array $r) => $this->Layout->playerPicture((object)['picture' => $r['picture'] ?? null]);
$num = fn($v, int $d = 1) => rtrim(rtrim(number_format((float)$v, $d), '0'), '.');
$signed = fn(float $v, int $d = 1) => ($v >= 0 ? '+' : '&minus;') . number_format(abs($v), $d);

// a player's name as on the All Time Ranking: hover card, QR hunt name frame, gold badge, flag
$nameLink = function (array $r, string $class = '') use ($name, $profiles): string {
    $style = $profiles['styles'][(string)$r['player_id']] ?? null;
    if (!$r['player_id']) {
        return '<span class="truncate font-semibold ' . $class . '">' . h($name($r)) . '</span>';
    }
    $html = $this->Html->link(h($name($r)), ['controller' => 'Players', 'action' => 'view', $r['player_id']], [
        'escape' => false,
        'class' => 'truncate font-semibold hover:text-blue-300 ' . $class . (!empty($style['frame']) ? ' rounded-md border-2 px-1.5' : ''),
        'style' => QrHunt::nameFrameCss($style['frame'] ?? null) ?: null,
        'data-hover-card' => '/players/hover-card/' . $r['player_id'],
    ]);
    if (!empty($style['gold'])) {
        $html .= $this->element('qr_gold_badge');
    }

    return $html . ($r['country'] ? '<span class="shrink-0">' . $this->Layout->flag($r['country']) . '</span>' : '');
};
// archetype, last week's achievements and player type, as on the All Time Ranking
$badges = function (array $r) use ($profiles): string {
    $id = (string)$r['player_id'];
    $pr = $profiles['ratings'][$id] ?? null;
    $html = '';
    if (!empty($pr['archetype'])) {
        $html .= $this->element('archetype_badge', ['archetype' => $pr['archetype'], 'pct' => $pr['archetype_pct'], 'rank' => $pr['archetype_rank'], 'compact' => true]);
    }
    foreach ($profiles['achievements'][$id] ?? [] as $ach => $count) {
        $html .= $this->element('achievement_badge', ['event' => $ach]);
    }
    if (!empty($pr['type'])) {
        $html .= $this->element('player_type_badge', ['type' => $pr['type'], 'label' => $pr['type_label']]);
    }

    return $html;
};
// days since the last game; the longer, the greyer the name: 20 / 40 / 60 / 80 days (all >= 4.5:1 on the dark ground)
$idle = fn(array $r) => max(0, (int)floor((time() - strtotime((string)$r['last_game_at'])) / 86400));
$shade = fn(int $d) => $d >= 80 ? 'text-[#8a8a93]' : ($d >= 60 ? 'text-zinc-400' : ($d >= 40 ? 'text-zinc-300' : ($d >= 20 ? 'text-zinc-200' : 'text-white')));
$ago = fn(int $d) => $d <= 0 ? 'today' : ($d === 1 ? '1 day ago' : $d . ' days ago');
$indClass = fn($ind) => $ind === null ? 'text-zinc-500' : ((float)$ind >= 6 ? 'text-green-400' : ((float)$ind >= 4 ? 'text-zinc-200' : 'text-red-400'));
// score against expected, rounded first so the sign and colour match the shown number (no "−0.0")
$delta = fn(array $r) => $r['expected'] !== null ? round($r['score'] - $r['expected'], 1) + 0.0 : null;
// win / draw / loss bar (decorative, the numbers are always next to it)
$wdlBar = function (array $r, string $height = 'h-1.5'): string {
    $g = max(1, (int)$r['games']);

    return '<span aria-hidden="true" class="flex ' . $height . ' gap-0.5 overflow-hidden rounded-full">'
        . '<span class="bg-green-500" style="width:' . round((int)$r['wins'] / $g * 100, 2) . '%"></span>'
        . '<span class="bg-zinc-500" style="width:' . round((int)$r['draws'] / $g * 100, 2) . '%"></span>'
        . '<span class="flex-1 bg-red-700"></span></span>';
};

$podium = array_slice($ratings, 0, 3);
$chase = array_slice($ratings, 3);
$viewerRank = null;
foreach ($ratings as $i => $r) {
    if ($viewer && $r['player_id'] === $viewer) {
        $viewerRank = $i + 1;
    }
}
// stat leaders over the ranked players
$leader = function (callable $value) use ($ratings): ?array {
    $best = null;
    foreach ($ratings as $r) {
        $v = $value($r);
        if ($v !== null && ($best === null || $v > $best[1])) {
            $best = [$r, $v];
        }
    }

    return $best;
};
$leaders = array_filter([
    ['Beats the odds', $leader($delta), fn($v) => $signed($v), 'vs expected', 'text-green-400'],
    ['Deadliest', $leader(fn($r) => $r['kd']), fn($v) => number_format($v, 2), 'K/D', ''],
    ['Sharpest aim', $leader(fn($r) => $r['acc']), fn($v) => round($v) . '%', 'accuracy', ''],
    ['Iron man', $leader(fn($r) => (int)$r['games']), fn($v) => (string)$v, 'games played', ''],
    ['Best individual', $leader(fn($r) => $r['ind_rating'] !== null ? (float)$r['ind_rating'] : null), fn($v) => number_format($v, 1), 'individual rating', 'text-green-400'],
], fn($l) => $l[1] !== null);
// provisional: closest to ranked first; from 5 games shown, the others behind a button
usort($provisional, fn($a, $b) => [(int)$b['games'], (float)$b['rating']] <=> [(int)$a['games'], (float)$a['rating']]);
$provShown = array_values(array_filter($provisional, fn($r) => (int)$r['games'] >= 5));
$provMore = array_values(array_filter($provisional, fn($r) => (int)$r['games'] < 5));
$focus = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-400';
$metals = [['1st place', 'text-yellow-300', 'border-t-yellow-300', 'ring-yellow-300'], ['2nd place', 'text-zinc-300', 'border-t-zinc-300', 'ring-zinc-300'], ['3rd place', 'text-orange-400', 'border-t-orange-400', 'ring-orange-400']];
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-7 px-4 py-6 md:px-10 md:py-8">

    <!-- Intro: a banner on the most played map of the recent games (not the one the slider opens on), like the game and ranking pages -->
    <?php
    $mapCounts = array_count_values(array_filter(array_map(fn($g) => \App\View\Helper\LayoutHelper::mapPicture((string)$g['map']) ? (string)$g['map'] : null, $games)));
    unset($mapCounts[(string)($games[0]['map'] ?? '')]);
    arsort($mapCounts);
    $bannerMap = $mapCounts ? $this->Layout->mapImage((string)array_key_first($mapCounts)) : '/img/bullet.jpg'; ?>
    <header class="relative overflow-hidden rounded-xl border border-white/10">
        <img src="<?= h($bannerMap) ?>" alt="" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-r from-black/95 via-black/70 to-black/30"></div>
        <div class="relative flex flex-wrap items-end justify-between gap-x-8 gap-y-4 px-5 py-8 md:px-10 md:py-12">
            <div class="flex min-w-0 flex-col gap-3 drop-shadow-lg">
                <div class="text-xs font-bold uppercase tracking-[0.14em] text-blue-400">
                    Inter / clan matches &middot; <?= count($ratings) ?> ranked &middot; <?= count($provisional) ?> provisional
                </div>
                <h1 class="bg-gradient-to-r from-yellow-200 via-amber-300 to-orange-500 bg-clip-text text-5xl font-extrabold uppercase leading-none tracking-tight text-transparent sm:text-6xl md:text-7xl">Inter Games</h1>
                <div aria-hidden="true" class="h-1 w-24 rounded-full bg-gradient-to-r from-amber-300 to-orange-500"></div>
            </div>
            <?php if (!$public): ?>
                <span class="rounded-lg bg-amber-500/20 px-3 py-1.5 text-xs font-semibold text-amber-200"><i class="fa-solid fa-lock mr-1"></i>Admins only (Ladder.competitive.public is off)</span>
            <?php endif; ?>
        </div>
    </header>

    <!-- Stat leaders (ranked players) -->
    <?php if ($leaders): ?>
        <section aria-label="Stat leaders" class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(min(190px,100%),1fr))]">
            <?php foreach ($leaders as [$title, [$r, $v], $fmt, $unit, $color]): ?>
                <div class="flex flex-col gap-2 rounded-xl border border-white/10 px-4 py-3">
                    <span class="truncate text-[11px] uppercase tracking-widest text-zinc-400"><?= h($title) ?></span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-extrabold leading-none <?= $color ?>"><?= $fmt($v) ?></span>
                        <span class="text-[11px] text-zinc-400"><?= h($unit) ?></span>
                    </div>
                    <div class="flex min-w-0 items-center gap-1.5 border-t border-white/10 pt-2"><?= $nameLink($r) ?></div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <!-- Recent games: a slider of game cards in the style of /games/view (map picture behind the scoreboard) -->
    <section class="flex flex-col gap-3.5" aria-roledescription="carousel" aria-label="Recent games">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-2xl font-extrabold tracking-wide">Recent games</h2>
            <?php if ($games): ?><span id="games-count" class="font-mono text-sm text-zinc-400" aria-live="polite">1 / <?= count($games) ?></span><?php endif; ?>
        </div>
        <?php if (!$games): ?>
            <p class="text-sm text-zinc-400">No games yet &ndash; they show up here as soon as one ends.</p>
        <?php else:
            // literal class names so Tailwind keeps them (as in game_card)
            $teamStyle = [
                'CLA' => ['band' => 'bg-red-600/45', 'text' => 'text-red-400', 'border' => 'border-red-500/40'],
                'RVSF' => ['band' => 'bg-blue-600/45', 'text' => 'text-blue-400', 'border' => 'border-blue-500/40'],
            ];
            // the facts row of a game, all from the stored numbers (comp_player_stats); ties name everyone
            $gameFacts = function (array $g, array $list, bool $flagMode): array {
                $best = function (callable $value) use ($list): ?array {
                    $top = null;
                    $who = [];
                    foreach ($list as $p) {
                        $v = $value($p);
                        if ($v === null) {
                            continue;
                        }
                        if ($top === null || $v > $top) {
                            [$top, $who] = [$v, [$p]];
                        } elseif ($v == $top) {
                            $who[] = $p;
                        }
                    }

                    return $top !== null ? [$who, $top] : null;
                };
                $facts = [];
                if ($f = $best(fn($p) => (int)$p['frags'] > 0 ? (int)$p['frags'] : null)) {
                    $facts[] = ['fa-solid fa-crosshairs', 'Top fragger', $f[0], $f[1] . ' frags'];
                }
                if ($flagMode && ($f = $best(fn($p) => (int)$p['flags'] > 0 ? (int)$p['flags'] : null))) {
                    $facts[] = ['fa-solid fa-flag', 'Most flags', $f[0], $f[1] . ($f[1] === 1 ? ' flag' : ' flags')];
                }
                if ($f = $best(fn($p) => (int)$p['shot_damage'] > 0 ? round((int)$p['damage'] / (int)$p['shot_damage'] * 100) : null)) {
                    $facts[] = ['fa-solid fa-bullseye', 'Best aim', $f[0], $f[1] . '% accuracy'];
                }
                if ($f = $best(fn($p) => $p['rating_after'] !== null && (float)$p['rating_after'] > (float)$p['rating_before'] ? round((float)$p['rating_after'] - (float)$p['rating_before'], 1) : null)) {
                    $facts[] = ['fa-solid fa-arrow-trend-up', 'Biggest Elo gain', $f[0], '+' . number_format($f[1], 1)];
                }
                // upset: the team with the lower win chance won (the win chance is the same for every rated player of a team)
                if ($g['counted'] && $g['winner']) {
                    foreach ($list as $p) {
                        if ($p['team'] === $g['winner'] && $p['expected'] !== null) {
                            if ((float)$p['expected'] < 0.5) {
                                $facts[] = ['fa-solid fa-bolt', 'Upset', null, $g['winner'] . ' won at ' . round((float)$p['expected'] * 100) . '% win chance'];
                            }
                            break;
                        }
                    }
                }

                return $facts;
            };
            $navBtn = 'absolute top-4 md:top-1/2 md:-translate-y-1/2 z-20 h-11 w-11 flex items-center justify-center rounded-full bg-black/60 hover:bg-black/80 text-white transition disabled:opacity-0 ' . $focus; ?>
            <div class="relative overflow-hidden rounded-xl border border-white/10">
                <div id="games-slider" tabindex="0" class="flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden focus-visible:outline-2 focus-visible:outline-blue-400">
                    <?php foreach ($games as $n => $g):
                        $sc = json_decode($g['team_scores'], true) ?: [];
                        $flagMode = in_array($g['mode'], ['ctf', 'htf', 'tktf'], true);
                        $nc = !$g['counted'];
                        $afps = ($g['source'] ?? 'live') === 'actionfps';
                        $map = $this->Layout->cleanMapName((string)$g['map']);
                        $gameUrl = $this->Url->build(['action' => 'game', $g['id']]);
                        $teams = ['CLA' => [], 'RVSF' => []];
                        foreach ($players[$g['id']] ?? [] as $p) {
                            $teams[$p['team']][] = $p;
                        } ?>
                        <article class="relative w-full shrink-0 snap-start overflow-hidden bg-zinc-900" aria-roledescription="slide" aria-label="<?= $n + 1 ?> of <?= count($games) ?>: <?= h($map) ?>">
                            <img src="<?= $this->Layout->mapImage((string)$g['map'], (string)$g['id']) ?>" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-b from-black/50 via-black/10 to-black/60"></div>
                            <div class="relative z-10 flex min-h-[520px] flex-col gap-4 px-4 py-5 md:px-16 md:py-6">
                                <div class="pr-28 drop-shadow-lg md:pr-0">
                                    <h3 class="text-3xl font-extrabold tracking-wide md:text-5xl"><a href="<?= $gameUrl ?>" class="hover:text-blue-300"><?= h($map) ?></a></h3>
                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-200">
                                        <span><i class="far fa-calendar mr-1"></i><?= h(date('D, d M Y', strtotime((string)$g['started_at']))) ?></span>
                                        <span><i class="far fa-clock mr-1"></i><?= h(date('H:i', strtotime((string)$g['started_at']))) ?><?php if ($g['ended_at']): ?> &ndash; <?= h(date('H:i', strtotime((string)$g['ended_at']))) ?><?php endif; ?></span>
                                        <span><?php if ($afps): ?><i class="fa-solid fa-server mr-1"></i>ActionFPS<?php else: ?><?= $this->Layout->serverIcon($g['server_name']) ?: '<i class="fa-solid fa-server mr-1"></i>' ?><?= h($serverName($g['server_name'])) ?><?php endif; ?></span>
                                        <span><?= $this->Layout->gameModeIcon((string)$g['mode']) ?> &middot; <?= h($g['team_size']) ?></span>
                                    </div>
                                </div>
                                <?php if ($facts = $gameFacts($g, $players[$g['id']] ?? [], $flagMode)): ?>
                                    <ul class="flex flex-wrap gap-2 font-mono text-xs">
                                        <?php foreach ($facts as [$icon, $label, $who, $value]): ?>
                                            <li class="flex items-center gap-2 rounded-lg border border-white/15 bg-black/60 px-3 py-1.5 backdrop-blur-[2px]">
                                                <i class="<?= $icon ?> text-amber-300" aria-hidden="true"></i>
                                                <span class="text-zinc-400"><?= h($label) ?></span>
                                                <?php if ($who): ?>
                                                    <span class="font-bold"><?= implode(', ', array_map(fn($p) => '<span class="' . $teamStyle[$p['team']]['text'] . '">' . h((string)($p['player_id'] ? ($p['profile_name'] ?? $p['name']) : $p['name'])) . '</span>', $who)) ?></span>
                                                <?php endif; ?>
                                                <span class="text-zinc-200"><?= h($value) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <!-- Scoreboard -->
                                <div class="mt-auto w-full rounded-lg border border-white/15 bg-black/60 p-3 font-mono text-xs backdrop-blur-[2px] md:p-5 md:text-sm">
                                    <div class="mb-3 flex flex-wrap items-center gap-x-2 text-zinc-200">
                                        <span>"<?= h(strtoupper((string)$g['mode'])) ?>" on map <?= h($g['map']) ?></span>
                                        <?php if ($g['winner']): ?>
                                            <span class="<?= $teamStyle[$g['winner']]['text'] ?>"><?= h($g['winner']) ?> wins!</span>
                                        <?php else: ?>
                                            <span class="text-zinc-400">draw</span>
                                        <?php endif; ?>
                                        <?php if ($nc): ?>
                                            <span class="rounded bg-amber-500/25 px-1.5 text-[10px] font-bold uppercase text-amber-300" title="<?= h((string)$g['reason']) ?>">not counted</span>
                                        <?php endif; ?>
                                        <a href="<?= $gameUrl ?>" class="ml-auto text-blue-300 hover:underline">Elo calculation <i class="fa-solid fa-chevron-right text-[10px]"></i></a>
                                    </div>
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <?php foreach ($teams as $team => $rows): $st = $teamStyle[$team]; $tsc = $sc[$team] ?? []; ?>
                                            <div class="min-w-0 overflow-hidden rounded border <?= $st['border'] ?>">
                                                <div class="flex items-center justify-between gap-4 px-3 py-3 md:px-4 <?= $st['band'] ?>">
                                                    <div class="flex items-center gap-2 text-2xl font-extrabold tracking-wider text-white drop-shadow md:text-3xl">
                                                        <?= $team ?>
                                                        <?php if ($g['winner'] === $team): ?>
                                                            <span class="rounded bg-yellow-400/25 px-2 py-0.5 text-xs font-bold tracking-normal text-yellow-300">🏆 wins</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="flex gap-4 text-right tabular-nums md:gap-6">
                                                        <?php if ($flagMode): ?>
                                                            <div><div class="text-3xl font-extrabold leading-none text-white drop-shadow md:text-4xl"><?= (int)($tsc['flags'] ?? 0) ?></div><div class="mt-1 text-[10px] uppercase tracking-wider text-zinc-200">flags</div></div>
                                                        <?php endif; ?>
                                                        <div><div class="<?= $flagMode ? 'text-xl md:text-2xl' : 'text-3xl md:text-4xl' ?> font-extrabold leading-none text-white drop-shadow"><?= (int)($tsc['frags'] ?? 0) ?></div><div class="mt-1 text-[10px] uppercase tracking-wider text-zinc-200">frags</div></div>
                                                    </div>
                                                </div>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full tabular-nums">
                                                        <thead>
                                                        <tr class="text-zinc-400">
                                                            <th class="px-1.5 py-1 text-left font-normal sm:px-2">name</th>
                                                            <?php if ($flagMode): ?><th class="px-1 py-1 text-right font-normal sm:px-2" title="Flags scored"><i class="fa-solid fa-flag" aria-hidden="true"></i><span class="sr-only">flags</span></th><?php endif; ?>
                                                            <th class="px-1 py-1 text-right font-normal sm:px-2" title="Frags"><i class="fa-solid fa-crosshairs" aria-hidden="true"></i><span class="sr-only">frags</span></th>
                                                            <th class="px-1 py-1 text-right font-normal sm:px-2" title="Deaths"><i class="fa-solid fa-skull" aria-hidden="true"></i><span class="sr-only">deaths</span></th>
                                                            <th class="px-1 py-1 text-right font-normal sm:px-2" title="individual rating of this game, 0.1 - 9.9 (5.0 = average)"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i><span class="sr-only">rating</span></th>
                                                            <th class="px-1 py-1 text-right font-normal sm:px-2" title="Elo change in this game">Elo</th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        <?php foreach ($rows as $p):
                                                            $chg = $p['rating_after'] !== null ? (float)$p['rating_after'] - (float)$p['rating_before'] : null;
                                                            $ir = $p['ind_rating'] !== null ? (float)$p['ind_rating'] : null; ?>
                                                            <tr class="<?= $p['at_end'] ? '' : 'text-zinc-400' ?>">
                                                                <td class="whitespace-nowrap px-1.5 py-1 sm:px-2">
                                                                    <?php if ($p['player_id']): ?>
                                                                        <?= $this->Html->link(h((string)($p['profile_name'] ?? $p['name'])), ['controller' => 'Players', 'action' => 'view', $p['player_id']],
                                                                            ['escape' => false, 'class' => 'hover:text-blue-300', 'data-hover-card' => '/players/hover-card/' . $p['player_id']]) ?>
                                                                        <?= $this->Layout->flag($p['country']) ?>
                                                                    <?php else: ?>
                                                                        <span class="<?= $p['name'] === 'Anonymous' ? 'italic text-zinc-400' : '' ?>"><?= h($p['name']) ?></span>
                                                                    <?php endif; ?>
                                                                    <?php if (!$p['at_end']): ?><span class="text-[10px]" title="played <?= round((float)$p['share'] * 100) ?>% of the game">left</span><?php endif; ?>
                                                                </td>
                                                                <?php if ($flagMode): ?><td class="px-1 py-1 text-right sm:px-2"><?= (int)$p['flags'] ?></td><?php endif; ?>
                                                                <td class="px-1 py-1 text-right sm:px-2"><?= (int)$p['frags'] ?></td>
                                                                <td class="px-1 py-1 text-right sm:px-2"><?= $afps ? '–' : (int)$p['deaths'] ?></td>
                                                                <td class="px-1 py-1 text-right font-bold sm:px-2 <?= $indClass($ir) ?>"><?= $ir !== null ? number_format($ir, 1) : '–' ?></td>
                                                                <td class="px-1 py-1 text-right font-bold sm:px-2 <?= $chg === null ? 'text-zinc-500' : ($chg >= 0 ? 'text-green-400' : 'text-red-400') ?>"
                                                                    title="<?= $chg === null ? 'not rated (played under half of it, or the game was not counted)' : (int)round((float)$p['rating_before']) . ' → ' . (int)round((float)$p['rating_after']) ?>"><?= $chg === null ? '–' : ($chg >= 0 ? '+' : '&minus;') . number_format(abs($chg), 1) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="games-prev" disabled aria-label="Newer game" title="Newer game" class="<?= $navBtn ?> right-16 md:right-auto md:left-2"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                <button type="button" id="games-next" aria-label="Older game" title="Older game" class="<?= $navBtn ?> right-2"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
            </div>
        <?php endif; ?>
    </section>



    <?php if (!$ratings): ?>
        <p class="rounded-xl border border-white/10 bg-black/50 p-4 text-sm text-zinc-400">Nobody active has <?= $minGames ?> counted games yet.</p>
    <?php endif; ?>

    <!-- Podium: ranks 1 - 3 -->
    <?php if ($podium): ?>
        <section aria-label="Top three" class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr))]">
            <?php foreach ($podium as $i => $r):
                [$place, $text, $border, $ring] = $metals[$i];
                $d = $delta($r);
                $g = max(1, (int)$r['games']);
                $you = $viewer && $r['player_id'] === $viewer; ?>
                <article class="relative flex flex-col gap-4 overflow-hidden rounded-xl border border-white/10 border-t-[3px] <?= $border ?> <?= $you ? 'bg-blue-500/10' : 'bg-black/50' ?> p-5">
                    <div aria-hidden="true" class="pointer-events-none absolute -top-5 right-3 text-[150px] font-extrabold leading-none opacity-[0.12] <?= $text ?>"><?= $i + 1 ?></div>
                    <div class="relative flex items-center gap-3.5">
                        <img src="<?= $pic($r) ?>" alt="" class="h-16 w-16 shrink-0 rounded-full object-cover ring-2 ring-offset-2 ring-offset-zinc-950 <?= $ring ?>">
                        <div class="flex min-w-0 flex-col gap-1">
                            <div class="text-xs font-bold uppercase tracking-[0.12em] <?= $text ?>"><?= $place ?>
                                <?php if ($you): ?><span class="ml-1 rounded bg-blue-500 px-1.5 text-[11px] text-white">You</span><?php endif; ?></div>
                            <div class="flex min-w-0 items-center gap-1.5 text-xl <?= $shade($idle($r)) ?>"><?= $nameLink($r) ?></div>
                            <?php if ($b = $badges($r)): ?><div class="flex flex-wrap items-center gap-1.5"><?= $b ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="relative flex items-baseline gap-3">
                        <span class="text-6xl font-extrabold leading-none text-amber-300"><?= (int)round((float)$r['rating']) ?></span>
                        <span class="text-xs text-zinc-400">Elo</span>
                        <?php if ($r['ind_rating'] !== null): ?>
                            <span class="ml-auto text-right" title="individual rating 0.1 - 9.9 (5.0 = average): own play against the others in the same games, average of the last <?= \App\Service\CompetitiveRatings::IND_WINDOW ?> games - does not change the Elo">
                                <span class="text-3xl font-extrabold leading-none <?= $indClass($r['ind_rating']) ?>"><?= number_format((float)$r['ind_rating'], 1) ?></span>
                                <span class="block text-xs text-zinc-400">Ind.</span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex justify-between gap-2 text-xs text-zinc-400">
                            <span><b class="text-green-400"><?= (int)$r['wins'] ?> W</b> &middot; <?= (int)$r['draws'] ?> D &middot; <b class="text-red-400"><?= (int)$r['losses'] ?> L</b></span>
                            <span><?= round((int)$r['wins'] / $g * 100) ?>% wins &middot; <?= (int)$r['games'] ?> games</span>
                        </div>
                        <?= $wdlBar($r, 'h-2') ?>
                    </div>
                    <dl class="grid grid-cols-4 gap-2">
                        <div class="rounded-lg bg-white/5 p-2.5"><dt class="text-[11px] tracking-wider text-zinc-400" title="frags per death">K/D</dt><dd class="mt-0.5 font-bold"><?= $r['kd'] !== null ? number_format($r['kd'], 2) : '–' ?></dd></div>
                        <div class="rounded-lg bg-white/5 p-2.5"><dt class="text-[11px] tracking-wider text-zinc-400" title="damage done out of the damage the shots could have done">ACC</dt><dd class="mt-0.5 font-bold"><?= $r['acc'] !== null ? round($r['acc']) . '%' : '–' ?></dd></div>
                        <div class="rounded-lg bg-white/5 p-2.5"><dt class="text-[11px] tracking-wider text-zinc-400" title="highest Elo reached">ELO PEAK</dt><dd class="mt-0.5 font-bold text-amber-300"><?= (int)round((float)$r['peak']) ?></dd></div>
                        <div class="rounded-lg bg-white/5 p-2.5"><dt class="text-[11px] tracking-wider text-zinc-400" title="score against expected">VS EXP.</dt><dd class="mt-0.5 font-bold <?= $d === null ? '' : ($d >= 0 ? 'text-green-400' : 'text-red-400') ?>"><?= $d !== null ? $signed($d) : '–' ?></dd></div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>


    <!-- The chase: ranks 4 to N, in the All Time Ranking's scoreboard style -->
    <?php if ($chase): ?>
        <section class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-2xl font-extrabold tracking-wide">The chase <span class="text-sm font-normal text-zinc-400">ranks 4 &ndash; <?= count($ratings) ?></span>
                    <a href="#how" data-how class="ml-1 text-xs font-normal text-amber-300 hover:underline">how it works</a></h2>
                <?php if ($viewerRank !== null && $viewerRank > 1):
                    $me = $ratings[$viewerRank - 1];
                    $above = $ratings[$viewerRank - 2]; ?>
                    <div class="flex items-center gap-3 rounded-lg border border-blue-400 bg-black/60 px-3.5 py-2 text-sm">
                        <span class="text-[11px] font-bold tracking-widest text-blue-400">YOU</span>
                        <span>#<?= $viewerRank ?> &middot; <b class="text-amber-300"><?= (int)round((float)$me['rating']) ?></b> &middot;
                            <?= max(1, (int)ceil((float)$above['rating'] - (float)$me['rating'])) ?> Elo to pass <?= h($name($above)) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto rounded-lg border border-white/15 bg-black/60 font-mono text-xs md:text-sm">
                <table class="w-full tabular-nums">
                    <thead class="text-zinc-400">
                    <tr class="border-b border-white/10 lowercase">
                        <th class="px-1.5 py-2 text-right font-normal sm:px-2">#</th>
                        <th class="px-1.5 py-2 text-left font-normal sm:px-2">name</th>
                        <th class="px-1.5 py-2 text-right font-normal sm:px-2">Elo</th>
                        <th class="px-1.5 py-2 text-right font-normal sm:px-2" title="individual rating 0.1 - 9.9 (5.0 = average): own play against the others in the same games, average of the last <?= \App\Service\CompetitiveRatings::IND_WINDOW ?> games - does not change the Elo">Ind.</th>
                        <th class="hidden px-1.5 py-2 text-right font-normal sm:table-cell sm:px-2" title="score minus expected: above 0 raises the Elo">vs exp.</th>
                        <th class="px-1.5 py-2 text-right font-normal sm:px-2" title="wins - draws - losses">W-D-L</th>
                        <th class="hidden px-1.5 py-2 text-right font-normal sm:table-cell sm:px-2" title="frags per death">K/D</th>
                        <th class="hidden px-1.5 py-2 text-right font-normal md:table-cell sm:px-2" title="damage done out of the damage the shots could have done - not in match mode, the server hides it there">Acc</th>
                        <th class="hidden px-1.5 py-2 text-right font-normal lg:table-cell sm:px-2">Peak</th>
                        <th class="hidden px-1.5 py-2 text-right font-normal lg:table-cell sm:px-2" title="Elo won in the games you gained from (Elo = <?= $start ?> + Gained - Lost)">Gained</th>
                        <th class="hidden px-1.5 py-2 text-right font-normal lg:table-cell sm:px-2" title="Elo given up in the games you lost points in">Lost</th>
                        <th class="hidden px-1.5 py-2 text-right font-normal xl:table-cell sm:px-2" title="what you scored: wins + half a point per draw">Score</th>
                        <th class="hidden px-1.5 py-2 text-right font-normal xl:table-cell sm:px-2" title="the win chances of all your games added up: what an average player would have scored in your games">Expected</th>
                        <th class="hidden px-1.5 py-2 text-right font-normal xl:table-cell sm:px-2" title="average strength (Elo) of the teams you played against">Opp.</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($chase as $j => $r):
                        $rank = $j + 4;
                        $prev = $ratings[$rank - 2];
                        $you = $viewer && $r['player_id'] === $viewer;
                        $days = $idle($r);
                        $d = $delta($r);
                        if ((float)$prev['rating'] >= $start && (float)$r['rating'] < $start): ?>
                            <tr aria-hidden="true"><td colspan="14" class="px-2 py-2.5">
                                <div class="flex items-center gap-3"><span class="flex-1 border-t border-dashed border-amber-300/40"></span>
                                    <span class="text-[11px] font-bold tracking-[0.14em] text-amber-300"><?= $start ?> &middot; STARTING LINE</span>
                                    <span class="flex-1 border-t border-dashed border-amber-300/40"></span></div>
                            </td></tr>
                        <?php endif; ?>
                        <tr class="border-b border-white/5 hover:bg-white/5 <?= $you ? 'bg-blue-500/10' : '' ?>">
                            <td class="px-1.5 py-2 text-right text-base font-extrabold text-zinc-400 sm:px-2"><?= $rank ?></td>
                            <td class="px-1.5 py-2 sm:px-2">
                                <div class="flex min-w-0 items-center gap-3">
                                    <img src="<?= $pic($r) ?>" alt="" class="hidden h-9 w-9 shrink-0 rounded-full object-cover sm:block">
                                    <div class="min-w-0">
                                        <div class="flex min-w-0 items-center gap-1.5 <?= $shade($days) ?>">
                                            <?= $nameLink($r) ?>
                                            <?php if ($you): ?><span class="shrink-0 rounded bg-blue-500 px-1.5 text-[11px] font-bold text-white">YOU</span><?php endif; ?>
                                            <?= $badges($r) ?>
                                        </div>
                                        <div class="mt-0.5 text-[11px] <?= $days >= 20 ? $shade($days) : 'text-zinc-400' ?>">
                                            <span class="whitespace-nowrap" title="last game <?= h(date('M j, Y H:i', strtotime((string)$r['last_game_at']))) ?>"><i class="far fa-clock" aria-hidden="true"></i> <?= h($ago($days)) ?></span>
                                            <span class="ml-2 whitespace-nowrap"><?= (int)$r['games'] ?> games</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-1.5 py-2 text-right sm:px-2">
                                <span class="font-bold text-amber-300"><?= (int)round((float)$r['rating']) ?></span>
                            </td>
                            <td class="px-1.5 py-2 text-right font-bold sm:px-2 <?= $indClass($r['ind_rating']) ?>"><?= $r['ind_rating'] !== null ? number_format((float)$r['ind_rating'], 1) : '–' ?></td>
                            <td class="hidden px-1.5 py-2 text-right font-bold sm:table-cell sm:px-2 <?= $d === null ? 'text-zinc-500' : ($d >= 0 ? 'text-green-400' : 'text-red-400') ?>"><?= $d !== null ? $signed($d) : '–' ?></td>
                            <td class="px-1.5 py-2 text-right sm:px-2">
                                <span class="whitespace-nowrap"><span class="text-green-400"><?= (int)$r['wins'] ?></span>-<?= (int)$r['draws'] ?>-<span class="text-red-400"><?= (int)$r['losses'] ?></span></span>
                                <span class="mt-1 ml-auto block w-20"><?= $wdlBar($r) ?></span>
                            </td>
                            <td class="hidden px-1.5 py-2 text-right text-zinc-200 sm:table-cell sm:px-2"><?= $r['kd'] !== null ? number_format($r['kd'], 2) : '–' ?></td>
                            <td class="hidden px-1.5 py-2 text-right text-zinc-200 md:table-cell sm:px-2"><?= $r['acc'] !== null ? round($r['acc']) . '%' : '–' ?></td>
                            <td class="hidden px-1.5 py-2 text-right text-zinc-400 lg:table-cell sm:px-2"><?= (int)round((float)$r['peak']) ?></td>
                            <td class="hidden px-1.5 py-2 text-right text-green-400 lg:table-cell sm:px-2">+<?= (int)round($r['gained']) ?></td>
                            <td class="hidden px-1.5 py-2 text-right text-red-400 lg:table-cell sm:px-2">&minus;<?= (int)round($r['lost']) ?></td>
                            <td class="hidden px-1.5 py-2 text-right xl:table-cell sm:px-2"><?= $num($r['score']) ?></td>
                            <td class="hidden px-1.5 py-2 text-right text-zinc-300 xl:table-cell sm:px-2"><?= $r['expected'] !== null ? number_format($r['expected'], 1) : '–' ?></td>
                            <td class="hidden px-1.5 py-2 text-right text-zinc-400 xl:table-cell sm:px-2"><?= $r['opp'] !== null ? (int)round($r['opp']) : '–' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($inactive): ?>
                <p class="text-xs text-zinc-400"><?= $inactive ?> more without a game in the last <?= $activeDays ?> days - they are back as soon as they play.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Provisional -->
    <?php if ($provisional):
        $provCard = function (array $r) use ($pic, $nameLink, $minGames): string {
            $g = (int)$r['games'];
            $left = $minGames - $g;
            $segments = '';
            for ($s = 0; $s < $minGames; $s++) {
                $segments .= '<span class="flex-1 rounded-sm ' . ($s < $g ? 'bg-blue-500' : 'bg-white/10') . '"></span>';
            }

            return '<div class="flex flex-col gap-2.5 rounded-lg border border-white/10 bg-black/50 px-3.5 py-3">'
                . '<div class="flex min-w-0 items-center gap-2.5"><img src="' . $pic($r) . '" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover">'
                . '<span class="flex min-w-0 flex-1 items-center gap-1.5">' . $nameLink($r) . '</span>'
                . '<span class="font-bold text-amber-300/80">' . (int)round((float)$r['rating']) . '</span></div>'
                . '<div class="flex items-center gap-2.5"><span aria-hidden="true" class="flex h-1.5 flex-1 gap-0.5">' . $segments . '</span>'
                . '<span class="whitespace-nowrap text-xs text-zinc-400">' . $g . '/' . $minGames . ' &middot; ' . $left . ($left === 1 ? ' game to go' : ' to go') . '</span></div></div>';
        }; ?>
        <section class="flex flex-col gap-3.5">
            <div class="flex flex-wrap items-baseline gap-x-3.5 gap-y-1.5">
                <h2 class="text-2xl font-extrabold tracking-wide">Knocking on the door</h2>
                <span class="text-sm text-zinc-400">Provisional: under <?= $minGames ?> games, Elo still finding its level. Closest to ranked first.</span>
            </div>
            <?php if ($provShown): ?>
                <div class="grid gap-2.5 [grid-template-columns:repeat(auto-fit,minmax(min(250px,100%),1fr))]">
                    <?php foreach ($provShown as $r): ?><?= $provCard($r) ?><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($provMore): ?>
                <div id="prov-more" <?= $provShown ? 'hidden' : '' ?> class="grid gap-2.5 [grid-template-columns:repeat(auto-fit,minmax(min(250px,100%),1fr))]">
                    <?php foreach ($provMore as $r): ?><?= $provCard($r) ?><?php endforeach; ?>
                </div>
                <?php if ($provShown): ?>
                    <button type="button" aria-controls="prov-more" aria-expanded="false"
                            onclick="document.getElementById('prov-more').hidden = false; this.remove()"
                            class="min-h-11 self-start rounded-lg border border-white/15 px-4 text-sm text-zinc-300 transition hover:bg-white/5 <?= $focus ?>">
                        Show <?= count($provMore) ?> more with fewer than 5 games
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>


    <!-- How games count and the Elo works: the calculation folded, "how it works" at the ranking opens it -->
    <details class="group scroll-mt-20 rounded-xl border border-amber-300/30 bg-black/50 p-4" id="how">
        <summary class="cursor-pointer list-none text-lg font-bold">
            <i class="fa-solid fa-calculator mr-2 text-amber-300" aria-hidden="true"></i>How the Elo works
            <span class="ml-1 text-sm font-normal text-zinc-400 group-open:hidden">&ndash; show the calculation</span>
            <p class="mt-2 max-w-4xl text-sm font-normal text-zinc-400">
                Team games of <b class="text-white">2 vs 2, 3 vs 3</b> and up on the clan match servers (<?= h(implode(', ', array_map($serverName, $servers))) ?>), apart from the ladder.
                A game counts when it starts with even teams and runs its full 15 minutes &ndash; seen live, every few seconds. Rating: Elo &ndash; everyone starts at
                <?= $start ?>, beating a stronger team brings more. Ranked from <?= $minGames ?> games on (the first ones only find your level), with a game in the last <?= $activeDays ?> days &ndash; names grey out the longer someone hasn't played.
            </p>
        </summary>
        <div class="mt-3 grid gap-4 text-sm text-zinc-300 lg:grid-cols-2">
            <ol class="list-decimal space-y-2 pl-5">
                <li>Everyone starts at <b class="text-white"><?= $start ?></b>.</li>
                <li><b class="text-white">Team strength</b> = the average Elo of the team's players before the game. Only players who
                    played at least <?= round($minShare * 100) ?>% of the game count, and only they gain or lose.</li>
                <li><b class="text-white">Win chance</b> = 1 / (1 + 10<sup>(their strength &minus; your strength) / 400</sup>).
                    Equal teams: 50%. A team 100 stronger: 64%; 200 stronger: 76%.</li>
                <li><b class="text-white">Result</b>: a win is 1, a draw &frac12;, a loss 0.</li>
                <li><b class="text-white">Change</b> = K &times; (result &minus; win chance). K is <?= (int)$k['new'] ?> for your first
                    <?= (int)$k['newGames'] ?> games (finding your level fast), then <?= (int)$k['normal'] ?>.
                    Beating a stronger team brings more than beating a weaker one; losing to a weaker one costs more.</li>
                <li>Everything is replayed from the first counted game in order, so every number below can be checked on the game pages.</li>
            </ol>
            <div class="space-y-3">
                <?php if ($example):
                    $res = $example['winner'] === null ? 0.5 : ($example['winner'] === $example['team'] ? 1 : 0);
                    $chg = (float)$example['rating_after'] - (float)$example['rating_before']; ?>
                    <div class="rounded-lg bg-white/5 p-3">
                        <div class="mb-1 text-xs uppercase tracking-wide text-zinc-400">Example: the latest game,
                            <a class="text-blue-300 hover:underline" href="<?= $this->Url->build(['action' => 'game', $example['game_id']]) ?>"><?= h($this->Layout->cleanMapName((string)$example['map'])) ?> <?= h($example['team_size']) ?></a></div>
                        <table class="w-full font-mono text-xs">
                            <tr><td class="py-0.5 text-zinc-400">Player</td><td class="text-right text-white"><?= h($example['name']) ?> (<?= h($example['team']) ?>)</td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Elo before</td><td class="text-right"><?= number_format((float)$example['rating_before'], 1, '.', '') ?></td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Own team strength</td><td class="text-right"><?= number_format((float)$example['team_rating'], 1, '.', '') ?></td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Other team strength</td><td class="text-right"><?= number_format((float)$example['opp_rating'], 1, '.', '') ?></td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Win chance</td><td class="text-right">1 / (1 + 10<sup>(<?= number_format((float)$example['opp_rating'], 1, '.', '') ?> &minus; <?= number_format((float)$example['team_rating'], 1, '.', '') ?>) / 400</sup>) = <?= number_format((float)$example['expected'] * 100, 1) ?>%</td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Result</td><td class="text-right"><?= $res === 1 ? 'win = 1' : ($res === 0 ? 'loss = 0' : 'draw = 0.5') ?></td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Change</td><td class="text-right"><?= (int)$example['k_factor'] ?> &times; (<?= $res ?> &minus; <?= number_format((float)$example['expected'], 3) ?>) = <b class="<?= $chg >= 0 ? 'text-green-400' : 'text-red-400' ?>"><?= ($chg >= 0 ? '+' : '') . number_format($chg, 1) ?></b></td></tr>
                            <tr class="border-t border-white/10"><td class="pt-1 text-zinc-400">Elo after</td><td class="pt-1 text-right font-bold text-amber-300"><?= number_format((float)$example['rating_after'], 1, '.', '') ?></td></tr>
                        </table>
                    </div>
                <?php endif; ?>
                <?php $iw = \App\Service\CompetitiveRatings::IND_WEIGHTS['flags']; ?>
                <div class="rounded-lg bg-white/5 p-3 text-xs text-zinc-300">
                    <div class="mb-1 font-bold text-white"><i class="fa-solid fa-user mr-1 text-amber-300"></i>Individual rating (Ind.)</div>
                    The Elo only knows who won. Next to it, <b>Ind.</b> rates your own play, 0.1 &ndash; 9.9 (5.0 = average): in every rated game
                    your numbers are compared with the average of that game, per share of the game you played, on one scale for all games
                    (a close game gives close ratings) &ndash;
                    flags scored <?= round($iw['flags'] * 100) ?>%, damage dealt <?= round($iw['damage'] * 100) ?>%, K/D <?= round($iw['kd'] * 100) ?>%,
                    team result <?= round($iw['win'] * 100) ?>%, teamkills <?= round($iw['discipline'] * 100) ?>% (minus). In modes without flags:
                    damage <?= round(\App\Service\CompetitiveRatings::IND_WEIGHTS['other']['damage'] * 100) ?>%, K/D <?= round(\App\Service\CompetitiveRatings::IND_WEIGHTS['other']['kd'] * 100) ?>%.
                    Your Ind. is the average of your last <?= \App\Service\CompetitiveRatings::IND_WINDOW ?> games; each game's own rating is on the game page. It never changes the Elo.
                </div>
                <p class="text-xs text-zinc-400">In the table: <b class="text-zinc-200">Elo = <?= $start ?> + Gained &minus; Lost</b>.
                    <b class="text-zinc-200">Score</b> is what you got (wins + &frac12; per draw), <b class="text-zinc-200">Expected</b> what the win chances
                    of all your games add up to: scoring more than expected (<b class="text-zinc-200">vs exp.</b>) is what raises your Elo. <b class="text-zinc-200">Opp.</b> is the average
                    strength of the teams you played against.</p>
            </div>
        </div>
    </details>
</div>
</div>
<script>
(function () {
    const how = document.getElementById('how');
    document.querySelectorAll('[data-how]').forEach(a => a.addEventListener('click', () => { how.open = true; }));
    if (location.hash === '#how') how.open = true;

    // recent games slider: arrows move one game, swiping and the scrollbar-less scroll snap per game
    const slider = document.getElementById('games-slider');
    if (!slider) return;
    const prev = document.getElementById('games-prev'), next = document.getElementById('games-next'), count = document.getElementById('games-count');
    const total = slider.children.length;
    const index = () => Math.round(slider.scrollLeft / slider.clientWidth);
    const go = (i) => slider.scrollTo({left: Math.max(0, Math.min(total - 1, i)) * slider.clientWidth, behavior: 'smooth'});
    const update = () => {
        const i = index();
        prev.disabled = i <= 0;
        next.disabled = i >= total - 1;
        count.textContent = (i + 1) + ' / ' + total;
    };
    prev.addEventListener('click', () => go(index() - 1));
    next.addEventListener('click', () => go(index() + 1));
    slider.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.preventDefault(); go(index() + (e.key === 'ArrowRight' ? 1 : -1)); }
    });
    slider.addEventListener('scroll', () => requestAnimationFrame(update), {passive: true});
    update();
})();
</script>
<?= $this->element('hover_card_script') ?>
