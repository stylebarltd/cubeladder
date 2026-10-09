<div class="w-full">

    <?php $this->start('gameNotice'); ?>
    <?php if ($game->inaccurate): ?>
        <div class="rounded-xl border border-amber-500/40 bg-black/60 px-5 py-4 backdrop-blur-[2px] text-amber-200">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i>
            <strong>Inaccurate game data</strong> &ndash; this game does not count towards any ranking or stat.
            <?php if ($game->inaccurate_reason): ?>
                <span class="text-amber-300/80">(<?= h($game->inaccurate_reason) ?>)</span>
            <?php endif; ?>
            <?php if (!empty($notQualified)): ?>
                <ul class="mt-3 space-y-1 text-sm">
                    <?php foreach ($notQualified as $nq): ?>
                        <li>
                            <span class="inline-block rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide">Not qualified</span>
                            <?= h($nq['name']) ?> &ndash; <?= h($nq['title']) ?> <strong><?= (int)$nq['value'] ?></strong>
                            <span class="text-amber-300/70">(would be #<?= (int)$nq['rank'] ?> in the Hall of Fame)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php $this->end(); ?>

    <?php
    // Phones: top-right corner (clear of the scoreboard); larger: left / right sides
    $navBtn = 'absolute top-4 md:top-1/2 md:-translate-y-1/2 z-20 h-11 w-11 flex items-center justify-center rounded-full bg-black/60 hover:bg-black/80 text-white transition';
    ?>
    <div class="relative">
        <?= $this->element('game_card', ['game' => $game, 'board' => $board]) ?>

        <?php if ($prevId): ?>
            <?= $this->Html->link('<i class="fas fa-chevron-left"></i>', ['action' => 'view', $prevId],
                ['escape' => false, 'id' => 'game-prev', 'title' => 'Previous game', 'aria-label' => 'Previous game', 'class' => $navBtn . ' right-16 md:right-auto md:left-2']) ?>
        <?php endif; ?>
        <?php if ($nextId): ?>
            <?= $this->Html->link('<i class="fas fa-chevron-right"></i>', ['action' => 'view', $nextId],
                ['escape' => false, 'id' => 'game-next', 'title' => 'Next game', 'aria-label' => 'Next game', 'class' => $navBtn . ' right-2']) ?>
        <?php endif; ?>
    </div>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
document.addEventListener('keydown', (e) => {
    if (e.target.closest('input, textarea, select')) return;
    const a = document.getElementById(e.key === 'ArrowLeft' ? 'game-prev' : e.key === 'ArrowRight' ? 'game-next' : '');
    if (a) location.href = a.href;
});
</script>
<?php $this->end(); ?>
