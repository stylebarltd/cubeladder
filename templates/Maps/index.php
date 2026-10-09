<?php
/**
 * One map per page, full screen like the game pages: the map in the
 * background, records / top players / weekly winners on top with opacity,
 * a dropdown (with thumbnails) of every played map and prev / next arrows
 * in most-played order.
 *
 * @var \App\View\AppView $this
 * @var \Cake\ORM\Entity|null $map
 * @var array $allMaps
 * @var int $rank
 * @var string|null $prevMap
 * @var string|null $nextMap
 */
if (!$map): ?>
    <p class="py-20 text-center text-zinc-500">No maps to show yet.</p>
    <?php return; endif;

$mapUrl = fn(string $name) => $this->Url->build(['controller' => 'Maps', 'action' => 'index', '?' => ['map' => $name]]);
$panel = 'rounded-lg border border-white/15 bg-black/60 p-4 backdrop-blur-[2px]';
$panelTitle = 'mb-3 text-[11px] font-bold uppercase tracking-wider';

$leaderDefs = [
    'points'   => ['label' => 'Most Points',       'fmt' => fn($v) => number_format((int)$v)],
    'ratio'    => ['label' => 'Best K/D Ratio',    'fmt' => fn($v) => number_format((float)$v, 2)],
    'flags'    => ['label' => 'Most Flags Scored', 'fmt' => fn($v) => number_format((int)$v)],
    'headshot' => ['label' => 'Most Headshots',    'fmt' => fn($v) => number_format((int)$v)],
    'slashed'  => ['label' => 'Most Slashes',      'fmt' => fn($v) => number_format((int)$v)],
    'gibbed'   => ['label' => 'Most Gibs',         'fmt' => fn($v) => number_format((int)$v)],
];
$navBtn = 'absolute top-4 md:top-1/2 md:-translate-y-1/2 z-20 h-11 w-11 flex items-center justify-center rounded-full bg-black/60 hover:bg-black/80 text-white transition';
?>
<h1 class="sr-only">Maps</h1>

