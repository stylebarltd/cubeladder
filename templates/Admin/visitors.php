<?php
/**
 * Admin: who uses the site (SiteVisits) - visitors per day (players and
 * guests), pages, where they come from, which players visit.
 *
 * @var \App\View\AppView $this
 * @var array $report SiteVisits::report()
 * @var string $tab
 */
$perDay = $report['perDay'];
$today = reset($perDay) ?: ['visitors' => 0, 'players' => 0, 'guests' => 0, 'views' => 0];
$counted = array_filter($perDay, fn($d) => $d['visitors'] > 0);
$avg = $counted ? array_sum(array_column($counted, 'visitors')) / count($counted) : 0;
$maxVisitors = max([1, ...array_values(array_column($perDay, 'visitors'))]);
$maxPage = max([1, ...array_map('intval', array_column($report['pages'], 'views'))]);
$totalSources = max(1, array_sum(array_column($report['sources'], 'visitors')));
$card = 'rounded-xl border border-white/10 bg-black/50 p-4';
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 md:px-10 md:py-8">
    <?= $this->element('admin_nav') ?>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-3xl text-sm text-zinc-400">
            Real visitors only: a page counts when the browser itself reports it, so crawlers drop out. Players count by their profile,
            guests by a code that changes every day (no IPs stored), admins not at all. Days are UTC.
        </p>
        <div class="flex gap-1 text-sm">
            <?php foreach ([7, 30, 90, 365] as $d): ?>
                <a href="?days=<?= $d ?>" class="rounded-lg px-2.5 py-1 font-semibold <?= $report['days'] === $d ? 'bg-white text-black' : 'bg-white/5 text-zinc-300 hover:bg-white/10' ?>"><?= $d ?> days</a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$counted): ?>
        <p class="<?= $card ?> text-zinc-400">No visits counted yet - they start with the first page someone opens.</p>
    <?php else: ?>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="<?= $card ?>"><div class="text-xs uppercase tracking-wide text-zinc-400">Today</div>
            <div class="text-3xl font-extrabold"><?= $today['visitors'] ?></div>
            <div class="text-xs text-zinc-400"><?= $today['players'] ?> players · <?= $today['guests'] ?> guests</div></div>
        <div class="<?= $card ?>"><div class="text-xs uppercase tracking-wide text-zinc-400">Per day</div>
            <div class="text-3xl font-extrabold"><?= number_format($avg, 0) ?></div>
            <div class="text-xs text-zinc-400">average of the days with visits</div></div>
        <div class="<?= $card ?>"><div class="text-xs uppercase tracking-wide text-zinc-400">Players</div>
            <div class="text-3xl font-extrabold text-sky-300"><?= count($report['players']) ?></div>
            <div class="text-xs text-zinc-400">different ones in <?= $report['days'] ?> days</div></div>
        <div class="<?= $card ?>"><div class="text-xs uppercase tracking-wide text-zinc-400">Come back</div>
            <div class="text-3xl font-extrabold text-green-400"><?= $report['returning'] ?></div>
            <div class="text-xs text-zinc-400">players on 2+ days</div></div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="<?= $card ?>">
            <h2 class="mb-3 font-bold">Per day</h2>
            <table class="w-full text-sm">
                <thead class="text-[11px] uppercase tracking-wide text-zinc-400">
                <tr><th class="py-1 text-left font-normal">Day</th><th class="w-2/5"></th>
                    <th class="px-1 py-1 text-right font-normal">Visitors</th><th class="px-1 py-1 text-right font-normal">Players</th>
                    <th class="px-1 py-1 text-right font-normal" title="On a phone or tablet">Phone</th><th class="px-1 py-1 text-right font-normal">Views</th></tr>
                </thead>
                <tbody>
                <?php foreach ($perDay as $day => $d): ?>
                    <tr class="border-t border-white/5">
                        <td class="whitespace-nowrap py-1 text-zinc-300"><?= date('D j M', strtotime($day)) ?></td>
                        <td class="px-2"><div class="flex h-2.5 overflow-hidden rounded bg-white/5" title="<?= $d['players'] ?> players, <?= $d['guests'] ?> guests">
                            <div class="bg-sky-400" style="width: <?= round($d['players'] / $maxVisitors * 100, 1) ?>%"></div>
                            <div class="bg-zinc-400" style="width: <?= round($d['guests'] / $maxVisitors * 100, 1) ?>%"></div></div></td>
                        <td class="px-1 py-1 text-right font-mono font-bold"><?= $d['visitors'] ?: '<span class="text-zinc-600">0</span>' ?></td>
                        <td class="px-1 py-1 text-right font-mono text-sky-300"><?= $d['players'] ?: '' ?></td>
                        <td class="px-1 py-1 text-right font-mono text-zinc-400"><?= $d['visitors'] ? round($d['phone'] / $d['visitors'] * 100) . '%' : '' ?></td>
                        <td class="px-1 py-1 text-right font-mono text-zinc-400"><?= $d['views'] ?: '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="mt-2 text-xs text-zinc-500"><span class="text-sky-300">■</span> players <span class="ml-2 text-zinc-400">■</span> guests</p>
        </section>

        <div class="flex flex-col gap-5">
            <section class="<?= $card ?>">
                <h2 class="mb-3 font-bold">Pages</h2>
                <?php foreach ($report['pages'] as $p): ?>
                    <div class="flex items-center gap-2 py-0.5 text-sm">
                        <span class="w-40 shrink-0 truncate font-mono text-xs text-zinc-300">/<?= h($p['page'] === 'home' ? '' : $p['page']) ?></span>
                        <div class="h-2.5 flex-1 overflow-hidden rounded bg-white/5"><div class="h-full bg-amber-400/80" style="width: <?= round($p['views'] / $maxPage * 100, 1) ?>%"></div></div>
                        <span class="w-14 text-right font-mono text-xs text-zinc-400"><?= (int)$p['views'] ?></span>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="<?= $card ?>">
                <h2 class="mb-1 font-bold">Where they come from</h2>
                <p class="mb-3 text-xs text-zinc-500">Per visitor and day: the site that linked them first. "direct" = typed in, a bookmark, or an app that sends no referrer.</p>
                <?php foreach ($report['sources'] as $s): ?>
                    <div class="flex items-center gap-2 py-0.5 text-sm">
                        <span class="w-40 shrink-0 truncate text-zinc-300"><?= h($s['source']) ?></span>
                        <div class="h-2.5 flex-1 overflow-hidden rounded bg-white/5"><div class="h-full bg-green-400/70" style="width: <?= round($s['visitors'] / $totalSources * 100, 1) ?>%"></div></div>
                        <span class="w-14 text-right font-mono text-xs text-zinc-400"><?= round($s['visitors'] / $totalSources * 100) ?>%</span>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>
    </div>

    <section class="<?= $card ?>">
        <h2 class="mb-1 font-bold">Players who visited</h2>
        <p class="mb-3 text-xs text-zinc-500">Recognised by their cookie or their game IP. Everyone else is a guest.</p>
        <?php if (!$report['players']): ?>
            <p class="text-sm text-zinc-500">None yet.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-[11px] uppercase tracking-wide text-zinc-400">
            <tr><th class="py-1 text-left font-normal">Player</th><th class="px-2 py-1 text-right font-normal">Days</th>
                <th class="px-2 py-1 text-right font-normal">Views</th><th class="px-2 py-1 text-right font-normal">Last visit (UTC)</th></tr>
            </thead>
            <tbody>
            <?php foreach ($report['players'] as $p): ?>
                <tr class="border-t border-white/5">
                    <td class="py-1"><?= $this->Html->link(h($p['name']) . ' ' . $this->Layout->flag($p['country']), ['controller' => 'Players', 'action' => 'view', $p['player_id']], ['escape' => false, 'class' => 'hover:text-sky-300']) ?></td>
                    <td class="px-2 py-1 text-right font-mono <?= (int)$p['days'] > 1 ? 'text-green-400' : 'text-zinc-400' ?>"><?= (int)$p['days'] ?></td>
                    <td class="px-2 py-1 text-right font-mono text-zinc-400"><?= (int)$p['views'] ?></td>
                    <td class="px-2 py-1 text-right font-mono text-xs text-zinc-400"><?= h(date('j M H:i', strtotime($p['last_seen']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
</div>
