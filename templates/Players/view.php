<?php

use Cake\Core\Configure;

?>
<?php
$bgMap = !empty($favoriteMap['name']) && is_file(WWW_ROOT . 'img/maps/' . $favoriteMap['name'] . '.jpg')
    ? '/img/maps/' . rawurlencode($favoriteMap['name']) . '.jpg'
    : '/img/bullet.jpg';
$panel = 'rounded-xl border border-white/15 bg-black/60 backdrop-blur-[2px]';
$achievementLabels = [
    'total_score' => 'Most Points', 'kills' => 'Most Kills', 'teamkills' => 'Most Teamkills',
    'kd_ratio' => 'Best KD Ratio', 'gibbed' => 'Most Gibs', 'suicided' => 'Most Suicided',
    'slashed' => 'Most Slashes', 'scored_with_the_flag' => 'Most Flags scored',
    'headshot' => 'Most Headshots', 'best_on_map' => 'Best on Map', 'games' => 'Most Games Played',
];
?>
<!-- Player page on their most played map (or the bullet) -->
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center bg-fixed text-white"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.55), rgba(0,0,0,.35) 35%, rgba(0,0,0,.8)), url('<?= $bgMap ?>');">
<div class="mx-auto w-full max-w-7xl px-4 py-6 md:px-16 md:py-8">

    <!-- Header -->
    <div class="mb-6 flex flex-wrap items-center gap-5 drop-shadow-lg lg:flex-nowrap">
        <a href="<?= $this->Layout->playerPicture($player) ?>">
            <img src="<?= $this->Layout->playerPicture($player) ?>" alt="<?= h($player->name) ?>"
                 class="h-24 w-24 md:h-32 md:w-32 rounded-full object-cover ring-2 ring-white/30">
        </a>
        <div class="min-w-0 lg:flex-1">
            <h1 class="text-3xl md:text-5xl font-extrabold tracking-wide">
                <?= h($player->name) ?> <?= $this->Layout->flag($player->country) ?>
            </h1>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-200">
                <?php if ($player->rankAllTime > 0): ?>
                    <span><i class="fa-solid fa-ranking-star mr-1"></i>#<?= (int)$player->rankAllTime ?> all time</span>
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
                <?php if ($player->country): ?><span><?= h($player->country) ?></span><?php endif; ?>
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
               class="<?= $panel ?> flex w-full shrink-0 items-center gap-5 p-4 hover:border-white/30 transition sm:ml-auto sm:w-auto">
                <div class="shrink-0 text-center">
                    <div class="font-mono text-5xl font-extrabold tabular-nums leading-none <?= $ratingColor ?>"><?= number_format($ratingValue, 1) ?></div>
                    <div class="mt-1 text-[10px] uppercase tracking-wider text-zinc-400">CTF rating</div>
                </div>
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-sm font-semibold <?= $typeColor ?>">
                            <i class="fa-solid <?= $typeIcon ?> text-xs"></i><?= h($rating->type) ?>
                        </span>
                        <?php if ($rating->weapon !== 'Mixed'): ?>
                            <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs text-zinc-200"><?= h($rating->weapon) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-zinc-300">
                        <span class="underline decoration-dotted decoration-zinc-500 underline-offset-2"
                              title="Place among all <?= number_format((int)$ratedPlayers) ?> players with a CTF rating: 20+ CTF games of 3+ minutes (players who chose &quot;Don't track me&quot; are not rated)">#<?= (int)$rating->rank ?> of <?= number_format((int)$ratedPlayers) ?> rated players</span>
                        <?php if ($rating->win_rate !== null): ?> · <?= round((float)$rating->win_rate * 100) ?>% won<?php endif; ?>
                        · last <?= (int)$rating->games ?> CTF games
                    </div>
                    <div class="grid grid-cols-[4.5rem_1fr] items-center gap-x-2 gap-y-1 text-[10px] uppercase tracking-wide text-zinc-400">
                        <?php foreach (['Attack' => [$rating->attack_pct, 'bg-red-400'], 'Defense' => [$rating->defense_pct, 'bg-blue-400'], 'Combat' => [$rating->combat_pct, 'bg-orange-400']] as $label => [$value, $bar]): ?>
                            <span><?= $label ?></span>
                            <span class="h-1.5 w-24 overflow-hidden rounded-full bg-white/10 sm:w-32" title="better than <?= (int)$value ?>% of rated players">
                                <span class="block h-full rounded-full <?= $bar ?>" style="width: <?= max(2, (int)$value) ?>%"></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </a>
        <?php endif; ?>
    </div>

    <!-- Row 1: personal records / nemesis / prey -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <?php
        $recordStyle = [
            'kills' => ['icon' => 'fa-skull', 'color' => 'text-green-400'],
            'headshot' => ['icon' => 'fa-crosshairs', 'color' => 'text-red-400'],
            'scored_with_the_flag' => ['icon' => 'fa-trophy', 'color' => 'text-red-400'],
            'longest_streak' => ['icon' => 'fa-fire', 'color' => 'text-orange-400'],
            'slashed' => ['icon' => 'fa-knife', 'color' => 'text-purple-400'],
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
                            <i class="fa-solid <?= $st['icon'] ?> <?= $st['color'] ?> w-5 text-center"></i>
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

    <!-- Row 2: weekly achievements / the last 100 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <section class="<?= $panel ?> p-5">
            <?php
            $achievements = collection($player->achievements ?? [])->sortBy('week_end', SORT_DESC)->toList();
            $weekly = array_values(array_filter($achievements, fn($a) => $a->event_type !== 'best_on_map'));
            $bestOnMap = count($achievements) - count($weekly);
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
                                <div class="text-sm font-semibold"><?= h($achievementLabels[$a->event_type] ?? $a->event_type) ?></div>
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

    <?php if (!empty($milestoneProgress)): ?>
        <?= $this->element('player_milestones', compact('player', 'milestones', 'milestoneProgress', 'funFacts', 'timePlayed', 'rating', 'panel')) ?>
    <?php endif; ?>

    <!-- Charts -->

    <div class="mb-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="rounded-xl border border-white/15 bg-black/60 p-5 backdrop-blur-[2px]">
            <h2 class="text-lg font-bold mb-3">Kill / Death Ratio</h2>
            <canvas id="kdChart"></canvas>
        </section>
        <section class="rounded-xl border border-white/15 bg-black/60 p-5 backdrop-blur-[2px]">
            <h2 class="text-lg font-bold mb-3">Points</h2>
            <canvas id="scoreChart"></canvas>
        </section>
    </div>


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
                    class="absolute top-4 right-16 md:right-auto md:top-1/2 md:left-2 md:-translate-y-1/2 z-20 h-11 w-11 flex items-center justify-center rounded-full bg-black/60 hover:bg-black/80 text-white transition disabled:opacity-30">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button type="button" id="pg-next" aria-label="Next game" title="Next (older) game"
                    class="absolute top-4 right-2 md:top-1/2 md:-translate-y-1/2 z-20 h-11 w-11 flex items-center justify-center rounded-full bg-black/60 hover:bg-black/80 text-white transition disabled:opacity-30">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    <?php else: ?>
        <p class="rounded-xl border border-white/15 bg-black/60 p-5 text-zinc-400">No games yet.</p>
    <?php endif; ?>

</div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

    const gamesData = <?= json_encode($gamesData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // K/D chart
    new Chart(document.getElementById('kdChart'), {
        type: 'line',
        data: {
            labels: gamesData.map(g => g.map_name + ' ' + g.played_at),
            datasets: [{
                label: 'K/D Ratio',
                data: gamesData.map(g => g.kd_ratio),
                borderColor: 'rgba(255,255,255,1)',
                backgroundColor: 'rgba(255,255,255,0.2)',
                tension: 0.2
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Score chart
    new Chart(document.getElementById('scoreChart'), {
        type: 'bar',
        data: {
            labels: gamesData.map(g => g.map_name + ' ' + g.played_at),
            datasets: [{
                label: 'Score',
                data: gamesData.map(g => g.score),
                backgroundColor: 'rgba(255,255,255,1)',
                borderColor: 'rgba(255,255,255,0.6)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
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
        if (games[index] === g) card.innerHTML = html;
        load(index + 1); load(index - 1); // preload neighbours
    }

    prevBtn.addEventListener('click', () => show(index - 1));
    nextBtn.addEventListener('click', () => show(index + 1));
    document.addEventListener('keydown', (e) => {
        if (e.target.closest('input, textarea, select')) return;
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
