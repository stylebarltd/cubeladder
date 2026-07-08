<?php
// Only maps that actually have top players, so slide indexes line up with the dots.
$slideMaps = [];
foreach ($maps as $map) {
    if (empty($map->top_players)) {
        continue;
    }
    $slideMaps[] = $map;
}
$slideCount = count($slideMaps);
?>

<div class="w-full max-w-7xl px-0 sm:px-6 py-10 mx-auto">

    <h1 class="text-3xl font-bold mb-6 text-white text-center px-4 sm:px-0">
        Maps
        <small class="text-xs text-blue-500">by most played</small>
    </h1>


    <?php if ($slideCount === 0): ?>
        <p class="text-center text-zinc-500">No maps to show yet.</p>
    <?php else: ?>

    <div id="map-slideshow"
         class="relative select-none sm:rounded-xl border-y sm:border border-zinc-700 bg-zinc-800 overflow-hidden"
         data-count="<?= $slideCount ?>">

        <!-- Track -->
        <div id="map-track"
             class="flex transition-transform duration-300 ease-out"
             style="transform: translateX(0%);">

            <?php $i = 1; foreach ($slideMaps as $map): ?>
                <?php
                $mapImage = WWW_ROOT . 'img/maps/' . $map->name . '.jpg';
                $mapUrl = file_exists($mapImage)
                    ? '/img/maps/' . h($map->name) . '.jpg'
                    : '/img/maps/placeholder.jpg';
                ?>

                <!-- Slide -->
                <div class="map-slide w-full flex-shrink-0">
                    <div class="flex flex-col lg:flex-row">

                        <!-- Map image / hero -->
                        <div class="relative w-full lg:w-1/2 aspect-video lg:aspect-square bg-cover bg-center"
                             style="background-image:
                                 linear-gradient(to top, rgba(0,0,0,0.9), rgba(0,0,0,0.35), transparent),
                                 url('<?= $mapUrl ?>');">

                            <!-- Rank badge -->
                            <div class="absolute top-3 left-3 bg-black/60 text-zinc-200 text-xs font-bold
                                        px-2.5 py-1 rounded-full">
                                #<?= $i ?> most played
                                <?php if (!empty($map->games_count)): ?>
                                    · <?= (int)$map->games_count ?> games
                                <?php endif; ?>
                            </div>

                            <!-- Name + last 10 "best on map" winners -->
                            <div class="absolute bottom-0 left-0 right-0 p-4 md:p-5">
                                <div class="font-bold text-2xl md:text-3xl leading-tight text-white drop-shadow">
                                    <?= h($map->name) ?>
                                </div>

                                <?php if (!empty($map->best_on_map) && count($map->best_on_map)): ?>
                                    <div class="text-[10px] uppercase tracking-wide text-yellow-500/80 mt-1 drop-shadow">
                                        Most Points
                                    </div>
                                    <div class="space-y-0.5">
                                        <?php foreach ($map->best_on_map as $achievement): ?>
                                            <?php
                                            $weeksLabel = null;
                                            if (!empty($achievement->week_end)) {
                                                $weekEndStr = is_string($achievement->week_end)
                                                    ? $achievement->week_end
                                                    : $achievement->week_end->format('Y-m-d');
                                                $weeks = (int) floor((time() - strtotime($weekEndStr)) / (7 * 86400));
                                                $weeksLabel = $weeks <= 0
                                                    ? 'this week'
                                                    : ($weeks === 1 ? '1 week ago' : $weeks . ' weeks ago');
                                            }
                                            ?>
                                            <div class="text-xs text-yellow-400 leading-tight drop-shadow truncate">
                                                🏆
                                                <?= $this->Html->link(
                                                    h($achievement->player->name),
                                                    ['controller' => 'Players', 'action' => 'view', $achievement->player->id],
                                                    ['class' => 'hover:text-yellow-300', 'escape' => false]
                                                ) ?>
                                                <?= $this->Layout->flag($achievement->player->country) ?>
                                                <span class="font-bold text-yellow-200"><?= round($achievement->count) ?></span>
                                                <?php if ($weeksLabel): ?>
                                                    <span class="text-yellow-200/60"><?= h($weeksLabel) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Top players -->
                        <div class="w-full lg:w-1/2 p-4 md:p-6">

                            <!-- Per-map leaders -->
                            <?php
                            $leaderDefs = [
                                'ratio'    => ['label' => 'Best K/D Ratio',    'fmt' => fn($v) => number_format((float)$v, 2)],
                                'headshot' => ['label' => 'Most Headshots',    'fmt' => fn($v) => number_format((int)$v)],
                                'flags'    => ['label' => 'Most Flags Scored', 'fmt' => fn($v) => number_format((int)$v)],
                                'slashed'  => ['label' => 'Most Slashes',      'fmt' => fn($v) => number_format((int)$v)],
                                'gibbed'   => ['label' => 'Most Gibs',         'fmt' => fn($v) => number_format((int)$v)],
                                'points'   => ['label' => 'Most Points',       'fmt' => fn($v) => number_format((int)$v)],
                            ];
                            ?>
                            <div class="grid grid-cols-2 gap-2 mb-5">
                                <?php foreach ($leaderDefs as $key => $def):
                                    $leader = $map->leaders[$key] ?? null; ?>
                                    <div class="rounded-lg bg-zinc-900/60 p-2">
                                        <div class="text-[10px] uppercase tracking-wide text-zinc-500">
                                            <?= $def['label'] ?>
                                        </div>
                                        <?php if ($leader && !empty($leader->player)): ?>
                                            <div class="text-sm font-semibold truncate">
                                                <?= $this->Html->link(
                                                    h($leader->player->name),
                                                    ['controller' => 'Players', 'action' => 'view', $leader->player->id],
                                                    ['class' => 'hover:text-blue-400', 'escape' => false]
                                                ) ?>
                                                <?= $this->Layout->flag($leader->player->country) ?>
                                            </div>
                                            <div class="text-xs font-mono text-blue-400">
                                                <?= $def['fmt']($leader->val) ?>
                                            </div>
                                            <?php if (!empty($leader->game_id)): ?>
                                                <a href="<?= $this->Url->build([
                                                    'controller' => 'Games',
                                                    'action' => 'view',
                                                    $leader->game_id
                                                ]) ?>"
                                                   class="text-[10px] text-zinc-500 hover:text-blue-400 hover:underline whitespace-nowrap">
                                                    <?= !empty($leader->played_at)
                                                        ? h($leader->played_at->format('d M Y'))
                                                        : 'view game' ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="text-sm text-zinc-600">&mdash;</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <h2 class="text-xs uppercase tracking-wide text-zinc-500 mb-3">
                                Top Players by Total Points
                            </h2>
                            <ul class="space-y-2">
                                <?php $rank = 1; foreach ($map->top_players as $player): ?>
                                    <li class="flex items-center justify-between text-sm md:text-base">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <span class="w-5 shrink-0 text-right font-bold text-zinc-500">
                                                <?= $rank++ ?>
                                            </span>
                                            <span class="truncate">
                                                <?= $this->Html->link(
                                                    h($player->player->name),
                                                    ['controller' => 'Players', 'action' => 'view', $player->player->id],
                                                    ['class' => 'hover:text-blue-400', 'escape' => false]
                                                ) ?>
                                                <?= $this->Layout->flag($player->player->country) ?>
                                            </span>
                                        </span>
                                        <span class="font-bold text-zinc-300 shrink-0 ml-3">
                                            <?= (int)$player->score ?> pts
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                    </div>
                </div>

            <?php $i++; endforeach; ?>

        </div>

        <!-- Prev / Next arrows -->
        <button type="button" id="map-prev" aria-label="Previous map"
                class="absolute top-1/2 left-2 -translate-y-1/2 z-20
                       h-10 w-10 flex items-center justify-center rounded-full
                       bg-black/50 hover:bg-black/70 text-white transition">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button type="button" id="map-next" aria-label="Next map"
                class="absolute top-1/2 right-2 -translate-y-1/2 z-20
                       h-10 w-10 flex items-center justify-center rounded-full
                       bg-black/50 hover:bg-black/70 text-white transition">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    <!-- Dots + counter -->
    <div class="flex flex-col items-center gap-2 mt-4">
        <div id="map-dots" class="flex flex-wrap justify-center gap-2 max-w-full"></div>
        <div class="text-xs text-zinc-500">
            <span id="map-counter">1</span> / <?= $slideCount ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const root = document.getElementById('map-slideshow');
    if (!root) return;

    const track   = document.getElementById('map-track');
    const slides   = Array.from(track.children);
    const count   = slides.length;
    const dotsBox  = document.getElementById('map-dots');
    const counter  = document.getElementById('map-counter');
    let index = 0;

    // Build dots
    const dots = slides.map((_, i) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('aria-label', 'Go to map ' + (i + 1));
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

    document.getElementById('map-next').addEventListener('click', next);
    document.getElementById('map-prev').addEventListener('click', prev);

    // Keyboard
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
        // Only treat as swipe if mostly horizontal and past threshold
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
            dx < 0 ? next() : prev();
        }
    }, { passive: true });

    render();
})();
</script>
<?php $this->end(); ?>
