<?php
use App\Service\HallOfFameService;

/**
 * @var string|null $category the category opened by /players/hall_of_fame/<slug>
 */
// Link preview: the category's card (PreviewsController::hallOfFame), the
// first category on the plain address; a new picture URL when its list changes
$ogSite = rtrim((string)\Cake\Core\Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh', '/');
$ogKey = $category ?? 'kills';
$ogCat = HallOfFameService::CATEGORIES[$ogKey];
$ogList = HallOfFameService::rows((new HallOfFameService())->topLists()[$ogKey] ?? []);
$ogTitle = $category ? 'Hall of Fame · ' . $ogCat['title'] . ' · cubeLadder' : 'Hall of Fame · cubeLadder';
$ogText = $ogList
    ? sprintf('#1 %s with %s %s in one game. The best single games on the AssaultCube servers - straight from the logs.',
        $ogList[0]['name'], number_format($ogList[0]['value']), $ogCat['unit'])
    : 'The best single games on the AssaultCube servers - straight from the logs.';
$this->assign('title', $ogTitle);
$this->start('meta'); ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="cubeLadder">
    <meta property="og:title" content="<?= h($ogTitle) ?>">
    <meta property="og:description" content="<?= h($ogText) ?>">
    <meta property="og:url" content="<?= h($ogSite . '/players/hall_of_fame' . ($category ? '/' . $ogCat['slug'] : '')) ?>">
    <meta property="og:image" content="<?= h($ogSite . '/previews/hall_of_fame/' . $ogCat['slug'] . '?v=' . substr(md5((string)json_encode($ogList)), 0, 8)) ?>">
    <meta property="og:image:width" content="<?= \App\Service\HallOfFameImage::WIDTH ?>">
    <meta property="og:image:height" content="<?= \App\Service\HallOfFameImage::HEIGHT ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="description" content="<?= h($ogText) ?>">
<?php $this->end(); ?>
<div class="w-full">

<h1 class="sr-only">Hall of Fame</h1>

<?php
$genres = [
    [
        'key'   => 'kills',
        'title' => 'Most Kills',
        'color' => 'text-green-400',
        'data'  => $topKills,
    ],
    [
        'key'   => 'headshot',
        'title' => 'Most Headshots',
        'color' => 'text-red-400',
        'data'  => $topHeadshots,
    ],
    [
        'key'   => 'flags',
        'title' => 'Most Flags scored',
        'color' => 'text-red-400',
        'data'  => $topFlags,
    ],
    [
        'key'   => 'streak',
        'title' => 'Longest Streak',
        'color' => 'text-orange-400',
        'data'  => $topStreaks,
    ],
    [
        'key'   => 'slashed',
        'title' => 'Most Slashes',
        'color' => 'text-purple-400',
        'data'  => $topSlashes,
    ],
    [
        'key'   => 'gibbed',
        'title' => 'Most Gibbed',
        'color' => 'text-yellow-400',
        'data'  => $topGibbed,
    ],
    [
        'key'   => 'helper',
        'title' => 'Flag Helpers',
        'color' => 'text-blue-400',
        'data'  => $bestFlagHelpers,
    ],
];

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

// Only categories that actually have players.
$slides = array_values(array_filter($genres, fn($g) => !empty($g['data'])));
$slideCount = count($slides);

// Random player photos used as the slide backgrounds (one per slide)
$playerImages = array_map('basename', glob(WWW_ROOT . 'img/players/*.jpg'));
shuffle($playerImages);
?>

<?php if ($slideCount === 0): ?>
    <p class="text-center text-zinc-500">Nothing to show yet.</p>
<?php else: ?>

<div id="hof-slideshow"
     class="relative select-none overflow-hidden bg-zinc-900 text-white"
     data-count="<?= $slideCount ?>">

    <!-- Track -->
    <div id="hof-track"
         class="flex transition-transform duration-300 ease-out"
         style="transform: translateX(0%);">

        <?php $slideIdx = 0; foreach ($slides as $genre): ?>
            <?php
            $bgImg = !empty($playerImages)
                ? '/img/players/' . $playerImages[$slideIdx % count($playerImages)]
                : '/img/bullet-full.jpg';
            $slideIdx++;
            ?>
            <!-- Slide: full screen, player photo as background -->
            <div class="hof-slide relative w-full flex-shrink-0 bg-cover bg-center"
                 data-slug="<?= h(HallOfFameService::CATEGORIES[$genre['key']]['slug']) ?>" data-key="<?= h($genre['key']) ?>"
                 data-title="<?= h(HallOfFameService::CATEGORIES[$genre['key']]['title']) ?>"
                 style="background-image: linear-gradient(to right, rgba(0,0,0,.9), rgba(0,0,0,.7) 55%, rgba(0,0,0,.8)), url('<?= h($bgImg) ?>');">
                <div class="mx-auto flex min-h-[calc(100svh-4rem)] w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-16 md:py-10 lg:flex-row lg:items-center lg:justify-between">

                    <!-- Category -->
                    <div class="drop-shadow-lg lg:max-w-[45%] pr-24 md:pr-0">
                        <div class="text-xs md:text-sm uppercase tracking-[0.3em] text-zinc-300">
                            Hall of Fame &middot; <?= $slideIdx ?>/<?= $slideCount ?>
                        </div>
                        <h2 class="mt-2 text-5xl md:text-7xl font-extrabold leading-tight <?= $genre['color'] ?>">
                            <?= $genre['title'] ?>
                        </h2>
                        <div class="mt-3 text-sm text-zinc-300">Best single game since <?= date('M Y', strtotime(\App\Controller\PlayersController::HOF_SINCE)) ?></div>
                        <button type="button" class="js-hof-share mt-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-black/50 px-4 py-2 text-sm text-white transition hover:bg-black/70">
                            <i class="fa-solid fa-share-nodes"></i> <span>Share</span>
                        </button>
                    </div>

                    <!-- Players -->
                    <div class="w-full lg:w-[460px] shrink-0 rounded-lg border border-white/15 bg-black/60 p-4 md:p-5 backdrop-blur-[2px]">
                        <div class="divide-y divide-white/10">
                            <?php $rank = 1; foreach ($genre['data'] as $player): ?>
                                <?php
                                // Weapons used in the record-setting game
                                $recordStats = [
                                    'kills'      => (int)($player->kills ?? 0),
                                    'headshot'   => (int)($player->headshot ?? 0),
                                    'shredded'   => (int)($player->shredded ?? 0),
                                    'peppered'   => (int)($player->peppered ?? 0),
                                    'sprayed'    => (int)($player->sprayed ?? 0),
                                    'punctured'  => (int)($player->punctured ?? 0),
                                    'splattered' => (int)($player->splattered ?? 0),
                                    'slashed'    => (int)($player->slashed ?? 0),
                                    'gibbed'     => (int)($player->gibbed ?? 0),
                                    'picked_off' => (int)($player->picked_off ?? 0),
                                    'busted'     => (int)($player->busted ?? 0),
                                ];
                                $weapons = $this->Layout->weapon($recordStats);
                                $medals = $achievementPlayers[$player->player_id] ?? [];
                                ?>
                                <div class="flex items-start gap-3 py-2">

                                    <div class="w-6 text-center font-bold pt-1
                                                <?= $rank <= 3 ? 'text-yellow-400' : 'text-zinc-500' ?>">
                                        <?= $rank++ ?>
                                    </div>

                                    <img
                                        src="<?= $this->Layout->playerPicture($player) ?>"
                                        class="w-11 h-11 rounded-full object-cover shrink-0"
                                        alt="pic"
                                    >

                                    <div class="flex-1 min-w-0">
                                        <!-- Name with the record-game weapon(s) right next to it -->
                                        <div class="flex items-center flex-wrap gap-x-2 gap-y-0.5">
                                            <span class="font-semibold truncate">
                                                <?= $this->Html->link($player->name, ['controller' => 'Players', 'action' => 'view', $player->player_id], ['class' => 'hover:text-blue-400', 'data-hover-card' => '/players/hover-card/' . $player->player_id]) ?>
                                                <?= $this->Layout->flag($player->country) ?>
                                            </span>
                                            <?php if ($ch = $playerRatings[(string)$player->player_id] ?? null): ?>
                                                <?php if (!empty($ch['archetype'])): ?>
                                                    <?= $this->element('archetype_badge', ['archetype' => $ch['archetype'], 'pct' => $ch['archetype_pct'] !== null ? (int)$ch['archetype_pct'] : null, 'rank' => $ch['archetype_rank'] !== null ? (int)$ch['archetype_rank'] : null, 'compact' => true]) ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (isset($inactivePlayers[(string)$player->player_id])): ?>
                                                <!-- no game in the last Activity::ACTIVE_DAYS: the record stays -->
                                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-white/10 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-400"
                                                      title="Inactive: no game in the last <?= \App\Utility\Activity::ACTIVE_DAYS ?> days">
                                                    <i class="fa-solid fa-moon"></i>inactive
                                                </span>
                                            <?php endif; ?>
                                            <!-- last week's best, as in the rankings -->
                                            <?php foreach ($medals as $ach => $count): ?>
                                                <?= $this->element('achievement_badge', ['event' => $ach, 'title' => $achievementLabels[$ach] ?? null]) ?>
                                            <?php endforeach; ?>
                                            <?php if (!empty($weapons['weapons'])): ?>
                                                <span class="flex items-center gap-1 shrink-0">
                                                    <?php foreach ($weapons['weapons'] as $weapon): ?>
                                                        <img src="/img/weapons/<?= $weapon ?>.svg"
                                                             title="<?= h($weapon) ?>" class="w-4 h-4">
                                                    <?php endforeach; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex items-center flex-wrap gap-x-2 gap-y-1 text-xs text-zinc-400">
                                            <?php if ($pr = $playerRatings[(string)$player->player_id] ?? null): ?>
                                                <!-- the player's CTF rating, rank among rated players and type -->
                                                <span class="whitespace-nowrap" title="CTF rating · rank among <?= h('rated players') ?>">
                                                    <b class="font-mono <?= $this->Layout->ratingClass((float)$pr['rating']) ?>"><?= number_format((float)$pr['rating'], 1) ?></b><?= $this->Layout->trendArrow(isset($pr['trend']) ? (float)$pr['trend'] : null, 'ml-0.5 text-[9px]') ?>
                                                    <span class="text-zinc-500">#<?= (int)$pr['rank'] ?></span>
                                                </span>
                                                <?= $this->element('player_type_badge', ['type' => $pr['type'], 'label' => $pr['label']]) ?>
                                            <?php endif; ?>
                                        </div>

                                    </div>

                                    <div class="text-right shrink-0">
                                        <div class="text-blue-500 font-mono text-lg leading-none">
                                            <?= number_format($player->value) ?>
                                        </div>
                                        <div class="text-[10px] text-zinc-500 whitespace-nowrap mt-1">
                                            <?= $player->played_at->format('d M Y') ?>
                                        </div>
                                        <!-- the record game's map, under its date -->
                                        <a href="<?= $this->Url->build(['controller' => 'Games', 'action' => 'view', $player->game_id]) ?>"
                                           class="mt-0.5 ml-auto block max-w-[5.5rem] truncate text-[10px] text-blue-500 hover:underline sm:max-w-[7rem]" title="<?= h($player->map_name) ?>">
                                            <?= h($player->map_name) ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- Prev / Next arrows -->
    <button type="button" id="hof-prev" aria-label="Previous category"
            class="absolute top-4 right-16 md:right-auto md:top-1/2 md:left-2 md:-translate-y-1/2 z-20 h-11 w-11 flex items-center justify-center rounded-full
                   bg-black/50 hover:bg-black/70 text-white transition">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button type="button" id="hof-next" aria-label="Next category"
            class="absolute top-4 right-2 md:top-1/2 md:-translate-y-1/2 z-20 h-11 w-11 flex items-center justify-center rounded-full
                   bg-black/50 hover:bg-black/70 text-white transition">
        <i class="fas fa-chevron-right"></i>
    </button>
</div>

<?php endif; ?>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const root = document.getElementById('hof-slideshow');
    if (!root) return;

    const track   = document.getElementById('hof-track');
    const slides   = Array.from(track.children);
    const count   = slides.length;
    // the category of the address (/players/hall_of_fame/<slug>, also #slug or #key)
    const want = <?= json_encode($category ? HallOfFameService::CATEGORIES[$category]['slug'] : null) ?> || decodeURIComponent(location.hash.slice(1));
    let index = Math.max(0, slides.findIndex(s => s.dataset.slug === want || s.dataset.key === want));
    const urlOf = (s) => location.origin + '/players/hall_of_fame/' + s.dataset.slug;

    function render() {
        track.style.transform = 'translateX(' + (-index * 100) + '%)';
        // the address and the title follow the slide, so a copied link opens it
        const s = slides[index];
        if (location.pathname + location.hash !== '/players/hall_of_fame/' + s.dataset.slug) {
            history.replaceState(null, '', urlOf(s));
        }
        document.title = 'Hall of Fame · ' + s.dataset.title + ' · cubeLadder';
    }

    // share: the phone's share sheet, else copy the link
    root.querySelectorAll('.js-hof-share').forEach(btn => btn.addEventListener('click', async () => {
        const s = btn.closest('.hof-slide');
        const url = urlOf(s), title = 'Hall of Fame · ' + s.dataset.title;
        const label = btn.querySelector('span');
        try {
            if (navigator.share) { await navigator.share({title, url}); return; }
            await navigator.clipboard.writeText(url);
            label.textContent = 'Link copied';
        } catch (e) {
            if (e && e.name === 'AbortError') return;
            label.textContent = url;
        }
        setTimeout(() => { label.textContent = 'Share'; }, 2500);
    }));

    function go(i) {
        index = (i + count) % count; // wrap around
        render();
    }
    const next = () => go(index + 1);
    const prev = () => go(index - 1);

    document.getElementById('hof-next').addEventListener('click', next);
    document.getElementById('hof-prev').addEventListener('click', prev);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') next();
        else if (e.key === 'ArrowLeft') prev();
    });

    // Touch swipe (mobile)
    let startX = 0, startY = 0, tracking = false;
    root.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        tracking = true;
    }, { passive: true });

    root.addEventListener('touchend', (e) => {
        if (!tracking) return;
        tracking = false;
        const dx = e.changedTouches[0].clientX - startX;
        const dy = e.changedTouches[0].clientY - startY;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
            dx < 0 ? next() : prev();
        }
    }, { passive: true });

    render();
})();
</script>
<!-- the player card on hovering a name, as in the rankings -->
<?= $this->element('hover_card_script') ?>
<?php $this->end(); ?>
