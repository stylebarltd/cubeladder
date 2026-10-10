<?php
/**
 * A player's stats on one map (MapsController::getPlayerMapStats): CTF rating
 * on the map against the overall one, place, points, games, K/D, flags,
 * headshots and the best game. "Your stats" on /maps and the hover card of
 * every player there (MapsController::playerStats).
 *
 * @var \App\View\AppView $this
 * @var array $stats
 * @var string|null $grid grid column classes
 * @var string|null $bestClass extra classes of the best game tile
 */
?>
<?php
$tiles = [
    ['Place', '#' . number_format($stats['place']), 'by points'],
    ['Points', number_format($stats['points']), null],
    ['Games', number_format($stats['games']), $this->Layout->duration($stats['minutes'])],
    ['K/D', number_format($stats['kd'], 2), number_format($stats['kills']) . ' / ' . number_format($stats['deaths'])],
    ['Flags', number_format($stats['flags']), null],
    ['Headshots', number_format($stats['headshots']), null],
];
?>
<div class="grid gap-2 <?= $grid ?? 'grid-cols-4' ?>">
    <?php if ($stats['rating'] !== null):
        $diff = $stats['overallRating'] !== null ? $stats['rating'] - $stats['overallRating'] : null; ?>
        <!-- average per-game CTF rating on this map, against the player's overall rating -->
        <div class="min-w-0 rounded bg-white/5 p-2"
             title="Average CTF rating of <?= $stats['ratedGames'] ?> rated CTF game<?= $stats['ratedGames'] === 1 ? '' : 's' ?> on this map<?= $stats['overallRating'] !== null ? ' (overall ' . number_format($stats['overallRating'], 1) . ')' : '' ?>">
            <div class="text-[10px] uppercase tracking-wide text-zinc-400">CTF rating</div>
            <div class="text-lg font-extrabold leading-tight tabular-nums <?= $this->Layout->ratingClass($stats['rating']) ?>"><?= number_format($stats['rating'], 1) ?></div>
            <div class="truncate text-[10px] text-zinc-400">
                <?php if ($diff !== null && abs($diff) >= 0.05): ?>
                    <span class="<?= $diff > 0 ? 'text-green-400' : 'text-red-400' ?>"><?= $diff > 0 ? '▲ +' : '▼ −' ?><?= number_format(abs($diff), 1) ?></span> vs <?= number_format($stats['overallRating'], 1) ?>
                <?php else: ?>
                    <?= $stats['ratedGames'] ?> rated game<?= $stats['ratedGames'] === 1 ? '' : 's' ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php foreach ($tiles as [$label, $value, $sub]): ?>
        <div class="min-w-0 rounded bg-white/5 p-2">
            <div class="text-[10px] uppercase tracking-wide text-zinc-400"><?= $label ?></div>
            <div class="text-lg font-extrabold leading-tight text-fuchsia-200 tabular-nums"><?= $value ?></div>
            <?php if ($sub): ?><div class="truncate text-[10px] text-zinc-400"><?= h($sub) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if ($stats['best']): ?>
        <div class="min-w-0 rounded bg-white/5 p-2 <?= $bestClass ?? '' ?>">
            <div class="text-[10px] uppercase tracking-wide text-zinc-400">Best game</div>
            <div class="text-lg font-extrabold leading-tight text-fuchsia-200 tabular-nums"><?= number_format($stats['best']['points']) ?> pts</div>
            <?= $this->Html->link(
                $stats['best']['playedAt'] ? h($stats['best']['playedAt']->format('d M Y')) : 'view game',
                ['controller' => 'Games', 'action' => 'view', $stats['best']['gameId']],
                ['class' => 'block truncate text-[10px] text-zinc-400 hover:text-fuchsia-200 hover:underline']
            ) ?>
        </div>
    <?php endif; ?>
</div>
