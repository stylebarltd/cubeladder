<?php

use Cake\Core\Configure;

$achievementLabels = [
    'total_score' => 'Points',
    'kills' => 'Kills',
    'teamkills' => 'Teamkills',
    'kd_ratio' => 'KD Ratio',
    'gibbed' => 'Gibs',
    'slashed' => 'Slashes',
    'scored_with_the_flag' => 'Flags scored',
    'headshot' => 'Headshots',
    'suicided' => 'Suicides',
];

// Per-game stat columns shown for each player (key => label + formatter)
$statCols = [
    'total_score'          => ['label' => 'Points', 'fmt' => fn($v) => number_format((int)$v)],
    'kd_ratio'             => ['label' => 'KDR',    'fmt' => fn($v) => number_format((float)$v, 2)],
    'kills'                => ['label' => 'Kills',  'fmt' => fn($v) => number_format((int)$v)],
    'scored_with_the_flag' => ['label' => 'Flags',  'fmt' => fn($v) => number_format((int)$v)],
];

$slideCount = count($games);
?>
<div class="w-full max-w-7xl px-0 sm:px-6 py-10 mx-auto">

    <h1 class="text-4xl font-bold text-white mb-6 text-center px-4 sm:px-0">
        Recent games
    </h1>

    <?php if ($slideCount === 0): ?>
        <p class="text-center text-zinc-500">No games to show yet.</p>
    <?php else: ?>

    <div id="game-slideshow"
         class="relative select-none sm:rounded-xl border-y sm:border border-zinc-700 bg-zinc-800 overflow-hidden"
         data-count="<?= $slideCount ?>">

        <!-- Track -->
        <div id="game-track"
             class="flex transition-transform duration-300 ease-out"
             style="transform: translateX(0%);">

            <?php foreach ($games as $game): ?>
                <?php
                $mapImage = WWW_ROOT . 'img/maps/' . $game->map->name . '.jpg';
                $mapUrl = file_exists($mapImage)
                    ? '/img/maps/' . h($game->map->name) . '.jpg'
                    : '/img/maps/placeholder.jpg';
                ?>

                <!-- Slide -->
                <div class="game-slide w-full flex-shrink-0 flex">
                    <div class="flex flex-col lg:flex-row w-full">

                        <!-- Left: map image + date / server / type / map name -->
                        <div class="relative w-full lg:w-[65%] aspect-video lg:aspect-auto"
                             style="background-image:
                                 linear-gradient(to top, rgba(0,0,0,0.92), rgba(0,0,0,0.45), rgba(0,0,0,0.15)),
                                 url('<?= $mapUrl ?>');
                                 background-size: cover;
                                 background-position: center;
                                 background-repeat: no-repeat;">
                            <div class="absolute bottom-0 left-0 right-0 p-5 md:p-6">
                                <div class="font-bold text-2xl md:text-3xl text-white drop-shadow leading-tight">
                                    <?= $this->Layout->cleanMapName($game->map->name) ?>
                                </div>

                                <div class="mt-2 space-y-1 text-sm text-zinc-200 drop-shadow">
                                    <div>
                                        <i class="far fa-calendar w-4 text-center"></i>
                                        <?= $game->started_at->format('D, dS M Y') ?>
                                    </div>
                                    <div>
                                        <i class="far fa-clock w-4 text-center"></i>
                                        <?= $game->started_at->format('H:i') ?>
                                        <?php if (!empty($game->ended_at)): ?>
                                            &ndash; <?= $game->ended_at->format('H:i') ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <i class="fa-solid fa-server w-4 text-center"></i>
                                        <?= $this->Layout->serverName($game->server_name) ?>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <?= $this->Layout->gameModeIcon($game->mode) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: players -->
                        <div class="w-full lg:w-[35%] p-4 md:p-6">
                            <div class="flex items-center justify-between mb-3">
                                <h2 class="text-xs uppercase tracking-wide text-zinc-500">
                                    Players
                                </h2>
                                <?= $this->Html->link(
                                    'More details →',
                                    ['action' => 'view', $game->id],
                                    ['class' => 'text-xs text-blue-400 hover:text-blue-300']
                                ) ?>
                            </div>

                            <div class="divide-y divide-zinc-700/60 max-h-[70vh] overflow-y-auto scrollbar-hide">
                                <?php $rank = 1; foreach ($game->players as $player): ?>
                                    <?php
                                    $stats = $player->PlayerStatsPerGame;

                                    // "Most X" titles this player leads in this game
                                    $leaderLabels = [];
                                    foreach ($achievementLabels as $stat => $lbl) {
                                        if (($game->stat_leaders[$stat] ?? null) === $player->id) {
                                            $leaderLabels[] = $lbl;
                                        }
                                    }
                                    ?>
                                    <div class="py-2">
                                        <!-- Name with the "leads in" achievements sitting right next to it -->
                                        <div class="flex items-start gap-2">
                                            <span class="w-5 text-right text-zinc-500 font-bold shrink-0 text-sm">
                                                <?= $rank++ ?>
                                            </span>
                                            <div class="flex-1 min-w-0 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                <span class="truncate font-semibold text-sm">
                                                    <?= $this->Html->link(
                                                        h($player->name),
                                                        ['controller' => 'Players', 'action' => 'view', $player->id],
                                                        ['class' => 'hover:text-blue-400', 'escape' => false]
                                                    ) ?>
                                                    <?= $this->Layout->flag($player->country) ?>
                                                </span>
                                                <?php if (!empty($leaderLabels)): ?>
                                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-yellow-400">
                                                        <?php foreach ($leaderLabels as $lbl): ?>
                                                            <span>🏆 <?= h($lbl) ?></span>
                                                        <?php endforeach; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Stat columns: Points / KDR / Kills / Flags at all sizes -->
                                        <div class="pl-7 mt-1.5 grid grid-cols-4 gap-1">
                                            <?php foreach ($statCols as $key => $def):
                                                $isLeader = ($game->stat_leaders[$key] ?? null) === $player->id; ?>
                                                <div class="rounded px-1 py-0.5 text-center
                                                    <?= $isLeader ? 'bg-blue-500/15 ring-1 ring-blue-400/40' : 'bg-zinc-900/40' ?>">
                                                    <div class="text-[9px] uppercase tracking-wide
                                                        <?= $isLeader ? 'text-blue-300' : 'text-zinc-500' ?>">
                                                        <?= $def['label'] ?>
                                                    </div>
                                                    <div class="text-xs font-bold
                                                        <?= $isLeader ? 'text-blue-200' : 'text-white' ?>">
                                                        <?= $def['fmt']($stats[$key] ?? 0) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
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
        <button type="button" id="game-prev" aria-label="Previous game"
                class="absolute top-1/2 left-2 -translate-y-1/2 z-20
                       h-10 w-10 flex items-center justify-center rounded-full
                       bg-black/50 hover:bg-black/70 text-white transition">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button type="button" id="game-next" aria-label="Next game"
                class="absolute top-1/2 right-2 -translate-y-1/2 z-20
                       h-10 w-10 flex items-center justify-center rounded-full
                       bg-black/50 hover:bg-black/70 text-white transition">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    <!-- Dots + counter -->
    <div class="flex flex-col items-center gap-2 mt-4">
        <div id="game-dots" class="flex flex-wrap justify-center gap-1.5 max-w-full"></div>
        <div class="text-xs text-zinc-500">
            <span id="game-counter">1</span> / <?= $slideCount ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const root = document.getElementById('game-slideshow');
    if (!root) return;

    const track   = document.getElementById('game-track');
    const slides   = Array.from(track.children);
    const count   = slides.length;
    const dotsBox  = document.getElementById('game-dots');
    const counter  = document.getElementById('game-counter');
    let index = 0;

    const dots = slides.map((_, i) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('aria-label', 'Go to game ' + (i + 1));
        dot.className = 'h-2 w-2 rounded-full bg-zinc-600 hover:bg-zinc-400 transition';
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

    document.getElementById('game-next').addEventListener('click', next);
    document.getElementById('game-prev').addEventListener('click', prev);

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
