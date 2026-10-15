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
                <b><?= $n($avgOnline) ?></b> players in games at once on average &ndash; around <b><?= $n($s['hours'][$peakHour]) ?></b> in the evening peak
                (<?= sprintf('%02d:00', $peakHour) ?> UTC), only <b><?= $n($s['hours'][$quietHour]) ?></b> at <?= sprintf('%02d:00', $quietHour) ?> UTC.
                Join at the peak to find full servers &ndash; <a href="/live" class="text-sky-300 hover:underline">Live</a> shows who's on right now.
            </p>
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
    document.querySelectorAll('.js-tz').forEach(el => el.textContent = Intl.DateTimeFormat().resolvedOptions().timeZone || 'local time');
})();
</script>
