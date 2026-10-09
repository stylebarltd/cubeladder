<?php
/**
 * A game shown like the in-game scoreboard: the map big in the background,
 * the scoreboard on top of it with opacity. Shared by Games index and view.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Game $game
 * @var array $board GamesTable::scoreboard()
 * @var bool $link Map name links to the game page (index)
 * @var bool $compact Box height instead of full screen (player page)
 * @var string|null $highlight Player id whose row is highlighted
 * @var string|null $extra HTML shown under the title (player's own stats)
 */
$link = $link ?? false;
$compact = $compact ?? false;
$highlight = $highlight ?? null;
$extra = $extra ?? null;

$mapUrl = $this->Layout->mapImage($game->map->name, (string)$game->id); // picture 1 or 2 per game, bullet artwork when there is none

// Literal class names so Tailwind keeps them
$teamStyle = [
    'CLA' => ['band' => 'bg-red-600/45', 'text' => 'text-red-400', 'border' => 'border-red-500/40'],
    'RVSF' => ['band' => 'bg-blue-600/45', 'text' => 'text-blue-400', 'border' => 'border-blue-500/40'],
];
$ratio = fn(int $k, int $d) => number_format($d > 0 ? $k / $d : $k, 2);
$ratioClass = fn(float $r) => $r >= 1.5 ? 'text-green-400' : ($r >= 1 ? 'text-blue-300' : 'text-red-400');
$flagMode = $board['flagMode'];
$mapName = $this->Layout->cleanMapName($game->map->name);

$rated = !empty($board['rated']);
// CTF rating of this game, colored like the player page's rating
$ratingClass = fn(?float $r) => match (true) {
    $r === null => 'text-zinc-500',
    $r >= 8.0 => 'text-sky-300',
    $r >= 7.0 => 'text-green-400',
    $r >= 6.0 => 'text-lime-300',
    $r >= 5.0 => 'text-yellow-300',
    $r >= 4.0 => 'text-orange-400',
    default => 'text-red-400',
};

$playerRow = function (array $row) use ($flagMode, $ratioClass, $highlight, $rated, $ratingClass) {
    $mine = $highlight !== null && (string)$row['player']->id === (string)$highlight;
    ob_start(); ?>
    <tr class="<?= $mine ? 'bg-sky-500/25 font-bold outline outline-1 -outline-offset-1 outline-sky-400' : ($row['is_mvp'] ? 'bg-yellow-400/10' : '') ?>">
        <td class="whitespace-nowrap px-1.5 py-1 sm:max-w-0 sm:w-full sm:px-2">
            <div class="flex items-center gap-1.5 sm:min-w-0">
                <?php if (!empty($row['anonymous'])): ?>
                    <span class="italic text-zinc-400 sm:truncate" title="This player opted out of tracking">Anonymous</span>
                <?php else: ?>
                    <?= $this->Html->link(
                        h($row['player']->name),
                        ['controller' => 'Players', 'action' => 'view', $row['player']->id],
                        ['class' => 'sm:truncate hover:text-blue-300', 'escape' => false]
                    ) ?>
                    <span class="shrink-0"><?= $this->Layout->flag($row['player']->country) ?></span>
                <?php endif; ?>
                <?= $row['is_mvp'] ? '<span class="shrink-0" title="Most points">🏆</span>' : '' ?>
            </div>
        </td>
        <?php if ($flagMode): ?><td class="px-1 py-1 sm:px-2 text-right"><?= $row['flags'] ?></td><?php endif; ?>
        <td class="px-1 py-1 sm:px-2 text-right"><?= $row['kills'] ?></td>
        <td class="px-1 py-1 sm:px-2 text-right"><?= $row['deaths'] ?></td>
        <?php /* phones: with a rating column, k/d (frags / deaths) makes room */ ?>
        <td class="px-1 py-1 sm:px-2 text-right <?= $rated ? 'hidden sm:table-cell' : '' ?> <?= $ratioClass($row['kd_ratio']) ?>"><?= number_format($row['kd_ratio'], 2) ?></td>
        <td class="px-1 py-1 sm:px-2 text-right font-bold text-sky-300"><?= $row['score'] ?></td>
        <?php if ($rated): ?>
            <td class="px-1 py-1 sm:px-2 text-right font-bold <?= $ratingClass($row['rating']) ?>"
                title="<?= $row['rating'] === null ? 'no rating: under 3 minutes played' : 'CTF rating of this game' ?>"><?= $row['rating'] !== null ? number_format($row['rating'], 1) : '–' ?></td>
        <?php endif; ?>
        <?php $short = $row['minutes'] !== null && $row['minutes'] < \App\Model\Table\PlayerStatsPerGameTable::MIN_MINUTES; ?>
        <td class="px-1 py-1 sm:px-2 text-right <?= $short ? 'text-zinc-500' : 'text-zinc-300' ?>"
            title="<?= $row['minutes'] === null ? 'minutes unknown' : ($short ? 'played under ' . \App\Model\Table\PlayerStatsPerGameTable::MIN_MINUTES . ' min: not counted as a game played' : (int)$row['minutes'] . ' minutes played') ?>"><?= $row['minutes'] ?? '–' ?></td>
    </tr>
    <?php return ob_get_clean();
};

