<?php
/**
 * Nemesis advanced stats (QR hunt unlock, level 3): PlayersController::duels, App\Service\NemesisStats.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Player $player
 * @var int $qrLevel
 * @var bool $unlocked
 * @var array|null $stats
 */
use App\Service\NemesisStats;
use App\Service\QrHunt;

$this->assign('title', $player->name . ' · Duels');
$panel = 'rounded-xl border border-white/15 bg-black/60 backdrop-blur-[2px]';
$avatar = fn($pic) => !empty($pic) ? '/img/players/' . h($pic) : '/img/acl.png';
$ratio = fn(int $k, int $d) => $d ? number_format($k / $d, 2) : ($k ? '&infin;' : '&ndash;');
$self = '/players/duels/' . h($player->id);
$need = QrHunt::UNLOCKS['nemesis'][0];
// a kills / deaths bar: green share = kills
$bar = function (int $k, int $d): string {
    $t = max(1, $k + $d);
    return '<div class="flex h-2 w-full overflow-hidden rounded-full bg-white/10"><div class="bg-green-500" style="width:' . round(100 * $k / $t, 1)
        . '%"></div><div class="bg-red-500" style="width:' . round(100 * $d / $t, 1) . '%"></div></div>';
};
$stat = fn($v, string $label, string $color = '') => '<div class="rounded-lg bg-white/5 px-4 py-2 text-center"><div class="font-mono text-2xl font-extrabold ' . $color . '">' . $v
    . '</div><div class="text-[10px] uppercase tracking-wider text-zinc-400">' . $label . '</div></div>';
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center bg-fixed text-white"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.55), rgba(0,0,0,1) 35%, rgba(0,0,0,.8)), url('/img/bullet.jpg');">
<div class="mx-auto w-full max-w-7xl px-4 py-6 md:px-16 md:py-8">

    <div class="mb-6 flex flex-wrap items-center gap-4">
        <img src="<?= $avatar($player->picture) ?>" class="h-14 w-14 rounded-full object-cover" alt="">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-extrabold tracking-wide md:text-4xl">💀 <?= h($player->name) ?> <?= $this->Layout->flag($player->country) ?></h1>
            <div class="text-sm text-zinc-400">Nemesis advanced stats &middot; every duel in detail</div>
        </div>
        <a href="/players/view/<?= h($player->id) ?>" class="rounded-md border border-white/15 bg-white/5 px-3 py-1.5 text-sm font-semibold hover:bg-white/10">&larr; Profile</a>
    </div>

<?php if (!$unlocked): ?>
    <section class="<?= $panel ?> max-w-2xl p-6">
        <h2 class="mb-2 text-xl font-bold"><i class="fa-solid fa-lock mr-2 text-amber-400"></i>Locked</h2>
        <p class="text-zinc-300">Nemesis advanced stats open at <b>QR hunt level <?= (int)$need ?></b>: find <?= (int)$need ?> of the QR codes hidden in our maps
            and scan them with your phone. <?= h($player->name) ?> is at level <?= (int)$qrLevel ?>.</p>
        <p class="mt-2 text-zinc-300">Then everyone can see their duels here: each opponent, per map, week by week and per weapon.</p>
        <a href="/run" class="mt-4 inline-block rounded-md bg-amber-500 px-4 py-2 font-bold text-black hover:bg-amber-400">Start the QR hunt</a>
    </section>