<div class="relative overflow-hidden bg-zinc-900 text-white">
    <img src="<?= $this->Layout->mapImage($map->name) ?>" alt="" class="absolute inset-0 h-full w-full object-cover">
    <div class="absolute inset-0 bg-gradient-to-b from-black/50 via-black/10 to-black/60"></div>

    <!-- Fills the screen below the 4rem nav bar -->
    <div class="relative z-10 mx-auto flex min-h-[calc(100svh-4rem)] w-full max-w-7xl flex-col gap-4 px-4 py-5 md:px-16 md:py-8">

        <!-- Title + map picker -->
        <div class="flex flex-wrap items-start justify-between gap-4 pr-24 md:pr-0">
            <div class="drop-shadow-lg">
                <h2 class="text-3xl md:text-5xl font-extrabold tracking-wide"><?= $this->Layout->cleanMapName($map->name) ?></h2>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-200">
                    <span class="font-mono"><?= h($map->name) ?></span>
                    <span><i class="fa-solid fa-ranking-star mr-1"></i>#<?= $rank ?> most played</span>
                    <span><i class="fa-solid fa-gamepad mr-1"></i><?= number_format((int)$map->games_count) ?> games</span>
                </div>
            </div>

            <div class="relative w-full sm:w-80" id="map-picker">
                <button type="button" id="map-picker-btn" aria-haspopup="listbox" aria-expanded="false"
                        class="flex w-full items-center gap-3 rounded-lg border border-white/15 bg-black/60 p-2 text-left backdrop-blur-[2px] hover:bg-black/75 transition">
                    <img src="<?= $this->Layout->mapThumb($map->name) ?>" alt="" class="h-9 w-16 shrink-0 rounded object-cover">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold"><?= $this->Layout->cleanMapName($map->name) ?></span>
                        <span class="block text-xs text-zinc-400"><?= count($allMaps) ?> maps played &ndash; jump to&hellip;</span>
                    </span>
                    <i class="fas fa-chevron-down text-zinc-400"></i>
                </button>

                <div id="map-picker-panel" role="listbox"
                     class="absolute right-0 z-30 mt-2 hidden w-full overflow-hidden rounded-lg border border-white/15 bg-zinc-900/95 shadow-2xl backdrop-blur">
                    <div class="border-b border-white/10 p-2">
                        <input id="map-picker-search" type="search" placeholder="Search maps…" autocomplete="off"
                               class="w-full rounded bg-zinc-800 px-3 py-1.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <ul class="nice-scroll max-h-[60vh] overflow-y-auto py-1">
                        <?php foreach ($allMaps as $i => $m): $current = $m->id === $map->id; ?>
                            <li data-name="<?= h(strtolower($m->name . ' ' . $this->Layout->cleanMapName($m->name))) ?>">
                                <a href="<?= $mapUrl($m->name) ?>" role="option" aria-selected="<?= $current ? 'true' : 'false' ?>"
                                   class="flex items-center gap-3 px-2 py-1.5 hover:bg-white/10 <?= $current ? 'bg-blue-600/30' : '' ?>">
                                    <img src="<?= $this->Layout->mapThumb($m->name) ?>" alt="" loading="lazy" class="h-9 w-16 shrink-0 rounded object-cover bg-zinc-800">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm"><?= $this->Layout->cleanMapName($m->name) ?></span>
                                        <span class="block truncate font-mono text-[10px] text-zinc-500"><?= h($m->name) ?></span>
                                    </span>
                                    <span class="shrink-0 text-xs text-zinc-400 tabular-nums"><?= number_format((int)$m->games_count) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Sections -->
        <div class="mt-auto grid gap-4 lg:grid-cols-3">

            <!-- Single-game records on this map -->
            <section class="<?= $panel ?>">
                <h3 class="<?= $panelTitle ?> text-blue-300"><i class="fa-solid fa-medal mr-1"></i> Map records</h3>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach ($leaderDefs as $key => $def): $leader = $map->leaders[$key] ?? null; ?>
                        <div class="min-w-0 rounded bg-white/5 p-2">
                            <div class="text-[10px] uppercase tracking-wide text-zinc-400"><?= $def['label'] ?></div>
                            <?php if ($leader && !empty($leader->player)): ?>
                                <div class="text-lg font-extrabold leading-tight text-sky-300 tabular-nums"><?= $def['fmt']($leader->val) ?></div>
                                <div class="truncate text-sm font-semibold">
                                    <?= $this->Html->link(h($leader->player->name), ['controller' => 'Players', 'action' => 'view', $leader->player->id], ['class' => 'hover:text-blue-300', 'escape' => false]) ?>
                                    <?= $this->Layout->flag($leader->player->country) ?>
                                </div>
                                <?php if (!empty($leader->game_id)): ?>
                                    <?= $this->Html->link(
                                        !empty($leader->played_at) ? h($leader->played_at->format('d M Y')) : 'view game',
                                        ['controller' => 'Games', 'action' => 'view', $leader->game_id],
                                        ['class' => 'text-[10px] text-zinc-400 hover:text-blue-300 hover:underline']
                                    ) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-sm text-zinc-500">&mdash;</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Top players by summed points -->
            <section class="<?= $panel ?>">
                <h3 class="<?= $panelTitle ?> text-green-300"><i class="fa-solid fa-users mr-1"></i> Top players by total points</h3>
                <?php if ($map->top_players): ?>
                    <ol class="space-y-1.5">
                        <?php foreach ($map->top_players as $i => $player): ?>
                            <li class="flex items-center gap-2 text-sm">
                                <span class="w-5 shrink-0 text-right font-bold <?= $i === 0 ? 'text-yellow-300' : 'text-zinc-400' ?>"><?= $i + 1 ?></span>
                                <span class="min-w-0 flex-1 truncate">
                                    <?= $this->Html->link(h($player->player->name), ['controller' => 'Players', 'action' => 'view', $player->player->id], ['class' => 'font-semibold hover:text-blue-300', 'escape' => false]) ?>
                                    <?= $this->Layout->flag($player->player->country) ?>
                                </span>
                                <span class="shrink-0 font-bold text-sky-300 tabular-nums"><?= number_format((int)$player->score) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="text-sm text-zinc-400">No ladder players yet.</p>
                <?php endif; ?>
            </section>

            <!-- Weekly "best on map" achievement winners -->
            <section class="<?= $panel ?>">
                <h3 class="<?= $panelTitle ?> text-yellow-300"><i class="fa-solid fa-trophy mr-1"></i> Weekly Achievement Winners</h3>
                <?php if ($map->best_on_map): ?>
                    <ul class="space-y-1.5">
                        <?php foreach ($map->best_on_map as $achievement):
                            $weekEnd = is_string($achievement->week_end) ? $achievement->week_end : $achievement->week_end->format('Y-m-d');
                            $weeks = (int)floor((time() - strtotime($weekEnd)) / (7 * 86400));
                            $weeksLabel = $weeks <= 0 ? 'this week' : ($weeks === 1 ? '1 week ago' : $weeks . ' weeks ago');
                            ?>
                            <li class="flex items-center gap-2 text-sm">
                                <span class="shrink-0">🏆</span>
                                <span class="min-w-0 flex-1 truncate">
                                    <?= $this->Html->link(h($achievement->player->name), ['controller' => 'Players', 'action' => 'view', $achievement->player->id], ['class' => 'font-semibold hover:text-yellow-200', 'escape' => false]) ?>
                                    <?= $this->Layout->flag($achievement->player->country) ?>
                                </span>
                                <span class="shrink-0 font-bold text-yellow-200 tabular-nums"><?= number_format((float)$achievement->count) ?></span>
                                <span class="w-20 shrink-0 text-right text-[11px] text-zinc-400"><?= h($weeksLabel) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-sm text-zinc-400">No weekly winner on this map yet.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <!-- Prev / next map (most-played order) -->
    <?= $this->Html->link('<i class="fas fa-chevron-left"></i>', $mapUrl($prevMap),
        ['escape' => false, 'id' => 'map-prev', 'title' => 'Previous map', 'aria-label' => 'Previous map', 'class' => $navBtn . ' right-16 md:right-auto md:left-2']) ?>
    <?= $this->Html->link('<i class="fas fa-chevron-right"></i>', $mapUrl($nextMap),
        ['escape' => false, 'id' => 'map-next', 'title' => 'Next map', 'aria-label' => 'Next map', 'class' => $navBtn . ' right-2']) ?>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const picker = document.getElementById('map-picker');
    const btn = document.getElementById('map-picker-btn');
    const panel = document.getElementById('map-picker-panel');
    const search = document.getElementById('map-picker-search');
    const items = Array.from(panel.querySelectorAll('li'));

    function open(show) {
        panel.classList.toggle('hidden', !show);
        btn.setAttribute('aria-expanded', show ? 'true' : 'false');
        if (show) {
            search.value = '';
            filter();
            search.focus();
            panel.querySelector('[aria-selected="true"]')?.scrollIntoView({block: 'center'});
        }
    }
    function filter() {
        const q = search.value.trim().toLowerCase();
        items.forEach(li => li.classList.toggle('hidden', q !== '' && !li.dataset.name.includes(q)));
    }

    btn.addEventListener('click', () => open(panel.classList.contains('hidden')));
    search.addEventListener('input', filter);
    search.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const first = items.find(li => !li.classList.contains('hidden'));
            if (first) location.href = first.querySelector('a').href;
        }
    });
    document.addEventListener('click', (e) => { if (!picker.contains(e.target)) open(false); });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') return open(false);
        if (e.target.closest('input, textarea, select')) return;
        const a = document.getElementById(e.key === 'ArrowLeft' ? 'map-prev' : e.key === 'ArrowRight' ? 'map-next' : '');
        if (a) location.href = a.href;
    });
})();
</script>
<?php $this->end(); ?>
