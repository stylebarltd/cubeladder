<?php

use Cake\Core\Configure;

?>
<?php
$bgMap = !empty($favoriteMap['name']) && is_file(WWW_ROOT . 'img/maps/' . $favoriteMap['name'] . '.jpg')
    ? \App\View\Helper\LayoutHelper::mapUrl($favoriteMap['name'] . '.jpg')
    : '/img/bullet.jpg';
$panel = 'rounded-xl border border-white/15 bg-black/60 backdrop-blur-[2px]';
$achievementLabels = [
    'total_score' => 'Most Points', 'kills' => 'Most Kills', 'teamkills' => 'Most Teamkills',
    'kd_ratio' => 'Best KD Ratio', 'gibbed' => 'Most Gibs', 'suicided' => 'Most Suicided',
    'slashed' => 'Most Slashes', 'scored_with_the_flag' => 'Most Flags scored',
    'headshot' => 'Most Headshots', 'best_on_map' => 'Best on Map', 'games' => 'Most Games Played',
];
?>
<?php
// Link previews (Discord, WhatsApp, ...): the player card picture (PlayersController::card)
if ((int)$player->track === 1):
    $site = rtrim((string)(Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');
    $ogTitle = $player->name . ' · cubeLadder';
    $ogText = !empty($rating)
        ? sprintf('CTF rating %s · %s · #%d of %s rated players', number_format((float)$rating->rating, 1),
            \App\Command\CalculateRatingsCommand::typeLabel($rating->type, (int)$rating->attack_pct, (int)$rating->defense_pct, (int)$rating->combat_pct),
            (int)$rating->rank, number_format((int)$ratedPlayers))
        : 'AssaultCube player stats on cubeLadder';
    if (!empty($rating) && $rating->trend !== null && abs((float)$rating->trend) >= \App\Command\CalculateRatingsCommand::TREND_ARROW) {
        $ogText = str_replace('CTF rating ' . number_format((float)$rating->rating, 1),
            'CTF rating ' . number_format((float)$rating->rating, 1) . ((float)$rating->trend > 0 ? ' ▲ on the rise' : ' ▼ going down'), $ogText);
    }
    if (!empty($timePlayed['all'])) {
        $ogText .= ' · ' . $this->Layout->duration((int)$timePlayed['all']) . ' played';
    }
    // a new picture URL whenever the rating changes, so previews don't stay stale
    $ogImage = $site . '/players/card/' . $player->id . '?v=' . substr(md5(json_encode([$rating?->rating, $rating?->type, $rating?->rank, $rating?->trend, $player->name, $player->picture, array_column($weapons['choice'] ?? [], 'pct', 'key'), 'card-v2'])), 0, 8);
    $this->assign('title', $ogTitle);
    $this->start('meta'); ?>
    <meta property="og:type" content="profile">
    <meta property="og:site_name" content="cubeLadder">
    <meta property="og:title" content="<?= h($ogTitle) ?>">
    <meta property="og:description" content="<?= h($ogText) ?>">
    <meta property="og:url" content="<?= h($site . '/players/view/' . $player->id) ?>">
    <meta property="og:image" content="<?= h($ogImage) ?>">
    <meta property="og:image:width" content="<?= \App\Service\PlayerCardImage::WIDTH ?>">
    <meta property="og:image:height" content="<?= \App\Service\PlayerCardImage::HEIGHT ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="description" content="<?= h($ogText) ?>">
<?php $this->end();
endif;
?>
<!-- Player page on their most played map (or the bullet) -->
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center bg-fixed text-white"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.55), rgba(0,0,0,1) 35%, rgba(0,0,0,.8)), url('<?= $bgMap ?>');">
<div class="mx-auto w-full max-w-7xl px-4 py-6 md:px-16 md:py-8">

    <!-- Header -->
    <div class="<?= $panel ?> mb-6 flex flex-col gap-6 p-5 md:p-6 lg:flex-row lg:items-center">
        <div class="flex min-w-0 flex-wrap items-center gap-5 sm:flex-nowrap lg:flex-1">
        <a href="<?= $this->Layout->playerPicture($player) ?>" class="shrink-0">
            <img src="<?= $this->Layout->playerPicture($player) ?>" alt="<?= h($player->name) ?>"
                 class="h-24 w-24 md:h-36 md:w-36 rounded-full object-cover ring-2 ring-white/25">
        </a>
        <div class="min-w-0">
            <?php
            // last week's best (not "best on map"), as badges like in the rankings
            $lastWeek = date('Y-m-d', strtotime('last week sunday'));
            $weekBadges = array_filter($player->achievements ?? [], fn($a) => $a->event_type !== 'best_on_map'
                && (is_string($a->week_end) ? $a->week_end : $a->week_end->format('Y-m-d')) === $lastWeek);
            ?>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <h1 class="text-3xl md:text-5xl font-extrabold tracking-wide">
                    <?= h($player->name) ?> <?= $this->Layout->flag($player->country) ?>
                </h1>
                <?php if (!\App\Utility\Activity::isActive($lastSeen['started_at'] ?? null)): ?>
                    <!-- no game in the last Activity::ACTIVE_DAYS: out of the rankings until the next one -->
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-sm font-semibold tracking-normal text-zinc-300"
                          title="No game in the last <?= \App\Utility\Activity::ACTIVE_DAYS ?> days: not in the rankings and the CTF rating until the next game">
                        <i class="fa-solid fa-moon text-xs"></i>Inactive
                    </span>
                <?php endif; ?>
                <?php foreach ($weekBadges as $a): ?>
                    <?= $this->element('achievement_badge', ['event' => $a->event_type, 'title' => ($achievementLabels[$a->event_type] ?? $a->event_type) . ' last week', 'large' => true]) ?>
                <?php endforeach; ?>
            </div>
            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-zinc-200">
                <?php if ($player->rankAllTime > 0): ?>
                    <span title="All Time Ranking by points"><i class="fa-solid fa-ranking-star mr-1"></i>#<?= (int)$player->rankAllTime ?> all time</span>
                <?php endif; ?>
                <?php if ($player->rankTheLast100 > 0): ?>
                    <span><i class="fa-solid fa-bolt mr-1"></i>#<?= (int)$player->rankTheLast100 ?> in the last 100</span>
                <?php endif; ?>
                <?php if (!empty($favoriteMap['name'])): ?>
                    <span><i class="fa-solid fa-map mr-1"></i>
                        <?= $this->Html->link(h($favoriteMap['name']), ['controller' => 'Maps', 'action' => 'index', '?' => ['map' => $favoriteMap['name']]], ['class' => 'hover:text-blue-300', 'escape' => false]) ?>
                        (<?= (int)$favoriteMap['n'] ?> games)</span>
                <?php endif; ?>
                <?php if (!empty($timePlayed['all'])): ?>
                    <span title="time on a team, all counted games"><i class="fa-solid fa-clock mr-1"></i><?= $this->Layout->duration($timePlayed['all']) ?> played</span>
                <?php endif; ?>
                <?php if (!empty($lastSeen)):
                    $seenAt = new \Cake\I18n\DateTime($lastSeen['started_at']);
                    $days = (int)floor((time() - $seenAt->getTimestamp()) / 86400);
                    $seenLabel = $days <= 0 ? 'today' : ($days === 1 ? 'yesterday' : $days . ' days ago');
                    ?>
                    <?= $this->Html->link('<i class="fa-regular fa-clock mr-1"></i>last seen ' . h($seenLabel),
                        ['controller' => 'Games', 'action' => 'view', $lastSeen['id']],
                        ['escape' => false, 'title' => 'Last game: ' . $seenAt->format('j M Y, H:i'), 'class' => 'hover:text-blue-300']) ?>
                <?php endif; ?>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <?php if (!empty($canEdit)): ?>
                    <?= $this->Html->link('<i class="fa-solid fa-pen mr-1"></i> Edit profile',
                        ['controller' => 'Players', 'action' => 'profile'],
                        ['escape' => false, 'class' => 'rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition']) ?>
                    <?php if ((int)$player->track === 0): ?>
                        <span class="rounded bg-amber-500/20 px-2 py-1 text-xs text-amber-300">
                            <i class="fa-solid fa-eye-slash mr-1"></i> Not tracked &ndash; only you can see this page
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($authPlayer && $authPlayer->id != $player->id): ?>
                    <?= $this->Html->link('<i class="fa-solid fa-envelope mr-1"></i> Send Message',
                        ['controller' => 'Messages', 'action' => 'send', $player->id],
                        ['escape' => false, 'class' => 'rounded-lg border border-blue-400/50 bg-black/40 px-4 py-2 text-sm font-semibold text-blue-300 hover:bg-blue-500/20 transition']) ?>
                <?php endif; ?>
            </div>
        </div>
        </div>

        <!-- right side: weapons of choice -->
        <div class="w-full lg:w-[44%] lg:shrink-0">
        <?php if (!empty($weapons['choice'])): ?>
            <!-- Weapons of choice (all counted games), the same pick as the icons in the rankings -->
            <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4 md:p-5">
                <div class="mb-4 border-b border-white/10 pb-3 text-xs uppercase tracking-[0.2em] text-zinc-400">Weapons of choice</div>
                <div class="flex flex-wrap items-center gap-y-3 divide-white/10 sm:flex-nowrap sm:divide-x">
                    <?php foreach ($weapons['choice'] as $i => $weapon): ?>
                        <div class="flex min-w-0 items-center gap-3 pr-5 <?= $i > 0 ? 'sm:pl-5' : '' ?>" title="<?= number_format($weapon['kills']) ?> kills with the <?= h($weapon['name']) ?>">
                            <img src="/img/weapons/<?= h($weapon['key']) ?>.svg" alt="" class="h-10 w-10 shrink-0 md:h-12 md:w-12">
                            <div class="min-w-0 leading-snug">
                                <div class="truncate text-sm font-bold md:text-base"><?= h($weapon['name']) ?></div>
                                <div class="text-xs text-zinc-400"><?= $weapon['pct'] ?>% of kills</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- every weapon's share of the kills -->
                <?php $weaponColors = ['rifle' => 'bg-yellow-400', 'smg' => 'bg-lime-400', 'sniper' => 'bg-sky-400', 'shotgun' => 'bg-orange-400',
                    'carabine' => 'bg-teal-400', 'pistol' => 'bg-zinc-300', 'knife' => 'bg-purple-400', 'grenade' => 'bg-red-400']; ?>
                <div class="mt-5 flex h-2 w-full gap-0.5 overflow-hidden rounded-full bg-white/10">
                    <?php foreach ($weapons['all'] as $key => $kills): ?>
                        <span class="<?= $weaponColors[$key] ?>" style="width: <?= round($kills * 100 / $weapons['kills'], 2) ?>%"
                              title="<?= h(\App\Controller\PlayersController::WEAPON_NAMES[$key]) ?>: <?= number_format($kills) ?> kills (<?= round($kills * 100 / $weapons['kills']) ?>%)"></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        </div>
    </div>

    <!-- CTF rating | fun facts -->
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <?php if (!empty($rating)):
            $ratingValue = (float)$rating->rating;
            $ratingColor = match (true) {
                $ratingValue >= 8.0 => 'text-sky-300',
                $ratingValue >= 7.0 => 'text-green-400',
                $ratingValue >= 6.0 => 'text-lime-300',
                $ratingValue >= 5.0 => 'text-yellow-300',
                $ratingValue >= 4.0 => 'text-orange-400',
                default => 'text-red-400',
            };
            [$typeIcon, $typeColor] = match ($rating->type) {
                'All-Rounder' => ['fa-star', 'bg-yellow-400/20 text-yellow-300'],
                'Flag Runner' => ['fa-person-running', 'bg-red-500/20 text-red-300'],
                'Defender' => ['fa-shield-halved', 'bg-blue-500/20 text-blue-300'],
                'Fragger' => ['fa-crosshairs', 'bg-orange-500/20 text-orange-300'],
                'Offensive Team Player' => ['fa-angles-right', 'bg-white/10 text-zinc-200'],
                default => ['fa-shield', 'bg-white/10 text-zinc-200'],
            };
            ?>
            <!-- CTF rating and player type (bin/cake CalculateRatings) -->
            <a href="/about#rating" title="How the rating works"
               class="<?= $panel ?> block p-5 transition hover:border-white/30 md:p-6">
                <h2 class="mb-5 flex items-center gap-3 border-b border-white/10 pb-4 text-sm uppercase tracking-[0.2em] text-zinc-300">
                    <i class="fa-solid fa-crosshairs text-zinc-400"></i>CTF rating
                </h2>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 sm:flex-nowrap">
                    <div class="shrink-0 font-mono text-6xl font-extrabold tabular-nums leading-none md:text-7xl <?= $ratingColor ?>"><?= number_format($ratingValue, 1) ?><?= $this->Layout->trendArrow($rating->trend !== null ? (float)$rating->trend : null, 'ml-1 align-top text-xl') ?></div>
                    <div class="min-w-0 space-y-3 sm:border-l sm:border-white/10 sm:pl-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full border border-current/30 px-4 py-1 text-base font-semibold <?= $typeColor ?>">
                            <i class="fa-solid <?= $typeIcon ?> text-xs"></i><?= h(\App\Command\CalculateRatingsCommand::typeLabel($rating->type, (int)$rating->attack_pct, (int)$rating->defense_pct, (int)$rating->combat_pct)) ?>
                        </span>
                    </div>
                    <div class="text-xs text-zinc-300">
                        <span class="underline decoration-dotted decoration-zinc-500 underline-offset-2"
                              title="Place among all <?= number_format((int)$ratedPlayers) ?> players with a CTF rating: 20+ CTF games of 3+ minutes and a game in the last <?= \App\Utility\Activity::ACTIVE_DAYS ?> days (players who chose &quot;Don't track me&quot; are not rated)">#<?= (int)$rating->rank ?> of <?= number_format((int)$ratedPlayers) ?> rated players</span>
                        <?php if ($rating->win_rate !== null): ?> · <?= round((float)$rating->win_rate * 100) ?>% won<?php endif; ?>
                        · last <?= (int)$rating->games ?> CTF games
                    </div>
                    </div>
                </div>
                    <div class="mt-6 grid grid-cols-[5.5rem_1fr] items-center gap-x-4 gap-y-4 text-xs uppercase tracking-[0.15em] text-zinc-400">
                        <?php foreach (['Attack' => [$rating->attack_pct, 'bg-red-400'], 'Defense' => [$rating->defense_pct, 'bg-blue-400'], 'Combat' => [$rating->combat_pct, 'bg-amber-400']] as $label => [$value, $bar]): ?>
                            <span><?= $label ?></span>
                            <span class="h-2.5 w-full overflow-hidden rounded-full bg-white/10" title="better than <?= (int)$value ?>% of rated players">
                                <span class="block h-full rounded-full <?= $bar ?>" style="width: <?= max(2, (int)$value) ?>%"></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
            </a>
        <?php elseif (!\App\Utility\Activity::isActive($lastSeen['started_at'] ?? null) && !empty($lastSeen)): ?>
            <!-- inactive: the CTF rating comes back with the next game -->
            <div class="<?= $panel ?> p-5 md:p-6">
                <h2 class="mb-5 flex items-center gap-3 border-b border-white/10 pb-4 text-sm uppercase tracking-[0.2em] text-zinc-300">
                    <i class="fa-solid fa-crosshairs text-zinc-400"></i>CTF rating
                </h2>
                <div class="flex items-center gap-4 text-zinc-300">
                    <i class="fa-solid fa-moon text-4xl text-zinc-500"></i>
                    <div>
                        <div class="text-lg font-bold text-white">Inactive</div>
                        <div class="text-sm">No game in the last <?= \App\Utility\Activity::ACTIVE_DAYS ?> days &ndash; the CTF rating and the rankings come back with the next game.</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($funFacts) || !empty($timePlayed['all'])): ?>
            <?= $this->element('player_fun_facts', compact('player', 'funFacts', 'timePlayed', 'panel')) ?>
        <?php endif; ?>
    </div>

    <!-- Tabs: the rest of the page in three parts (#overview, #awards, #games) -->
    <?php $tabs = ['overview' => ['fa-chart-simple', 'Overview'], 'awards' => ['fa-medal', 'Milestones & awards'], 'games' => ['fa-gamepad', 'Games']]; ?>
    <nav class="mb-5 flex flex-wrap gap-1 rounded-xl border border-white/15 bg-black/60 p-1" role="tablist">
        <?php foreach ($tabs as $key => [$tabIcon, $tabLabel]): ?>
            <a href="#<?= $key ?>" data-tab="<?= $key ?>" role="tab"
               class="player-tab flex shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-zinc-400 transition hover:text-white">
                <i class="fa-solid <?= $tabIcon ?> text-xs"></i><?= $tabLabel ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div data-tab-panel="overview">
    <!-- Row 1: personal records / nemesis / prey -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <?php
        $recordStyle = [
            'kills' => ['icon' => 'fa-skull', 'color' => 'text-green-400'],
            'headshot' => ['icon' => 'fa-crosshairs', 'color' => 'text-red-400'],
            'scored_with_the_flag' => ['icon' => 'fa-trophy', 'color' => 'text-red-400'],
            'longest_streak' => ['icon' => 'fa-fire', 'color' => 'text-orange-400'],
            // no knife in the free Font Awesome: our own icon, tinted via a mask
            'slashed' => ['img' => '/img/achievements/slashed.svg', 'color' => 'text-purple-400'],
            'gibbed' => ['icon' => 'fa-bomb', 'color' => 'text-yellow-400'],
            'flag_helper' => ['icon' => 'fa-flag', 'color' => 'text-blue-400'],
        ];
        $rankBadge = fn(int $rank) => match (true) {
            $rank === 1 => 'bg-yellow-400 text-black',
            $rank === 2 => 'bg-zinc-300 text-black',
            $rank === 3 => 'bg-amber-700 text-white',
            $rank <= 10 => 'bg-white/15 text-white',
            default => 'bg-white/5 text-zinc-400',
        };
        ?>
        <section class="<?= $panel ?> p-5">
            <div class="mb-3">
                <h3 class="text-lg font-bold">🏆 Personal records</h3>
                <a href="/players/hall_of_fame" class="text-xs text-zinc-400 hover:text-blue-300">Hall of Fame &rarr;</a>
            </div>
            <?php if (!empty($records)): ?>
                <div class="space-y-1.5">
                    <?php foreach ($records as $key => $rec): $st = $recordStyle[$key] ?? ['icon' => 'fa-circle-dot', 'color' => 'text-white']; ?>
                        <a href="<?= $rec['game_id'] ? '/games/view/' . h($rec['game_id']) : '#' ?>"
                           class="flex items-center gap-3 rounded-lg bg-white/5 px-2 py-1.5 hover:bg-white/10 transition">
                            <?php if (!empty($st['img'])): ?>
                                <span class="inline-block h-4 w-5 shrink-0 bg-current <?= $st['color'] ?>"
                                      style="mask: url('<?= $st['img'] ?>') center / contain no-repeat; -webkit-mask: url('<?= $st['img'] ?>') center / contain no-repeat;"></span>
                            <?php else: ?>
                                <i class="fa-solid <?= $st['icon'] ?> <?= $st['color'] ?> w-5 text-center"></i>
                            <?php endif; ?>
                            <div class="min-w-0 flex-1">
                                <div class="text-[10px] uppercase tracking-wide text-zinc-400"><?= h($rec['title']) ?></div>
                                <div class="truncate text-[11px] text-zinc-500"><?= h((string)$rec['map_name']) ?><?php if ($rec['played_at']): ?> &middot; <?= h($rec['played_at']->format('d M Y')) ?><?php endif; ?></div>
                            </div>
                            <div class="font-mono text-xl font-extrabold"><?= number_format($rec['value']) ?></div>
                            <span class="w-10 rounded-full px-1.5 py-0.5 text-center text-[11px] font-bold <?= $rankBadge((int)$rec['rank']) ?>"
                                  title="rank <?= (int)$rec['rank'] ?> of <?= (int)$rec['players'] ?> players">#<?= (int)$rec['rank'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-zinc-400">No records since <?= date('M Y', strtotime(\App\Controller\PlayersController::HOF_SINCE)) ?> yet.</p>
            <?php endif; ?>
        </section>

        <?php
        $duelCards = [
            ['title' => '💀 Nemesis', 'sub' => 'they kill ' . h($player->name) . ' the most', 'rows' => $nemeses ?? [], 'color' => 'text-red-400'],
            ['title' => '🎯 Prey', 'sub' => h($player->name) . ' kills them the most', 'rows' => $victims ?? [], 'color' => 'text-green-400'],
        ];
        foreach ($duelCards as $card): ?>
            <section class="<?= $panel ?> p-5">
                <h3 class="text-lg font-bold"><?= $card['title'] ?></h3>
                <div class="mb-3 text-xs text-zinc-400"><?= $card['sub'] ?></div>
                <?php if ($card['rows']): ?>
                    <div class="space-y-1.5">
                        <?php foreach ($card['rows'] as $i => $r): ?>
                            <a href="/players/view/<?= h($r['id']) ?>" class="flex items-center gap-3 rounded-lg bg-white/5 px-2 py-1.5 hover:bg-white/10 transition">
                                <span class="w-5 text-right text-sm font-bold text-zinc-400"><?= $i + 1 ?></span>
                                <img src="<?= !empty($r['picture']) ? '/img/players/' . h($r['picture']) : '/img/acl.png' ?>" class="h-7 w-7 rounded-full object-cover" alt="">
                                <div class="min-w-0 flex-1 truncate font-semibold"><?= h($r['name']) ?> <?= $this->Layout->flag($r['country']) ?></div>
                                <div class="<?= $card['color'] ?> font-mono text-lg font-bold"><?= (int)$r['n'] ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-zinc-400">Nothing yet.</p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <!-- the last 100 -->
    <div class="mb-6">
        <section class="<?= $panel ?> p-5">
            <div class="mb-3 flex items-baseline justify-between gap-2">
                <h3 class="text-3xl font-rubik">the last 100</h3>
                <a href="/players/thelast100" class="text-xs text-zinc-400 hover:text-blue-300">ranking &rarr;</a>
            </div>
            <p class="mb-4 text-xs text-zinc-400"><?= $lastGameDateRange['start'] ?> &ndash; <?= $lastGameDateRange['end'] ?></p>
            <?php if ($player->rankTheLast100 > 0):
                $sum = fn(array $fields) => array_sum(array_map(fn($f) => (int)($statSums[$f] ?? 0), $fields));
                $tiles = [
                    ['Rank', '#' . (int)$player->rankTheLast100, 'text-yellow-300'],
                    ['Time played', $this->Layout->duration((int)($timePlayed['last100'] ?? 0)), 'text-white'],
                    ['Points', number_format((float)$totalScore), 'text-sky-300'],
                    ['K/D', number_format($kdRatio, 2), 'text-white'],
                    ['Kills', number_format($totalKills), 'text-white'],
                    ['Deaths', number_format($totalDeaths), 'text-white'],
                    ['Headshots', number_format($sum(['headshot'])), 'text-white'],
                    ['Flags scored', number_format($sum(['scored_with_the_flag'])), 'text-white'],
                    ['Flags stolen', number_format($sum(['stole_the_flag'])), 'text-white'],
                    ['Returned', number_format($sum(['returned_the_flag'])), 'text-white'],
                ];
                ?>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    <?php foreach ($tiles as [$label, $value, $color]): ?>
                        <div class="rounded-lg bg-white/5 p-2.5 text-center">
                            <div class="text-[10px] uppercase tracking-wide text-zinc-400"><?= $label ?></div>
                            <div class="font-mono text-lg font-extrabold leading-tight <?= $color ?>"><?= $value ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-zinc-400">Not part of the last 100 games &ndash; go play!</p>
            <?php endif; ?>
        </section>
    </div>
    </div>

    <div data-tab-panel="awards" hidden>
    <?php $this->start('weekly'); ?>
    <section class="<?= $panel ?> p-5">
        <?php
        $achievements = collection($player->achievements ?? [])->sortBy('week_end', SORT_DESC)->toList();
        // all of them, "best on map" wins included (many players only have those)
        $weekly = $achievements;
        $bestOnMap = count(array_filter($achievements, fn($a) => $a->event_type === 'best_on_map'));
        ?>
        <div class="mb-3 flex items-baseline justify-between gap-2">
            <h3 class="text-lg font-bold">🥇 Weekly achievements</h3>
            <?php if ($bestOnMap): ?>
                <span class="flex items-center gap-1 text-xs text-zinc-300">
                    <img src="/img/achievements/best_on_map.svg" class="h-4 w-4" alt=""> Best on map <?= $bestOnMap ?>&times;
                </span>
            <?php endif; ?>
        </div>
        <?php if ($weekly): ?>
            <div class="nice-scroll max-h-96 space-y-1.5 overflow-y-auto pr-2">
                <?php foreach ($weekly as $a): ?>
                    <div class="flex items-center gap-3 rounded-lg bg-white/5 px-2 py-1.5">
                        <img src="/img/achievements/<?= h($a->event_type) ?>.svg" class="h-6 w-6 shrink-0" alt="">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold">
                                <?= h($achievementLabels[$a->event_type] ?? $a->event_type) ?>
                                <?php if ($a->event_type === 'best_on_map' && !empty($a->map)): ?>
                                    &middot; <?= $this->Html->link(h($this->Layout->cleanMapName($a->map->name)), ['controller' => 'Maps', 'action' => 'index', '?' => ['map' => $a->map->name]], ['class' => 'hover:text-blue-300', 'escape' => false]) ?>
                                <?php endif; ?>
                            </div>
                            <div class="text-[11px] text-zinc-400">week ending <?= date('d M Y', strtotime((string)$a->week_end)) ?></div>
                        </div>
                        <div class="font-mono text-lg font-bold text-orange-300">
                            <?= $a->event_type === 'kd_ratio' ? h($a->count) : number_format(round((float)$a->count)) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-zinc-400">No weekly achievement yet.</p>
        <?php endif; ?>
    </section>
    <?php $this->end(); ?>
    <?php if (!empty($milestoneProgress)): ?>
        <?= $this->element('player_milestones', compact('player', 'milestones', 'milestoneProgress', 'panel')) ?>
    <?php else: ?>
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2"><?= $this->fetch('weekly') ?></div>
    <?php endif; ?>
    </div>

    <div data-tab-panel="games" hidden>
    <!-- Form chart: points (bars) and K/D (line) per game, oldest to newest; a click opens the game below -->
    <section class="<?= $panel ?> mb-6 p-5">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-lg font-bold">Your form, game by game</h2>
            <span class="text-xs text-zinc-400">click a name to show or hide it &middot; click a game to open it below</span>
        </div>
        <div class="relative h-72 md:h-80"><canvas id="formChart"></canvas></div>
    </section>

    <!-- Recent games: one game at a time, like the game pages -->
    <?php
    $recent = [];
    foreach ($player->player_stats_per_game as $stat) {
        $recent[] = ['id' => $stat->game->id, 'map' => $stat->game->map->name, 'date' => $stat->game->started_at->format('D, d M Y H:i')];
    }
    ?>
    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-2xl font-bold drop-shadow"><?= h($player->name) ?> recent games</h2>
        <span id="pg-info" class="text-sm text-zinc-300"></span>
    </div>
    <?php if ($recent): ?>
        <div id="pg-box" class="relative overflow-hidden rounded-xl border border-white/15 bg-black/40 select-none">
            <div id="pg-card" class="min-h-[560px]"></div>
            <button type="button" id="pg-prev" aria-label="Previous game" title="Previous (newer) game"
                    class="absolute top-4 right-16 md:right-auto md:top-[256px] md:left-2 z-20 h-11 w-11 flex items-center justify-center rounded-full bg-black/60 hover:bg-black/80 text-white transition disabled:opacity-30">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button type="button" id="pg-next" aria-label="Next game" title="Next (older) game"
                    class="absolute top-4 right-2 md:top-[256px] z-20 h-11 w-11 flex items-center justify-center rounded-full bg-black/60 hover:bg-black/80 text-white transition disabled:opacity-30">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    <?php else: ?>
        <p class="rounded-xl border border-white/15 bg-black/60 p-5 text-zinc-400">No games yet.</p>
    <?php endif; ?>
    </div>

</div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

    const gamesData = <?= json_encode($gamesData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // one chart: points as bars (left axis), K/D as a line (right axis)
    const formGames = gamesData.slice().reverse(); // oldest first
    new Chart(document.getElementById('formChart'), {
        data: {
            labels: formGames.map(g => g.played_at),
            datasets: [{
                type: 'line',
                label: 'K/D',
                data: formGames.map(g => g.kd_ratio),
                yAxisID: 'kd',
                borderColor: '#fb923c',
                backgroundColor: '#fb923c',
                pointRadius: 2,
                tension: 0.25,
                order: 0
            }, {
                type: 'line',
                label: 'CTF rating',
                data: formGames.map(g => g.rating),
                yAxisID: 'rating',
                borderColor: '#4ade80',
                backgroundColor: '#4ade80',
                pointRadius: 2,
                tension: 0.25,
                spanGaps: true, // no rating in non-CTF or short games
                order: 0
            }, {
                type: 'line',
                label: 'Kills',
                data: formGames.map(g => g.kills),
                yAxisID: 'points',
                borderColor: '#e4e4e7',
                backgroundColor: '#e4e4e7',
                borderWidth: 1.5,
                pointRadius: 0,
                tension: 0.25,
                hidden: true, // off at first: click "Kills" in the legend
                order: 0
            }, {
                type: 'line',
                label: 'Flags scored',
                data: formGames.map(g => g.flags || null),
                yAxisID: 'flags',
                showLine: false,
                pointStyle: 'triangle',
                pointRadius: 5,
                borderColor: '#f87171',
                backgroundColor: '#f87171',
                hidden: true,
                order: 0
            }, {
                type: 'bar',
                label: 'Points',
                data: formGames.map(g => g.score),
                yAxisID: 'points',
                backgroundColor: 'rgba(125,211,252,0.45)',
                hoverBackgroundColor: 'rgba(125,211,252,0.8)',
                order: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {mode: 'index', intersect: false},
            plugins: {
                legend: {labels: {color: '#d4d4d8', usePointStyle: true, pointStyleWidth: 14, boxHeight: 8}},
                tooltip: {callbacks: {title: items => formGames[items[0].dataIndex].map_name + ' · ' + items[0].label}}
            },
            scales: {
                x: {ticks: {color: '#a1a1aa', maxRotation: 0, autoSkip: true, maxTicksLimit: 8}, grid: {display: false}},
                points: {position: 'left', beginAtZero: true, ticks: {color: '#7dd3fc'}, grid: {color: 'rgba(255,255,255,.06)'},
                    title: {display: true, text: 'points · kills', color: '#7dd3fc'}},
                kd: {position: 'right', beginAtZero: true, ticks: {color: '#fb923c'}, grid: {display: false},
                    title: {display: true, text: 'K/D', color: '#fb923c'}},
                // flags: own hidden axis, kept in the lower half; the tooltip has the numbers
                flags: {display: false, min: 0, suggestedMax: Math.max(4, ...formGames.map(g => g.flags || 0)) * 2},
                rating: {position: 'right', min: 0, max: 10, ticks: {color: '#4ade80', stepSize: 2}, grid: {display: false},
                    title: {display: true, text: 'rating', color: '#4ade80'}}
            },
            onClick: (e, items, chart) => {
                const hit = chart.getElementsAtEventForMode(e, 'index', {intersect: false}, false)[0];
                if (hit && window.showRecentGame) window.showRecentGame(formGames[hit.index].game_id);
            },
            onHover: (e, items) => { e.native.target.style.cursor = items.length ? 'pointer' : 'default'; }
        }
    });

</script>

<?php if (!empty($recent)): ?>
<script>
(function () {
    const games = <?= json_encode($recent, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; // newest first
    const playerId = <?= json_encode((string)$player->id, JSON_HEX_TAG) ?>;
    const box = document.getElementById('pg-box');
    const card = document.getElementById('pg-card');
    const info = document.getElementById('pg-info');
    // list is newest first: right / next = the next game in the list (older)
    const prevBtn = document.getElementById('pg-prev'); // back towards the newest
    const nextBtn = document.getElementById('pg-next'); // next in the list
    const cache = new Map();
    let index = 0;

    const load = (i) => {
        const g = games[i];
        if (!g) return Promise.resolve('');
        if (!cache.has(g.id)) {
            cache.set(g.id, fetch('/games/card/' + encodeURIComponent(g.id) + '?player=' + encodeURIComponent(playerId))
                .then(r => r.ok ? r.text() : '<p class="p-6 text-zinc-400">Could not load this game.</p>'));
        }
        return cache.get(g.id);
    };

    async function show(i) {
        index = Math.max(0, Math.min(games.length - 1, i));
        const g = games[index];
        info.textContent = 'game ' + (index + 1) + ' / ' + games.length + ' · ' + g.date;
        prevBtn.disabled = index <= 0;
        nextBtn.disabled = index >= games.length - 1;
        const html = await load(index);
        if (games[index] === g) {
            card.innerHTML = html;
            // never shrink while browsing, so the page and the arrows stay put
            card.style.minHeight = Math.max(card.offsetHeight, parseFloat(card.style.minHeight) || 0) + 'px';
        }
        load(index + 1); load(index - 1); // preload neighbours
    }

    // the form chart opens a game here
    window.showRecentGame = (id) => {
        const i = games.findIndex(g => g.id === id);
        if (i < 0) return;
        show(i);
        box.scrollIntoView({behavior: 'smooth', block: 'start'});
    };

    prevBtn.addEventListener('click', () => show(index - 1));
    nextBtn.addEventListener('click', () => show(index + 1));
    document.addEventListener('keydown', (e) => {
        if (e.target.closest('input, textarea, select') || box.offsetParent === null) return; // only on the Games tab
        if (e.key === 'ArrowLeft') show(index - 1);
        else if (e.key === 'ArrowRight') show(index + 1);
    });
    let sx = 0, sy = 0;
    box.addEventListener('touchstart', (e) => { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, {passive: true});
    box.addEventListener('touchend', (e) => {
        const dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) show(index + (dx < 0 ? 1 : -1)); // swipe left = next
    }, {passive: true});

    show(0);
})();
</script>
<?php endif; ?>

<script>
// Tabs: #overview (default), #awards (#milestones too), #games - the hash keeps the tab on reload and in shared links
(function () {
    const tabs = document.querySelectorAll('.player-tab');
    const panels = document.querySelectorAll('[data-tab-panel]');
    const aliases = {milestones: 'awards'};
    function open(name, scroll) {
        name = aliases[name] || name;
        if (![...panels].some(p => p.dataset.tabPanel === name)) name = 'overview';
        panels.forEach(p => p.hidden = p.dataset.tabPanel !== name);
        tabs.forEach(t => {
            const on = t.dataset.tab === name;
            t.classList.toggle('bg-blue-600', on);
            t.classList.toggle('text-white', on);
            t.classList.toggle('text-zinc-400', !on);
            t.setAttribute('aria-selected', on);
        });
        if (scroll) document.querySelector('[role=tablist]').scrollIntoView({block: 'nearest'});
    }
    tabs.forEach(t => t.addEventListener('click', e => {
        e.preventDefault();
        history.replaceState(null, '', '#' + t.dataset.tab);
        open(t.dataset.tab, false);
    }));
    window.addEventListener('hashchange', () => open(location.hash.slice(1), true));
    open(location.hash.slice(1), !!location.hash);
})();
</script>
