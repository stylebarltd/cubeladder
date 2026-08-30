<div class="w-full max-w-7xl px-0 sm:px-6 py-10 mx-auto">

<h1 class="text-4xl font-bold text-white mb-8 text-center px-4 sm:px-0">
    Hall of Fame
</h1>

<?php
$genres = [
    [
        'title' => 'Most Kills',
        'color' => 'text-green-400',
        'data'  => $topKills,
    ],
    [
        'title' => 'Most Headshots',
        'color' => 'text-red-400',
        'data'  => $topHeadshots,
    ],
    [
        'title' => 'Most Flags scored',
        'color' => 'text-red-400',
        'data'  => $topFlags,
    ],
    [
        'title' => 'Longest Streak',
        'color' => 'text-orange-400',
        'data'  => $topStreaks,
    ],
    [
        'title' => 'Most Slashes',
        'color' => 'text-purple-400',
        'data'  => $topSlashes,
    ],
    [
        'title' => 'Most Gibbed',
        'color' => 'text-yellow-400',
        'data'  => $topGibbed,
    ],
    [
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

// Only categories that actually have players, so slide indexes line up with dots.
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
     class="relative select-none sm:rounded-xl border-y sm:border border-zinc-700 bg-zinc-800 overflow-hidden"
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
            <!-- Slide -->
            <div class="hof-slide w-full flex-shrink-0 flex">
                <div class="flex flex-col lg:flex-row w-full">

                    <!-- Left: random player photo + category title -->
                    <div class="relative w-full lg:w-[65%] aspect-video lg:aspect-auto"
                         style="background-image:
                             linear-gradient(to top, rgba(0,0,0,0.9), rgba(0,0,0,0.4), rgba(0,0,0,0.15)),
                             url('<?= h($bgImg) ?>');
                             background-size: cover;
                             background-position: center;
                             background-repeat: no-repeat;">
                        <div class="absolute inset-0 flex items-center justify-center p-5 md:p-8">
                            <h3 class="text-5xl md:text-7xl font-bold drop-shadow text-center leading-tight <?= $genre['color'] ?>">
                                <?= $genre['title'] ?>
                            </h3>
                        </div>
                    </div>

                    <!-- Right: players -->
                    <div class="w-full lg:w-[35%] p-4 md:p-6">
                        <div class="divide-y divide-zinc-700/60">
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
                                                <?= $this->Html->link($player->name, ['controller' => 'Players', 'action' => 'view', $player->player_id], ['class' => 'hover:text-blue-400']) ?>
                                                <?= $this->Layout->flag($player->country) ?>
                                            </span>
                                            <?php if (!empty($weapons['weapons'])): ?>
                                                <span class="flex items-center gap-1 shrink-0">
                                                    <?php foreach ($weapons['weapons'] as $weapon): ?>
                                                        <img src="/img/weapons/<?= $weapon ?>.svg"
                                                             title="<?= h($weapon) ?>" class="w-4 h-4">
                                                    <?php endforeach; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="text-xs text-zinc-400 truncate">
                                            <a href="<?= $this->Url->build([
                                                'controller' => 'Games',
                                                'action' => 'view',
                                                $player->game_id
                                            ]) ?>"
                                               class="hover:underline text-blue-500">
                                                <?= h($player->map_name) ?>
                                            </a>
                                        </div>

                                        <!-- Achievements -->
                                        <?php if (!empty($medals)): ?>
                                            <div class="flex items-center flex-wrap gap-x-2 gap-y-1 mt-1.5">
                                                <span class="flex items-center gap-1">
                                                    <?php foreach ($medals as $ach => $count): ?>
                                                        <img src="/img/achievements/<?= $ach ?>.svg"
                                                             alt="<?= h($achievementLabels[$ach] ?? $ach) ?>"
                                                             title="<?= h($achievementLabels[$ach] ?? $ach) ?>"
                                                             class="w-5 h-5">
                                                    <?php endforeach; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-right shrink-0">
                                        <div class="text-blue-500 font-mono text-lg leading-none">
                                            <?= number_format($player->value) ?>
                                        </div>
                                        <div class="text-[10px] text-zinc-500 whitespace-nowrap mt-1">
                                            <?= $player->played_at->format('d M Y') ?>
                                        </div>
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
            class="absolute top-1/2 left-2 -translate-y-1/2 z-20
                   h-10 w-10 flex items-center justify-center rounded-full
                   bg-black/50 hover:bg-black/70 text-white transition">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button type="button" id="hof-next" aria-label="Next category"
            class="absolute top-1/2 right-2 -translate-y-1/2 z-20
                   h-10 w-10 flex items-center justify-center rounded-full
                   bg-black/50 hover:bg-black/70 text-white transition">
        <i class="fas fa-chevron-right"></i>
    </button>
</div>

<!-- Dots + counter -->
<div class="flex flex-col items-center gap-2 mt-4">
    <div id="hof-dots" class="flex flex-wrap justify-center gap-2 max-w-full"></div>
    <div class="text-xs text-zinc-500">
        <span id="hof-counter">1</span> / <?= $slideCount ?>
    </div>
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
    const dotsBox  = document.getElementById('hof-dots');
    const counter  = document.getElementById('hof-counter');
    let index = 0;

    const dots = slides.map((_, i) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('aria-label', 'Go to category ' + (i + 1));
        dot.className = 'h-2.5 w-2.5 rounded-full bg-zinc-600 hover:bg-zinc-400 transition';
        dot.addEventListener('click', () => go(i));
        dotsBox.appendChild(dot);
        return dot;
    });

    function render() {
        track.style.transform = 'translateX(' + (-index * 100) + '%)';
        counter.textContent = index + 1;
        dots.forEach((d, i) => {
            d.classList.toggle('bg-blue-500', i === index);
            d.classList.toggle('bg-zinc-600', i !== index);
        });
    }

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
<?php $this->end(); ?>