$head = function () use ($flagMode, $rated) {
    ob_start(); ?>
    <tr class="text-zinc-400">
        <th class="px-1.5 py-1 sm:px-2 text-left font-normal">name</th>
        <?php if ($flagMode): ?><th class="px-1 py-1 sm:px-2 text-right font-normal"><span class="sm:hidden">fl</span><span class="hidden sm:inline">flags</span></th><?php endif; ?>
        <th class="px-1 py-1 sm:px-2 text-right font-normal"><span class="sm:hidden">k</span><span class="hidden sm:inline">frags</span></th>
        <th class="px-1 py-1 sm:px-2 text-right font-normal"><span class="sm:hidden">d</span><span class="hidden sm:inline">deaths</span></th>
        <th class="px-1 py-1 sm:px-2 text-right font-normal <?= $rated ? 'hidden sm:table-cell' : '' ?>"><span class="sm:hidden">k/d</span><span class="hidden sm:inline">ratio</span></th>
        <th class="px-1 py-1 sm:px-2 text-right font-normal">pts</th>
        <?php if ($rated): ?><th class="px-1 py-1 sm:px-2 text-right font-normal" title="CTF rating of this game, 0-10 (5.0 = an average game) - see About">rtg</th><?php endif; ?>
        <th class="px-1 py-1 sm:px-2 text-right font-normal" title="minutes played">min</th>
    </tr>
    <?php return ob_get_clean();
};
?>
<div class="relative overflow-hidden bg-zinc-900 text-white">
    <img src="<?= $mapUrl ?>" alt="" class="absolute inset-0 h-full w-full object-cover">
    <div class="absolute inset-0 bg-gradient-to-b from-black/50 via-black/10 to-black/60"></div>

    <!-- Fills the screen below the 4rem nav bar -->
    <div class="relative z-10 mx-auto flex w-full max-w-7xl flex-col gap-4 <?= $compact ? 'min-h-[560px] px-4 py-5 md:px-16 md:py-6' : 'min-h-[calc(100svh-4rem)] px-4 py-5 md:px-16 md:py-8' ?>">

        <!-- Map + game info -->
        <div class="drop-shadow-lg">
            <h2 class="text-3xl md:text-5xl font-extrabold tracking-wide">
                <?= $link
                    ? $this->Html->link($mapName, ['controller' => 'Games', 'action' => 'view', $game->id], ['class' => 'hover:text-blue-300', 'escape' => false])
                    : $mapName ?>
            </h2>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-200">
                <span><i class="far fa-calendar mr-1"></i><?= $game->started_at->format('D, d M Y') ?></span>
                <span>
                    <i class="far fa-clock mr-1"></i><?= $game->started_at->format('H:i') ?>
                    <?php if ($game->ended_at): ?>&ndash; <?= $game->ended_at->format('H:i') ?><?php endif; ?>
                </span>
                <span><i class="fa-solid fa-server mr-1"></i><?= $this->Layout->serverName($game->server_name) ?></span>
                <span><?= $this->Layout->gameModeIcon($game->mode) ?></span>
            </div>
        </div>

        <?= $this->fetch('gameNotice') ?>
        <?= $extra ?>

        <!-- Scoreboard -->
        <div class="mt-auto w-full rounded-lg border border-white/15 bg-black/60 p-3 md:p-5 font-mono text-xs md:text-sm backdrop-blur-[2px]">
            <div class="mb-3 flex flex-wrap items-center gap-x-2 text-zinc-200">
                <span>"<?= h(strtoupper((string)$game->mode)) ?>" on map <?= h($game->map->name) ?></span>
                <?php if ($board['winner']): ?>
                    <span class="<?= $teamStyle[$board['winner']]['text'] ?>"><?= $board['winner'] ?> wins!</span>
                <?php elseif ($board['teams']): ?>
                    <span class="text-zinc-400">draw</span>
                <?php endif; ?>
                <?php if ($game->inaccurate): ?>
                    <span class="rounded bg-amber-500/25 px-1.5 text-[10px] font-bold uppercase text-amber-300">not counted</span>
                <?php endif; ?>
            </div>

            <?php if ($board['teams']): ?>
                <div class="grid gap-4 lg:grid-cols-2">
                    <?php foreach ($board['teams'] as $teamKey => $team): $st = $teamStyle[$teamKey]; $sc = $team['score']; ?>
                        <div class="min-w-0 overflow-hidden rounded border <?= $st['border'] ?>">
                            <!-- Team totals -->
                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-3 py-3 md:px-4 md:py-4 <?= $st['band'] ?>">
                                <div>
                                    <div class="flex items-center gap-2 text-2xl md:text-4xl font-extrabold tracking-wider text-white drop-shadow">
                                        <?= $teamKey ?>
                                        <?php if ($board['winner'] === $teamKey): ?>
                                            <span class="rounded bg-yellow-400/25 px-2 py-0.5 text-xs md:text-sm font-bold tracking-normal text-yellow-300">🏆 wins</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-zinc-200"><?= (int)$sc['players'] ?> players at the end</div>
                                </div>
                                <div class="flex gap-4 md:gap-6 tabular-nums text-right">
                                    <?php
                                    $totals = [];
                                    if ($flagMode) {
                                        $totals['flags'] = (int)$sc['flags'];
                                    }
                                    $totals += [
                                        'frags' => (int)$sc['frags'],
                                        'deaths' => $team['deaths'],
                                        'ratio' => $ratio((int)$sc['frags'], $team['deaths']),
                                    ];
                                    foreach ($totals as $label => $val): ?>
                                        <div>
                                            <div class="<?= $label === 'flags' || (!$flagMode && $label === 'frags') ? 'text-3xl md:text-4xl' : 'text-xl md:text-2xl' ?> font-extrabold leading-none text-white drop-shadow"><?= $val ?></div>
                                            <div class="mt-1 text-[10px] uppercase tracking-wider text-zinc-200"><?= $label ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                            <table class="w-full tabular-nums">
                                <thead><?= $head() ?></thead>
                                <tbody>
                                <?php foreach ($team['rows'] as $row): ?>
                                    <?= $playerRow($row) ?>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($board['unassigned']): ?>
                    <p class="mt-2 text-[11px] text-zinc-400">
                        Left early (no team):
                        <?php foreach ($board['unassigned'] as $i => $row): ?>
                            <?= $i ? ', ' : '' ?><?= !empty($row['anonymous']) ? '<span class="italic">Anonymous</span>' : $this->Html->link(h($row['player']->name), ['controller' => 'Players', 'action' => 'view', $row['player']->id], ['class' => 'hover:text-blue-300', 'escape' => false]) ?>
                            (<?= $row['score'] ?> pts)
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            <?php elseif ($board['rows']): ?>
                <div class="overflow-x-auto">
                    <table class="w-full tabular-nums">
                        <thead><?= $head() ?></thead>
                        <tbody>
                        <?php foreach ($board['rows'] as $row): ?>
                            <?= $playerRow($row) ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-zinc-400">No ladder players in this game.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
