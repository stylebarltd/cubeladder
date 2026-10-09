<?php
// Link previews (Discord, WhatsApp, ...): the final scoreboard picture (GamesController::preview)
$site = rtrim((string)(\Cake\Core\Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');
$result = \App\Service\GameResultPicture::describe($game, $board);
$ogTitle = $result['map'] . ($result['mode'] !== '' ? ' · ' . $result['mode'] : '') . ' — ' . $result['title'];
$ogText = implode(' · ', array_filter([
    $result['server'],
    $game->started_at ? $game->started_at->format('j M Y, H:i') : '',
    $result['minutes'] > 0 ? $result['minutes'] . ' min' : '',
    !empty($board['rows']) ? 'best player ' . $board['rows'][0]['player']->name . ' (' . number_format($board['rows'][0]['score']) . ' points)' : '',
    $game->inaccurate ? 'not counted (inaccurate data)' : '',
]));
$this->assign('title', $ogTitle . ' · cubeLadder');
$this->start('meta'); ?>
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="cubeLadder">
    <meta property="og:title" content="<?= h($ogTitle) ?>">
    <meta property="og:description" content="<?= h($ogText) ?>">
    <meta property="og:url" content="<?= h($site . '/games/view/' . $game->id) ?>">
    <meta property="og:image" content="<?= h($site . '/games/preview/' . $game->id . '?v=' . substr(md5((string)json_encode($result['picture'])), 0, 8)) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="description" content="<?= h($ogText) ?>">
<?php $this->end(); ?>
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
