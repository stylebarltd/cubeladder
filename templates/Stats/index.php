<?php
/**
 * The community in numbers (CommunityStatsService): where the front page's
 * player count comes from and who plays regularly.
 *
 * @var \App\View\AppView $this
 * @var array $stats
 */
use App\Service\CommunityStatsService as C;

$this->assign('title', 'Community stats · cubeLadder');
$s = $stats;
$n = fn($v) => number_format((int)round((float)$v));
$pct = fn($v) => $s['players'] ? round($v / $s['players'] * 100) : 0;
$hourMax = max(1, max($s['hours']));
$peakHour = array_search(max($s['hours']), $s['hours']);
$quietHour = array_search(min($s['hours']), $s['hours']);
$avgOnline = array_sum($s['hours']) / 24;
$monthMax = max([1, ...array_values(array_column($s['months'], 'active'))]);
$card = 'rounded-xl border border-white/10 bg-black/50 p-4 md:p-5';
$big = 'font-mono text-3xl md:text-4xl font-extrabold';
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
    <div>
        <h1 class="text-3xl font-extrabold tracking-wide"><i class="fa-solid fa-users mr-2 text-sky-400"></i>Community stats</h1>
        <p class="mt-1 max-w-3xl text-sm text-zinc-400">
            Where the <?= $n($s['players']) ?> players on the front page come from, and how many of them play regularly.
            From the logs of the <?= (int)$s['servers'] ?> ladder servers since <?= h(date('j F Y', strtotime((string)$s['first']))) ?>.
        </p>
    </div>

    <!-- the headline numbers -->
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <?php foreach ([
            [$s['players'], 'players seen', 'every different player in a ladder game, ever', 'text-white'],
            [$s['month'], 'last ' . C::DAYS . ' days', 'played at least one game', 'text-sky-300'],
            [$s['regulars'], 'regulars', 'played on ' . C::REGULAR_DAYS . '+ of the last ' . C::DAYS . ' days', 'text-green-300'],
            [$avgOnline, 'online on average', 'in games at the same time, peak hour ' . $n($s['hours'][$peakHour]), 'text-amber-300'],
        ] as [$v, $label, $hint, $color]): ?>
            <div class="<?= $card ?>">
                <div class="<?= $big ?> <?= $color ?>"><?= $n($v) ?></div>
                <div class="mt-1 text-xs font-bold uppercase tracking-wider text-zinc-300"><?= h($label) ?></div>
                <div class="mt-0.5 text-xs text-zinc-500"><?= h($hint) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- the question everybody asks -->
    <section class="<?= $card ?>">
        <h2 class="mb-2 text-lg font-bold">So why are there only a few players when I join?</h2>
        <div class="max-w-4xl space-y-2 text-sm text-zinc-300">
            <p>
                <b><?= $n($s['players']) ?></b> is everyone who ever played on a ladder server &ndash; also everyone who joined once, played one game and never came back:
                that's <b><?= $n($s['buckets']['1']) ?></b> of them (<?= $pct($s['buckets']['1']) ?>%). Every new name counts too, until it's linked to its player.
            </p>
            <p>
                The ones who really play: <b><?= $n($s['month']) ?></b> in the last <?= C::DAYS ?> days, <b><?= $n($s['week']) ?></b> in the last 7,
                about <b><?= $n($s['perDayAvg']) ?></b> on a normal day, and <b><?= $n($s['regulars']) ?></b> regulars who are on most days.
            </p>
            <p>
                They don't all play at the same time though. Spread over the day and over <?= (int)$s['servers'] ?> servers, there are about
                <b><?= $n($avgOnline) ?></b> players in games at once on average &ndash; around <b><?= $n($s['hours'][$peakHour]) ?></b> in the busiest hour
                (<?= sprintf('%02d:00', $peakHour) ?> UTC), only <b><?= $n($s['hours'][$quietHour]) ?></b> at <?= sprintf('%02d:00', $quietHour) ?> UTC.
                Join at the peak to find full servers &ndash; <a href="/live" class="text-sky-300 hover:underline">Live</a> shows who's on right now.
            </p>
        </div>
    </section>

    <!-- trends: this week so far, week by week -->
    <?php
    $weekMax = max([1, ...array_column($s['weeks'], 'players')]);
    $gamesMax = max([1, ...array_column($s['weeks'], 'games')]);
    $weekKeys = array_keys($s['weeks']);
    $current = end($weekKeys);
    $delta = function (int $now, int $before): string {
        if ($before === 0) {
            return $now ? '<span class="text-green-400">new</span>' : '';
        }
        $d = round(($now - $before) / $before * 100);

        return '<span class="' . ($d > 0 ? 'text-green-400' : ($d < 0 ? 'text-red-400' : 'text-zinc-400')) . '">'
            . ($d > 0 ? '▲ +' : ($d < 0 ? '▼ ' : '± ')) . $d . '%</span>';
    };
    ?>
    <section class="<?= $card ?>">
        <h2 class="text-lg font-bold">Trends</h2>
        <p class="mb-4 text-xs text-zinc-500">This week so far (since Monday, UTC) against the same days of last week</p>
        <div class="grid grid-cols-3 gap-3">
            <?php foreach ([['games', 'games'], ['players', 'players'], ['new', 'new players']] as [$k, $label]): ?>
                <div class="rounded-lg bg-white/5 px-3 py-2">
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <span class="font-mono text-2xl font-extrabold"><?= $n($s['thisWeek'][$k]) ?></span>
                        <span class="text-xs font-bold"><?= $delta($s['thisWeek'][$k], $s['lastWeek'][$k]) ?></span>
                    </div>
                    <div class="text-[11px] uppercase tracking-wide text-zinc-400"><?= $label ?></div>
                    <div class="text-[11px] text-zinc-500">last week by now: <?= $n($s['lastWeek'][$k]) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div>
                <h3 class="text-sm font-bold">Players per week</h3>
                <p class="mb-2 text-xs text-zinc-500">Everyone who played <span class="inline-block h-2 w-2 rounded-sm bg-sky-500/70"></span>, of them new <span class="inline-block h-2 w-2 rounded-sm bg-green-500/80"></span></p>
                <div class="flex h-36 items-end gap-1">
                    <?php foreach ($s['weeks'] as $w => $r): ?>
                        <div class="group relative flex h-full flex-1 flex-col justify-end">
                            <div class="relative rounded-t <?= $w === $current ? 'bg-sky-500/35 outline-1 outline-dashed outline-sky-400/60' : 'bg-sky-500/70' ?>" style="height: <?= max(1, round($r['players'] / $weekMax * 100)) ?>%">
                                <div class="absolute inset-x-0 bottom-0 bg-green-500/80" style="height: <?= $r['players'] ? round(min($r['new'], $r['players']) / $r['players'] * 100) : 0 ?>%"></div>
                            </div>
                            <span class="pointer-events-none absolute -top-10 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded bg-zinc-800 px-2 py-1 text-[11px] leading-tight group-hover:block">
                                week of <?= h(date('j M', strtotime($w))) ?><?= $w === $current ? ' (so far)' : '' ?><br><?= $r['players'] ?> players, <?= $r['new'] ?> new
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-1 flex gap-1 text-[10px] text-zinc-500">
                    <?php foreach ($weekKeys as $i => $w): ?>
                        <span class="flex-1 whitespace-nowrap text-center"><?= ($i % 5 === 0 && $i < count($weekKeys) - 2) || $w === $current ? h(date('j M', strtotime($w))) : '' ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h3 class="text-sm font-bold">Games per week</h3>
                <p class="mb-2 text-xs text-zinc-500">And the hours played in them</p>
                <div class="flex h-36 items-end gap-1">
                    <?php foreach ($s['weeks'] as $w => $r): ?>
                        <div class="group relative flex h-full flex-1 flex-col justify-end">
                            <div class="rounded-t <?= $w === $current ? 'bg-amber-400/35 outline-1 outline-dashed outline-amber-300/60' : 'bg-amber-400/75' ?>" style="height: <?= max(1, round($r['games'] / $gamesMax * 100)) ?>%"></div>
                            <span class="pointer-events-none absolute -top-10 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded bg-zinc-800 px-2 py-1 text-[11px] leading-tight group-hover:block">
                                week of <?= h(date('j M', strtotime($w))) ?><?= $w === $current ? ' (so far)' : '' ?><br><?= $r['games'] ?> games, <?= $n($r['hours']) ?> hours played
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-1 flex gap-1 text-[10px] text-zinc-500">
                    <?php foreach ($weekKeys as $i => $w): ?>
                        <span class="flex-1 whitespace-nowrap text-center"><?= ($i % 5 === 0 && $i < count($weekKeys) - 2) || $w === $current ? h(date('j M', strtotime($w))) : '' ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <h3 class="mt-6 text-sm font-bold">Do newcomers stay?</h3>
        <p class="mb-2 text-xs text-zinc-500">Of the players who were new that week, how many played again on another day. The latest weeks are still low: their newcomers had less time to come back.</p>
        <div class="flex gap-1">
            <?php foreach ($s['weeks'] as $w => $r): $rate = $r['new'] ? round($r['back'] / $r['new'] * 100) : null; ?>
                <div class="flex-1 rounded py-1 text-center font-mono text-[11px] <?= $rate === null ? 'bg-white/5 text-zinc-600' : ($rate >= 40 ? 'bg-green-500/30 text-green-200' : ($rate >= 25 ? 'bg-green-500/15 text-green-300' : 'bg-white/5 text-zinc-300')) ?>"
                     title="week of <?= h(date('j M', strtotime($w))) ?>: <?= $r['back'] ?> of <?= $r['new'] ?> new players came back">
                    <?= $rate === null ? '–' : $rate . '%' ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="mt-3 text-xs text-zinc-500">Weeks with a server whose logs didn't come in for a while look lower than they were.</p>
    </section>

    <!-- when they play: weekday x hour -->
    <section class="<?= $card ?>">
        <h2 class="text-lg font-bold">When do most play?</h2>
        <p class="mb-3 text-xs text-zinc-500">Players in games at the same time, average of the last <?= C::HEAT_WEEKS ?> full weeks, by day and hour (<span class="js-tz">UTC</span>)<span id="heat-best"></span></p>
        <div class="overflow-x-auto">
            <div id="heat" class="min-w-[36rem]" data-heat="<?= h(json_encode($s['heat'])) ?>"></div>
        </div>
        <div class="mt-2 flex items-center gap-2 text-[11px] text-zinc-500">
            fewer <span class="inline-flex"><?php foreach ([0.08, 0.25, 0.45, 0.65, 0.9] as $a): ?><span class="h-3 w-5" style="background: rgba(56,189,248,<?= $a ?>)"></span><?php endforeach; ?></span> more
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <!-- online by hour of day -->
        <section class="<?= $card ?>">
            <h2 class="text-lg font-bold">Online by time of day</h2>
            <p class="mb-3 text-xs text-zinc-500">Players in games at the same time, average of the last <?= C::DAYS ?> days, by hour (<span class="js-tz">UTC</span>)</p>
            <div class="flex h-40 items-end gap-[3px]" id="hours">
                <?php foreach ($s['hours'] as $h => $v): ?>
                    <div class="group relative flex h-full flex-1 flex-col justify-end" data-hour="<?= $h ?>">
                        <div class="rounded-t <?= $h === $peakHour ? 'bg-amber-400' : 'bg-sky-500/70 group-hover:bg-sky-400' ?>" style="height: <?= max(2, round($v / $hourMax * 100)) ?>%"></div>
                        <span class="pointer-events-none absolute -top-6 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-zinc-800 px-1.5 py-0.5 text-[11px] group-hover:block"><?= number_format($v, 1) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-1 flex gap-[3px] font-mono text-[10px] text-zinc-500">
                <?php foreach ($s['hours'] as $h => $v): ?>
                    <span class="flex-1 text-center js-hour" data-hour="<?= $h ?>"><?= $h % 3 === 0 ? $h : '' ?></span>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- how often people play -->
        <section class="<?= $card ?>">
            <h2 class="text-lg font-bold">How many games they played</h2>
            <p class="mb-3 text-xs text-zinc-500">All <?= $n($s['players']) ?> players, by their number of games</p>
            <ul class="space-y-2">
                <?php foreach ($s['buckets'] as $b => $c): ?>
                    <li class="flex items-center gap-3 text-sm">
                        <span class="w-24 shrink-0 whitespace-nowrap text-right font-mono text-zinc-400"><?= h($b) ?> <?= (string)$b === '1' ? 'game' : 'games' ?></span>
                        <span class="h-5 flex-1 rounded bg-white/5"><span class="block h-5 rounded bg-sky-500/70" style="width: <?= $pct($c) ?>%"></span></span>
                        <span class="w-24 shrink-0 font-mono"><?= $n($c) ?> <span class="text-zinc-500">(<?= $pct($c) ?>%)</span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- regulars -->
        <section class="<?= $card ?>">
            <h2 class="text-lg font-bold">How regularly</h2>
            <p class="mb-3 text-xs text-zinc-500">The <?= $n($s['month']) ?> players of the last <?= C::DAYS ?> days, by the number of days they played</p>
            <?php $dayMax = max(1, max($s['dayBuckets'])); ?>
            <ul class="space-y-2">
                <?php foreach ($s['dayBuckets'] as $b => $c): ?>
                    <li class="flex items-center gap-3 text-sm">
                        <span class="w-24 shrink-0 whitespace-nowrap text-right font-mono text-zinc-400"><?= h($b) ?> <?= (string)$b === '1' ? 'day' : 'days' ?></span>
                        <span class="h-5 flex-1 rounded bg-white/5"><span class="block h-5 rounded <?= in_array((string)$b, ['8-14', '15+'], true) ? 'bg-green-500/70' : 'bg-sky-500/70' ?>" style="width: <?= round($c / $dayMax * 100) ?>%"></span></span>
                        <span class="w-12 shrink-0 font-mono"><?= $n($c) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-3 text-xs text-zinc-500">Green: the regulars. On a normal day <?= $n($s['perDayAvg']) ?> different players play, on the busiest <?= $n($s['perDayMax']) ?>.</p>
        </section>

        <!-- servers -->
        <section class="<?= $card ?>">
            <h2 class="text-lg font-bold">Servers</h2>
            <p class="mb-3 text-xs text-zinc-500">The last <?= C::DAYS ?> days</p>
            <table class="w-full text-sm">
                <thead class="text-[11px] uppercase tracking-wide text-zinc-400">
                <tr><th class="py-1 text-left">Server</th><th class="py-1 text-right">Games</th><th class="py-1 text-right">Players</th></tr>
                </thead>
                <tbody>
                <?php foreach ($s['serverRows'] as $r): ?>
                    <tr class="border-t border-white/5">
                        <td class="py-1"><?= h($this->Layout->serverName($r['server_name'])) ?></td>
                        <td class="py-1 text-right font-mono"><?= $n($r['games']) ?></td>
                        <td class="py-1 text-right font-mono"><?= $n($r['players']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="mt-3 text-xs text-zinc-500">Players often play on several servers, so the column adds up to more than <?= $n($s['month']) ?>.</p>
        </section>
    </div>

    <!-- month by month -->
    <section class="<?= $card ?>">
        <h2 class="text-lg font-bold">Month by month</h2>
        <p class="mb-3 text-xs text-zinc-500">Players who played in the month <span class="inline-block h-2 w-2 rounded-sm bg-sky-500/70"></span>, of them new <span class="inline-block h-2 w-2 rounded-sm bg-green-500/80"></span></p>
        <div class="flex h-44 items-end gap-2">
            <?php foreach ($s['months'] as $m => $r): ?>
                <div class="group relative flex h-full flex-1 flex-col justify-end" title="<?= h(date('F Y', strtotime($m . '-01'))) ?>: <?= $r['active'] ?> players, <?= $r['new'] ?> new, <?= $r['games'] ?> games">
                    <div class="relative rounded-t bg-sky-500/70" style="height: <?= round($r['active'] / $monthMax * 100) ?>%">
                        <div class="absolute inset-x-0 bottom-0 bg-green-500/80" style="height: <?= $r['active'] ? round($r['new'] / $r['active'] * 100) : 0 ?>%"></div>
                    </div>
                    <span class="pointer-events-none absolute -top-6 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-zinc-800 px-1.5 py-0.5 text-[11px] group-hover:block"><?= $r['active'] ?> / <?= $r['new'] ?> new</span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-1 flex gap-2 text-[10px] text-zinc-500">
            <?php foreach ($s['months'] as $m => $r): ?>
                <span class="flex-1 text-center"><?= h(date('M', strtotime($m . '-01'))) ?></span>
            <?php endforeach; ?>
        </div>
        <p class="mt-3 text-xs text-zinc-500">The first and the current month are only partly in the logs.</p>
    </section>

    <?php if ($s['countries']): ?>
        <section class="<?= $card ?>">
            <h2 class="text-lg font-bold">Where the regulars come from</h2>
            <p class="mb-3 text-xs text-zinc-500">The <?= $n($s['regulars']) ?> regulars by country</p>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($s['countries'] as $c): ?>
                    <span class="rounded-lg bg-white/5 px-3 py-1.5 text-sm">
                        <?= $c['country'] ? $this->Layout->flag($c['country']) . ' ' . h(strtoupper($c['country'])) : '<span class="text-zinc-400">unknown</span>' ?>
                        <b class="ml-1 font-mono"><?= (int)$c['c'] ?></b>
                    </span>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <p class="text-xs text-zinc-500">
        A player is a ladder profile: names a player uses are joined to one profile when the server sees the same key, so somebody who changes names
        without being linked yet counts twice. Updated after every log import.
    </p>
</div>
</div>
<script>
// the hour chart in the visitor's own time zone
(() => {
    const off = -new Date().getTimezoneOffset() / 60;
    if (!off || off % 1) return;
    const bars = [...document.querySelectorAll('#hours [data-hour]')];
    const shift = (els) => {
        const parent = els[0].parentNode;
        const k = ((off % 24) + 24) % 24; // position h (local hour h) shows UTC hour h - off
        els.slice(24 - k).concat(els.slice(0, 24 - k)).forEach(el => parent.appendChild(el));
    };
    shift(bars);
})();

// the weekday x hour heatmap, in the visitor's time zone (whole-hour offsets)
(() => {
    const el = document.getElementById('heat');
    if (!el) return;
    const utc = JSON.parse(el.dataset.heat);
    let off = -new Date().getTimezoneOffset() / 60;
    if (off % 1) off = 0;
    const local = new Array(168).fill(0); // Monday 0:00 local first
    utc.forEach((v, i) => { local[((i + off) % 168 + 168) % 168] = v; });
    const max = Math.max(...local, 0.01);
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    let html = '<div class="grid gap-[2px]" style="grid-template-columns: 2.5rem repeat(24, minmax(0, 1fr))">';
    html += '<span></span>' + [...Array(24).keys()].map(h => `<span class="text-center font-mono text-[10px] text-zinc-500">${h % 3 === 0 ? h : ''}</span>`).join('');
    days.forEach((d, di) => {
        html += `<span class="pr-1 text-right text-[11px] leading-5 text-zinc-400">${d}</span>`;
        for (let h = 0; h < 24; h++) {
            const v = local[di * 24 + h];
            const a = v ? 0.06 + 0.9 * v / max : 0.03;
            html += `<span class="h-5 rounded-sm" style="background: rgba(56,189,248,${a.toFixed(2)})" title="${d} ${h}:00 – ${v.toFixed(1)} players"></span>`;
        }
    });
    el.innerHTML = html + '</div>';
    const best = local.indexOf(Math.max(...local));
    if (local[best] > 0) {
        document.getElementById('heat-best').textContent = ` · busiest: ${days[Math.floor(best / 24)]} ${best % 24}:00`;
    }
    document.querySelectorAll('.js-tz').forEach(el => el.textContent = Intl.DateTimeFormat().resolvedOptions().timeZone || 'local time');
})();
</script>
