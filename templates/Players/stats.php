<?php
/**
 * Special stats (QR hunt unlock, level 4): PlayersController::stats, App\Service\SpecialStats.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Player $player
 * @var int $qrLevel
 * @var bool $unlocked
 * @var array|null $stats
 */
use App\Service\QrHunt;
use App\Service\SpecialStats;

$this->assign('title', $player->name . ' · Special stats');
$panel = 'rounded-xl border border-white/15 bg-black/60 backdrop-blur-[2px]';
$avatar = fn($pic) => !empty($pic) ? '/img/players/' . h($pic) : '/img/acl.png';
$need = QrHunt::UNLOCKS['stats'][0];
$stat = fn($v, string $label, string $color = '') => '<div class="rounded-lg bg-white/5 px-4 py-2 text-center"><div class="font-mono text-2xl font-extrabold ' . $color . '">' . $v
    . '</div><div class="text-[10px] uppercase tracking-wider text-zinc-400">' . $label . '</div></div>';
$hours = fn(int $min) => $min >= 600 ? number_format($min / 60) : number_format($min / 60, 1);
// a K/D colour: green above 1, red below
$kdColor = fn(float $kd) => $kd >= 1.2 ? 'text-green-400' : ($kd < 0.9 ? 'text-red-400' : 'text-zinc-200');
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center bg-fixed text-white"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.55), rgba(0,0,0,1) 35%, rgba(0,0,0,.8)), url('/img/bullet.jpg');">
<div class="mx-auto w-full max-w-7xl px-4 py-6 md:px-16 md:py-8">

    <div class="mb-6 flex flex-wrap items-center gap-4">
        <img src="<?= $avatar($player->picture) ?>" class="h-14 w-14 rounded-full object-cover" alt="">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-extrabold tracking-wide md:text-4xl">📊 <?= h($player->name) ?> <?= $this->Layout->flag($player->country) ?></h1>
            <div class="text-sm text-zinc-400">Special stats &middot; K/D and accuracy over time, maps, hours</div>
        </div>
        <a href="/players/view/<?= h($player->id) ?>" class="rounded-md border border-white/15 bg-white/5 px-3 py-1.5 text-sm font-semibold hover:bg-white/10">&larr; Profile</a>
    </div>

<?php if (!$unlocked): ?>
    <section class="<?= $panel ?> max-w-2xl p-6">
        <h2 class="mb-2 text-xl font-bold"><i class="fa-solid fa-lock mr-2 text-amber-400"></i>Locked</h2>
        <p class="text-zinc-300">Special stats open at <b>QR hunt level <?= (int)$need ?></b>: find <?= (int)$need ?> of the QR codes hidden in our maps
            and scan them with your phone. <?= h($player->name) ?> is at level <?= (int)$qrLevel ?>.</p>
        <p class="mt-2 text-zinc-300">Then everyone can see their K/D and accuracy week by week, their best and worst maps and when they play.</p>
    </section>