<?php else:
    $t = $stats['totals'];
    $duel = $stats['duel'] ?? null; ?>

    <div class="mb-6 flex flex-wrap gap-3">
        <?= $stat(number_format($t['kills']), 'duel kills', 'text-green-400') ?>
        <?= $stat(number_format($t['deaths']), 'duel deaths', 'text-red-400') ?>
        <?= $stat($ratio($t['kills'], $t['deaths']), 'K/D') ?>
        <?= $stat(number_format($t['opponents']), 'opponents') ?>
        <?= $stat(number_format($t['games']), 'games') ?>
    </div>

    <?php if ($duel): $r = $duel['rival']; ?>
    <!-- one rival head to head -->
    <section class="<?= $panel ?> mb-6 p-5" id="duel">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-bold"><?= h($player->name) ?> <span class="text-zinc-500">vs</span>
                <a href="/players/view/<?= h($r['id']) ?>" class="hover:text-blue-300"><?= h($r['name']) ?></a> <?= $this->Layout->flag($r['country']) ?></h2>
            <a href="<?= $self ?>" class="ml-auto text-xs text-zinc-400 hover:text-white">close &times;</a>
        </div>
        <?php if ($duel['kills'] + $duel['deaths'] === 0): ?>
            <p class="text-sm text-zinc-400">These two have not met in a game since Sep 2026.</p>
        <?php else: ?>
        <div class="mb-2 flex items-baseline justify-between font-mono text-3xl font-extrabold">
            <span class="text-green-400"><?= (int)$duel['kills'] ?></span><span class="text-sm font-normal text-zinc-400">K/D <?= $ratio($duel['kills'], $duel['deaths']) ?></span><span class="text-red-400"><?= (int)$duel['deaths'] ?></span>
        </div>
        <?= $bar($duel['kills'], $duel['deaths']) ?>
        <div class="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div>
                <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-zinc-400">Per map</h3>
                <?php foreach (array_slice($duel['maps'], 0, 10) as $m): ?>
                    <div class="mb-2"><div class="flex justify-between text-sm"><span><?= h($m['map']) ?></span><span class="font-mono"><span class="text-green-400"><?= $m['kills'] ?></span> : <span class="text-red-400"><?= $m['deaths'] ?></span></span></div><?= $bar($m['kills'], $m['deaths']) ?></div>
                <?php endforeach; ?>
            </div>
            <div>
                <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-zinc-400">Per weapon</h3>
                <?php if (array_sum(array_column($duel['weapons'], 'kills')) + array_sum(array_column($duel['weapons'], 'deaths')) === 0): ?>
                    <p class="text-sm text-zinc-400">Weapons are recorded since <?= date('j M Y', strtotime(NemesisStats::WEAPONS_SINCE)) ?>.</p>
                <?php else: foreach ($duel['weapons'] as $w): if ($w['kills'] + $w['deaths'] === 0) continue; ?>
                    <div class="mb-2"><div class="flex justify-between text-sm"><span><?= h($w['label']) ?></span><span class="font-mono"><span class="text-green-400"><?= $w['kills'] ?></span> : <span class="text-red-400"><?= $w['deaths'] ?></span></span></div><?= $bar($w['kills'], $w['deaths']) ?></div>
                <?php endforeach; endif; ?>
            </div>
            <div>
                <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-zinc-400">Last games together</h3>
                <?php foreach ($duel['games'] as $g): ?>
                    <a href="/games/view/<?= h($g['id']) ?>" class="mb-1 flex items-center justify-between rounded bg-white/5 px-2 py-1 text-sm hover:bg-white/10">
                        <span class="truncate"><?= h(date('j M', strtotime($g['started_at']))) ?> &middot; <?= h($g['map']) ?></span>
                        <span class="font-mono"><span class="text-green-400"><?= $g['kills'] ?></span> : <span class="text-red-400"><?= $g['deaths'] ?></span></span></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (count($duel['weeks']) > 1): $max = max(array_map(fn($w) => max($w['kills'], $w['deaths']), $duel['weeks'])) ?: 1; ?>
            <h3 class="mb-2 mt-6 text-sm font-bold uppercase tracking-wide text-zinc-400">Week by week</h3>
            <div class="flex h-24 items-end gap-1">
                <?php foreach ($duel['weeks'] as $w): ?>
                    <div class="flex h-full flex-1 items-end gap-px" title="week of <?= h(date('j M', strtotime($w['week']))) ?>: <?= $w['kills'] ?> kills, <?= $w['deaths'] ?> deaths">
                        <div class="flex-1 rounded-t bg-green-500" style="height:<?= round(100 * $w['kills'] / $max) ?>%"></div>
                        <div class="flex-1 rounded-t bg-red-500" style="height:<?= round(100 * $w['deaths'] / $max) ?>%"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php elseif ($this->request->getQuery('vs')): ?>
        <p class="mb-6 text-sm text-zinc-400">That player is not on cubeLadder (or keeps their stats private).</p>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- every opponent -->
        <section class="<?= $panel ?> p-5 lg:row-span-2">
            <h3 class="text-lg font-bold">⚔️ Opponents</h3>
            <div class="mb-3 text-xs text-zinc-400">most duels first &middot; pick one for the head to head</div>
            <?php if (!$stats['rivals']): ?><p class="text-sm text-zinc-400">No duels since Sep 2026 yet.</p><?php endif; ?>
            <div class="space-y-1.5">
                <?php foreach ($stats['rivals'] as $r): ?>
                    <a href="<?= $self ?>?vs=<?= h($r['id']) ?>#duel" class="block rounded-lg bg-white/5 px-2 py-1.5 hover:bg-white/10 <?= ($duel['rival']['id'] ?? null) === $r['id'] ? 'ring-1 ring-amber-400' : '' ?>">
                        <div class="flex items-center gap-3">
                            <img src="<?= $avatar($r['picture']) ?>" class="h-7 w-7 rounded-full object-cover" alt="">
                            <div class="min-w-0 flex-1 truncate font-semibold"><?= h($r['name']) ?> <?= $this->Layout->flag($r['country']) ?>
                                <span class="text-[11px] font-normal text-zinc-500"><?= $r['games'] ?> games &middot; last <?= h(date('j M', strtotime($r['last_met']))) ?></span></div>
                            <div class="font-mono text-sm"><span class="text-green-400"><?= $r['kills'] ?></span> : <span class="text-red-400"><?= $r['deaths'] ?></span></div>
                        </div>
                        <div class="mt-1 pl-10"><?= $bar($r['kills'], $r['deaths']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- per map -->
        <section class="<?= $panel ?> p-5">
            <h3 class="text-lg font-bold">🗺️ Per map</h3>
            <div class="mb-3 text-xs text-zinc-400">duel kills : deaths, and who rules that map</div>
            <div class="space-y-2">
                <?php foreach (array_slice($stats['maps'], 0, 12) as $m): ?>
                    <div>
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 text-sm">
                            <span class="font-semibold"><?= h($m['map']) ?></span>
                            <span class="font-mono"><span class="text-green-400"><?= $m['kills'] ?></span> : <span class="text-red-400"><?= $m['deaths'] ?></span></span>
                        </div>
                        <?= $bar($m['kills'], $m['deaths']) ?>
                        <div class="mt-0.5 flex justify-between text-[11px] text-zinc-400">
                            <span><?php if ($m['nemesis']): ?>nemesis <a class="text-red-300 hover:underline" href="<?= $self ?>?vs=<?= h($m['nemesis']['id']) ?>#duel"><?= h($m['nemesis']['name']) ?></a> (<?= $m['nemesis']['n'] ?>)<?php endif; ?></span>
                            <span><?php if ($m['prey']): ?>prey <a class="text-green-300 hover:underline" href="<?= $self ?>?vs=<?= h($m['prey']['id']) ?>#duel"><?= h($m['prey']['name']) ?></a> (<?= $m['prey']['n'] ?>)<?php endif; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- per weapon -->
        <section class="<?= $panel ?> p-5">
            <h3 class="text-lg font-bold">🔫 Per weapon</h3>
            <div class="mb-3 text-xs text-zinc-400">kills with it : deaths by it &middot; recorded since <?= date('j M Y', strtotime(NemesisStats::WEAPONS_SINCE)) ?></div>
            <?php if (array_sum(array_column($stats['weapons'], 'kills')) + array_sum(array_column($stats['weapons'], 'deaths')) === 0): ?>
                <p class="text-sm text-zinc-400">No games since then yet &ndash; this fills up with the next ones.</p>
            <?php else: foreach ($stats['weapons'] as $w): ?>
                <div class="mb-2"><div class="flex justify-between text-sm"><span><?= h($w['label']) ?></span><span class="font-mono"><span class="text-green-400"><?= $w['kills'] ?></span> : <span class="text-red-400"><?= $w['deaths'] ?></span></span></div><?= $bar($w['kills'], $w['deaths']) ?></div>
            <?php endforeach; endif; ?>
        </section>
    </div>

    <!-- week by week -->
    <?php if ($stats['weeks']): $max = max(array_map(fn($w) => max($w['kills'], $w['deaths']), $stats['weeks'])) ?: 1; ?>
    <section class="<?= $panel ?> mt-6 p-5">
        <h3 class="text-lg font-bold">📈 Week by week</h3>
        <div class="mb-3 text-xs text-zinc-400">duel kills (green) and deaths (red), the last <?= NemesisStats::WEEKS ?> weeks &middot; the nemesis of each week below</div>
        <div class="flex h-32 items-end gap-1.5">
            <?php foreach ($stats['weeks'] as $w): ?>
                <div class="flex h-full flex-1 items-end gap-px" title="week of <?= h(date('j M', strtotime($w['week']))) ?>: <?= $w['kills'] ?> kills, <?= $w['deaths'] ?> deaths">
                    <div class="flex-1 rounded-t bg-green-500" style="height:<?= round(100 * $w['kills'] / $max) ?>%"></div>
                    <div class="flex-1 rounded-t bg-red-500" style="height:<?= round(100 * $w['deaths'] / $max) ?>%"></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-1 flex gap-1.5">
            <?php foreach ($stats['weeks'] as $w): ?>
                <div class="min-w-0 flex-1 text-center text-[10px] leading-tight text-zinc-400">
                    <div><?= h(date('j M', strtotime($w['week']))) ?></div>
                    <?php if ($w['nemesis']): ?><a class="block truncate text-red-300 hover:underline" href="<?= $self ?>?vs=<?= h($w['nemesis']['id']) ?>#duel"><?= h($w['nemesis']['name']) ?></a><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <p class="mt-6 text-xs text-zinc-500">Duels are counted from the servers' kill messages since Sep 2026: teamkills and games marked inaccurate are left out,
        so are players who keep their stats private. Unlocked by <?= h($player->name) ?> in the QR hunt.</p>
<?php endif; ?>
</div>
</div>
