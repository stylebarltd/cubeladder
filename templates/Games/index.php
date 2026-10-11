<?php
/**
 * The games list as cards, last played first (GamesController::index): each row opens
 * the game page, whose arrows go on in the same order.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Game[] $games
 * @var array $boards game id => GamesTable::scoreboard()
 * @var array $results game id => GameResultPicture::describe()
 * @var int $page
 * @var int $pages
 * @var int $total
 */
$this->assign('title', 'Games · cubeLadder');
$teamText = ['CLA' => 'text-red-400', 'RVSF' => 'text-blue-400'];
$today = new \Cake\I18n\Date();
$when = function (\Cake\I18n\DateTime $at) use ($today): string {
    $day = match (true) {
        $at->format('Y-m-d') === $today->format('Y-m-d') => 'Today',
        $at->format('Y-m-d') === $today->subDays(1)->format('Y-m-d') => 'Yesterday',
        default => $at->format('D, d M' . ($at->format('Y') !== $today->format('Y') ? ' Y' : '')),
    };

    return $day . ', ' . $at->format('H:i');
};
$pageUrl = fn(int $p) => $this->Url->build(['?' => $p > 1 ? ['page' => $p] : []]);
$pageBtn = 'flex items-center gap-2 rounded-lg border border-white/15 bg-black/60 px-3 py-2 text-sm font-semibold transition hover:bg-white/10';
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center bg-fixed text-white"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.65), rgba(0,0,0,1) 30%, rgba(0,0,0,.9)), url('/img/bullet.jpg');">
<div class="mx-auto flex w-full max-w-7xl flex-col gap-5 px-4 py-6 md:px-16 md:py-8">

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="drop-shadow-lg">
            <h1 class="text-3xl md:text-5xl font-extrabold tracking-wide">Games</h1>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-200">
                <span><i class="fa-solid fa-gamepad mr-1"></i><?= number_format($total) ?> games</span>
                <span><i class="fa-solid fa-arrow-down-wide-short mr-1"></i>last played first</span>
                <?php if ($pages > 1): ?><span><i class="fa-regular fa-file-lines mr-1"></i>page <?= $page ?> of <?= $pages ?></span><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!$games): ?>
        <p class="text-center text-zinc-500">No games to show yet.</p>
    <?php else: ?>
    <!-- one card per game: the map picture, the result, the top 3 -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($games as $game):
            $board = $boards[$game->id];
            $result = $results[$game->id];
            $url = $this->Url->build(['action' => 'view', $game->id]);
            $teams = $board['teams'];
            $winner = $board['winner'];
            $teamScore = fn(string $t) => (int)($board['flagMode'] ? ($teams[$t]['score']['flags'] ?? 0) : ($teams[$t]['score']['frags'] ?? 0));
            $top = array_slice(array_values(array_filter($board['rows'], fn($r) => empty($r['anonymous']))), 0, 3);
            $edge = $teams !== null ? ($winner === 'CLA' ? 'border-red-500/50' : ($winner === 'RVSF' ? 'border-blue-500/50' : 'border-white/15')) : 'border-white/15';
            ?>
            <article class="game-row group flex cursor-pointer flex-col overflow-hidden rounded-xl border <?= $edge ?> bg-black/70 backdrop-blur-[2px] transition hover:-translate-y-0.5 hover:border-white/40 hover:shadow-2xl"
                     data-href="<?= h($url) ?>">
                <!-- map picture with name, mode and time -->
                <div class="relative aspect-[16/7] overflow-hidden">
                    <img src="<?= $this->Layout->mapThumb($game->map->name ?? null) ?>" alt="" loading="lazy"
                         class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-black/10"></div>
                    <span class="absolute right-2 top-2 rounded-full bg-black/70 px-2 py-0.5 text-[11px] font-semibold text-zinc-200"><?= $this->Layout->gameModeIcon($game->mode) ?></span>
                    <div class="absolute inset-x-3 bottom-2">
                        <a href="<?= h($url) ?>" class="block truncate text-xl font-extrabold tracking-wide drop-shadow hover:text-blue-300"><?= h($result['map']) ?></a>
                        <div class="text-xs text-zinc-300" title="<?= h($game->started_at->format('j M Y, H:i')) ?> - <?= h($game->ended_at->format('H:i')) ?>">
                            <i class="far fa-clock mr-1"></i><?= h($when($game->ended_at)) ?>
                        </div>
                    </div>
                </div>

                <!-- result -->
                <div class="flex items-center justify-center gap-3 border-y border-white/10 px-3 py-2.5 font-mono">
                    <?php if ($teams !== null): ?>
                        <span class="w-14 text-right text-sm font-bold <?= $winner === 'RVSF' ? 'text-blue-400' : 'text-blue-400/50' ?>">RVSF</span>
                        <span class="text-2xl font-extrabold tabular-nums">
                            <span class="<?= $winner === 'RVSF' ? 'text-white' : 'text-zinc-400' ?>"><?= $teamScore('RVSF') ?></span><span class="text-zinc-500"> : </span><span class="<?= $winner === 'CLA' ? 'text-white' : 'text-zinc-400' ?>"><?= $teamScore('CLA') ?></span>
                        </span>
                        <span class="w-14 text-sm font-bold <?= $winner === 'CLA' ? 'text-red-400' : 'text-red-400/50' ?>">CLA</span>
                    <?php else: ?>
                        <span class="truncate text-sm font-bold text-amber-300"><i class="fa-solid fa-trophy mr-1.5"></i><?= h($result['title']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($teams !== null && $winner !== null && str_contains($result['title'], 'on frags')): ?>
                    <div class="-mt-px border-b border-white/10 py-0.5 text-center text-[10px] uppercase tracking-wider text-zinc-500"><span class="<?= $teamText[$winner] ?>"><?= $winner ?></span> won on frags</div>
                <?php elseif ($teams !== null && $winner === null): ?>
                    <div class="-mt-px border-b border-white/10 py-0.5 text-center text-[10px] uppercase tracking-wider text-zinc-500">draw</div>
                <?php endif; ?>

                <!-- top 3 -->
                <ol class="flex flex-1 flex-col gap-1.5 px-3 py-3 text-sm">
                    <?php foreach ($top as $i => $row): $p = $row['player']; ?>
                        <li class="flex min-w-0 items-center gap-2">
                            <span class="w-4 shrink-0 text-center text-xs font-bold <?= $i === 0 ? 'text-yellow-300' : 'text-zinc-500' ?>"><?= $i + 1 ?></span>
                            <img src="<?= $this->Layout->playerPicture($p) ?>" alt="" loading="lazy" class="h-6 w-6 shrink-0 rounded-full object-cover ring-1 <?= ($teamText[$row['team']] ?? null) ? ($row['team'] === 'CLA' ? 'ring-red-500/70' : 'ring-blue-500/70') : 'ring-white/20' ?>">
                            <?= $this->Html->link(h($p->name), ['controller' => 'Players', 'action' => 'view', $p->id],
                                ['escape' => false, 'class' => 'truncate font-semibold hover:text-blue-300', 'data-hover-card' => '/players/hover-card/' . $p->id]) ?>
                            <span class="shrink-0"><?= $this->Layout->flag($p->country) ?></span>
                            <span class="ml-auto shrink-0 font-mono text-xs tabular-nums text-zinc-400"><?= number_format($row['score']) ?> pts</span>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <!-- server, players, minutes -->
                <div class="flex items-center gap-4 border-t border-white/10 bg-white/[0.03] px-3 py-2 text-[11px] text-zinc-400">
                    <span class="truncate"><i class="fa-solid fa-server mr-1"></i><?= h($result['server']) ?></span>
                    <span class="ml-auto shrink-0"><i class="fa-solid fa-users mr-1"></i><?= count($board['rows']) ?></span>
                    <?php if ($result['minutes']): ?><span class="shrink-0"><i class="fa-regular fa-hourglass-half mr-1"></i><?= $result['minutes'] ?> min</span><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="flex items-center justify-between gap-3" aria-label="Pages">
            <?php if ($page > 1): ?>
                <a href="<?= h($pageUrl($page - 1)) ?>" id="page-prev" class="<?= $pageBtn ?>"><i class="fas fa-chevron-left text-xs"></i>Newer</a>
            <?php else: ?><span></span><?php endif; ?>
            <span class="text-sm text-zinc-400">page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?>
                <a href="<?= h($pageUrl($page + 1)) ?>" id="page-next" class="<?= $pageBtn ?>">Older<i class="fas fa-chevron-right text-xs"></i></a>
            <?php else: ?><span></span><?php endif; ?>
        </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
</div>

<!-- the player card on hovering a name, as in the rankings -->
<?= $this->element('hover_card_script') ?>
<?php $this->start('scriptBottom'); ?>
<script>
// the whole row opens the game (links inside it keep their own target)
document.querySelectorAll('.game-row').forEach(row => row.addEventListener('click', (e) => {
    if (e.target.closest('a')) return;
    if (e.ctrlKey || e.metaKey) window.open(row.dataset.href, '_blank');
    else location.href = row.dataset.href;
}));
// arrow keys: newer / older page
document.addEventListener('keydown', (e) => {
    if (e.altKey || e.ctrlKey || e.metaKey || e.target.closest('input, textarea, select')) return;
    const a = document.getElementById(e.key === 'ArrowLeft' ? 'page-prev' : e.key === 'ArrowRight' ? 'page-next' : '');
    if (a) location.href = a.href;
});
</script>
<?php $this->end(); ?>