<?php else:
    $t = $stats['totals'];
    $weeks = $stats['weeks']; ?>

    <div class="mb-6 flex flex-wrap gap-3">
        <?= $stat(number_format($t['kd'], 2), 'K/D', $kdColor((float)$t['kd'])) ?>
        <?= $stat($t['acc'] !== null ? $t['acc'] . '%' : '&ndash;', 'accuracy', 'text-sky-300') ?>
        <?= $stat($hours($t['minutes']), 'hours played', 'text-amber-300') ?>
        <?= $stat(number_format($t['games']), 'games') ?>
        <?= $stat(number_format($stats['daysPlayed']), 'days played') ?>
    </div>

    <!-- K/D week by week -->
    <?php $kdMax = max(1.0, ...array_map(fn($w) => (float)($w['kd'] ?? 0), $weeks)); ?>
    <section class="<?= $panel ?> mb-6 p-5">
        <h3 class="text-lg font-bold">📈 K/D week by week</h3>
        <div class="mb-3 text-xs text-zinc-400">kills per death each week, the last <?= SpecialStats::WEEKS ?> weeks &middot; the line is 1.0 &middot; weeks start Monday (UTC)</div>
        <div class="relative flex h-36 items-end gap-[3px]">
            <div class="pointer-events-none absolute inset-x-0 z-10 border-t border-dashed border-white/60" style="bottom: <?= round(100 / $kdMax, 1) ?>%"></div>
            <?php foreach ($weeks as $w): ?>
                <div class="group relative flex h-full min-w-0 flex-1 flex-col justify-end"
                     title="week of <?= h(date('j M', strtotime($w['week']))) ?>: <?= $w['kd'] === null ? 'no games' : 'K/D ' . number_format($w['kd'], 2) . ' (' . $w['kills'] . ' kills, ' . $w['deaths'] . ' deaths, ' . $w['games'] . ' games)' ?>">
                    <?php if ($w['kd'] !== null): ?>
                        <div class="rounded-t <?= $w['kd'] >= 1 ? 'bg-green-500/80 group-hover:bg-green-400' : 'bg-red-500/80 group-hover:bg-red-400' ?>" style="height: <?= max(2, round(100 * $w['kd'] / $kdMax, 1)) ?>%"></div>
                    <?php else: ?>
                        <div class="h-px bg-white/10"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-1 flex gap-[3px] text-[10px] text-zinc-500">
            <?php foreach ($weeks as $i => $w): ?>
                <span class="min-w-0 flex-1 overflow-visible whitespace-nowrap"><?= $i % 4 === 0 ? h(date('j M', strtotime($w['week']))) : '' ?></span>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- accuracy week by week, only weeks the live loop saw -->
    <?php $accWeeks = array_values(array_filter($weeks, fn($w) => $w['acc'] !== null)); ?>
    <section class="<?= $panel ?> mb-6 p-5">
        <h3 class="text-lg font-bold">🎯 Accuracy over time</h3>
        <div class="mb-3 text-xs text-zinc-400">damage dealt out of the damage your shots could have done, per week &middot; measured since Oct 2026 (<?= number_format($t['live']) ?> games)</div>
        <?php if (!$accWeeks): ?>
            <p class="text-sm text-zinc-400">No measured games yet &ndash; this fills up with the next ones.</p>
        <?php else: $accMax = max(50, ...array_column($accWeeks, 'acc')); ?>
            <div class="space-y-1.5">
                <?php foreach ($accWeeks as $w): ?>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-16 shrink-0 text-right text-xs text-zinc-400"><?= h(date('j M', strtotime($w['week']))) ?></span>
                        <span class="h-4 min-w-0 flex-1 rounded bg-white/5"><span class="block h-4 rounded bg-sky-500/70" style="width: <?= round(100 * $w['acc'] / $accMax, 1) ?>%"></span></span>
                        <span class="w-12 shrink-0 font-mono text-sky-300"><?= (int)$w['acc'] ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- best and worst maps -->
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <?php foreach ([['🏆 Best maps', 'highest K/D', $stats['best']], ['🪦 Worst maps', 'lowest K/D', $stats['worst']]] as [$title, $sub, $list]): ?>
            <section class="<?= $panel ?> p-5">
                <h3 class="text-lg font-bold"><?= $title ?></h3>
                <div class="mb-3 text-xs text-zinc-400"><?= $sub ?> &middot; maps with at least <?= SpecialStats::MIN_MAP_GAMES ?> games</div>
                <?php if (!$list): ?>
                    <p class="text-sm text-zinc-400"><?= $stats['mapsRanked'] ? 'Every map with enough games is already among the best.' : 'Not enough games on one map yet.' ?></p>
                <?php else: ?>
                    <div class="space-y-1.5">
                        <?php foreach ($list as $m): ?>
                            <div class="flex items-center gap-3 rounded-lg bg-white/5 px-3 py-1.5">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-semibold"><?= h($m['map']) ?></div>
                                    <div class="text-[11px] text-zinc-400"><?= number_format($m['games']) ?> games &middot; <?= number_format($m['kills']) ?> kills, <?= number_format($m['deaths']) ?> deaths<?= $m['acc'] !== null ? ' &middot; ' . $m['acc'] . '% accuracy' : '' ?></div>
                                </div>
                                <div class="font-mono text-lg font-bold <?= $kdColor((float)$m['kd']) ?>"><?= number_format($m['kd'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <!-- hours: weekday x hour, drawn in the visitor's time zone -->
    <section class="<?= $panel ?> p-5">
        <h3 class="text-lg font-bold">🕒 Your hours</h3>
        <div class="mb-3 text-xs text-zinc-400"><?= $hours($t['minutes']) ?> hours on a team in all games, by the hour a game started (<span class="js-tz">UTC</span>)<span id="hours-best"></span>
            <?php if ($stats['bestDay']): ?>&middot; longest day: <?= h(date('j M Y', strtotime($stats['bestDay']['day']))) ?> (<?= $hours($stats['bestDay']['minutes']) ?> h, UTC)<?php endif; ?></div>
        <div class="overflow-x-auto">
            <div id="hours-heat" class="min-w-[36rem]" data-heat="<?= h(json_encode($stats['heat'])) ?>"></div>
        </div>
        <div class="mt-5 grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <h4 class="mb-2 text-sm font-bold uppercase tracking-wide text-zinc-400">By weekday</h4>
                <div id="hours-days" class="space-y-1"></div>
            </div>
            <div>
                <h4 class="mb-2 text-sm font-bold uppercase tracking-wide text-zinc-400">By time of day</h4>
                <div id="hours-hours" class="flex h-28 items-end gap-[2px]"></div>
                <div id="hours-hours-axis" class="mt-1 flex gap-[2px] font-mono text-[10px] text-zinc-500"></div>
            </div>
        </div>
    </section>

    <p class="mt-6 text-xs text-zinc-500">From the servers' logs: K/D, maps and accuracy leave out games marked inaccurate; a map counts games of at least
        <?= \App\Model\Table\PlayerStatsPerGameTable::MIN_MINUTES ?> minutes. Unlocked by <?= h($player->name) ?> in the QR hunt.</p>

<script>
// the hours in the visitor's time zone (whole-hour offsets, else UTC), as on /stats
(() => {
    const el = document.getElementById('hours-heat');
    if (!el) return;
    const utc = JSON.parse(el.dataset.heat);
    let off = -new Date().getTimezoneOffset() / 60;
    if (off % 1) off = 0;
    const local = new Array(168).fill(0); // Monday 0:00 local first
    utc.forEach((v, i) => { local[((i + off) % 168 + 168) % 168] = v; });
    const max = Math.max(...local, 1);
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const h = m => (m / 60).toFixed(m >= 600 ? 0 : 1);
    let html = '<div class="grid gap-[2px]" style="grid-template-columns: 2.5rem repeat(24, minmax(0, 1fr))">';
    html += '<span></span>' + [...Array(24).keys()].map(x => `<span class="text-center font-mono text-[10px] text-zinc-500">${x % 3 === 0 ? x : ''}</span>`).join('');
    days.forEach((d, di) => {
        html += `<span class="pr-1 text-right text-[11px] leading-5 text-zinc-400">${d}</span>`;
        for (let x = 0; x < 24; x++) {
            const v = local[di * 24 + x];
            const a = v ? 0.08 + 0.88 * v / max : 0.03;
            html += `<span class="h-5 rounded-sm" style="background: rgba(251,191,36,${a.toFixed(2)})" title="${d} ${x}:00 – ${h(v)} h"></span>`;
        }
    });
    el.innerHTML = html + '</div>';

    const perDay = days.map((d, di) => local.slice(di * 24, di * 24 + 24).reduce((a, b) => a + b, 0));
    const dayMax = Math.max(...perDay, 1);
    document.getElementById('hours-days').innerHTML = days.map((d, di) =>
        `<div class="flex items-center gap-3 text-sm"><span class="w-9 shrink-0 text-zinc-400">${d}</span>`
        + `<span class="h-4 min-w-0 flex-1 rounded bg-white/5"><span class="block h-4 rounded bg-amber-400/80" style="width:${(100 * perDay[di] / dayMax).toFixed(1)}%"></span></span>`
        + `<span class="w-14 shrink-0 text-right font-mono text-xs">${h(perDay[di])} h</span></div>`).join('');

    const perHour = [...Array(24).keys()].map(x => days.reduce((a, d, di) => a + local[di * 24 + x], 0));
    const hourMax = Math.max(...perHour, 1);
    const peak = perHour.indexOf(Math.max(...perHour));
    document.getElementById('hours-hours').innerHTML = perHour.map((v, x) =>
        `<div class="flex h-full min-w-0 flex-1 flex-col justify-end" title="${x}:00 – ${h(v)} h"><div class="rounded-t ${x === peak ? 'bg-amber-400' : 'bg-amber-400/50'}" style="height:${Math.max(2, 100 * v / hourMax).toFixed(1)}%"></div></div>`).join('');
    document.getElementById('hours-hours-axis').innerHTML = perHour.map((v, x) => `<span class="min-w-0 flex-1 text-center">${x % 3 === 0 ? x : ''}</span>`).join('');

    const best = local.indexOf(Math.max(...local));
    if (local[best] > 0) {
        document.getElementById('hours-best').textContent = ` · favourite slot: ${days[Math.floor(best / 24)]} ${best % 24}:00`;
    }
    document.querySelectorAll('.js-tz').forEach(e => e.textContent = Intl.DateTimeFormat().resolvedOptions().timeZone || 'local time');
})();
</script>
<?php endif; ?>
</div>
</div>
