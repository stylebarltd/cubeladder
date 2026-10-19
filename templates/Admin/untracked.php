<?php
/**
 * Admin: players who ticked "Don't track me" on their profile. They are
 * hidden from the rankings, the Hall of Fame, achievements and their public
 * page, and show up as Anonymous on scoreboards. Only they can switch it back.
 *
 * @var \App\View\AppView $this
 * @var array $players rows of players with games, points, first_game, last_game
 * @var string $tab
 */
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 md:px-10 md:py-8">
    <?= $this->element('admin_nav') ?>

    <p class="max-w-3xl text-sm text-zinc-400">
        Players who chose <b>Don't track me</b> on their profile: hidden from the rankings, the Hall of Fame, achievements and
        their public page, shown as Anonymous on scoreboards. Their games still count for everyone else. Only they can switch it back.
        Latest game first.
    </p>

    <?php if (!$players): ?>
        <p class="text-zinc-500">Everyone is tracked.</p>
    <?php else: ?>
        <p class="text-sm text-zinc-300"><?= count($players) ?> <?= count($players) === 1 ? 'player' : 'players' ?></p>
        <div class="grid gap-2 md:grid-cols-2">
            <?php foreach ($players as $p): ?>
                <?= $this->element('admin_profile', ['p' => $p]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</div>
