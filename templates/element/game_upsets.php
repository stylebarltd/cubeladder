<?php
/**
 * "Upset of the game" on the game page: a much weaker player (by skill
 * rating) won the duel against a much stronger one (UpsetService).
 *
 * @var \App\View\AppView $this
 * @var array $upsets UpsetService::forGame()
 */
if (empty($upsets)) {
    return;
}
$who = fn(array $p) => $this->Html->link(h($p['name']), ['controller' => 'Players', 'action' => 'view', $p['id']],
        ['escape' => false, 'class' => 'font-bold text-white hover:text-blue-300', 'data-hover-card' => '/players/hover-card/' . $p['id']])
    . ' <span class="font-mono text-xs ' . $this->Layout->ratingClass($p['skill']) . '">' . number_format($p['skill'], 1) . '</span>';
?>
<div class="flex flex-col gap-1.5 rounded-xl border border-fuchsia-400/40 bg-black/60 px-4 py-3 text-sm text-zinc-200 backdrop-blur-[2px]">
    <?php foreach ($upsets as $i => $u): ?>
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <?php if ($i === 0): ?>
                <span class="rounded bg-fuchsia-500/25 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-fuchsia-200"
                      title="A much weaker player (by skill rating, <?= number_format(\App\Service\UpsetService::GAP, 0) ?>+ lower) won the duel against a much stronger one">
                    <i class="fa-solid fa-bolt mr-1"></i>Upset of the game
                </span>
            <?php else: ?>
                <span class="w-[8.5rem] text-[10px] uppercase tracking-wider text-zinc-500">also</span>
            <?php endif; ?>
            <span><?= $who($u['winner']) ?> beat <?= $who($u['loser']) ?></span>
            <span class="font-mono font-extrabold text-fuchsia-300"><?= (int)$u['kills'] ?>&ndash;<?= (int)$u['deaths'] ?></span>
            <span class="text-xs text-zinc-400">in kills on each other &middot; <?= number_format($u['gap'], 1) ?> skill below</span>
        </div>
    <?php endforeach; ?>
</div>
