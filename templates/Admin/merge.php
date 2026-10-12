<?php
/**
 * Admin: merge two profiles by hand (AdminController::merge).
 *
 * @var \App\View\AppView $this
 * @var string $fromInput
 * @var string $toInput
 * @var string|null $error
 * @var array|null $from
 * @var array|null $to
 * @var string $tab
 */
$input = 'w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none';
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 md:px-10 md:py-8">
    <?= $this->element('admin_nav') ?>
    <p class="text-sm text-zinc-400">A profile link, an id or the exact name. The old profile's games, points, awards and names move to the one that stays, then the old one is deleted; its link leads to the one that stays. Both pubkeys log in to it from then on.</p>

    <?= $this->Form->create(null, ['type' => 'get', 'url' => ['action' => 'merge'], 'class' => 'grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end']) ?>
        <label class="text-sm text-zinc-400">Old profile (goes away)<input name="from" value="<?= h($fromInput) ?>" class="<?= $input ?> mt-1" required></label>
        <label class="text-sm text-zinc-400">Profile that stays<input name="to" value="<?= h($toInput) ?>" class="<?= $input ?> mt-1" required></label>
        <button class="rounded-lg bg-white/10 px-4 py-2 text-sm font-semibold hover:bg-white/20">Preview</button>
    <?= $this->Form->end() ?>

    <?php if ($error): ?><p class="rounded-lg bg-red-500/15 p-3 text-sm text-red-300"><?= h($error) ?></p><?php endif; ?>

    <?php if ($from && $to && !$error): ?>
        <div class="rounded-xl border border-amber-400/40 bg-black/50 p-4">
            <div class="grid items-center gap-2 md:grid-cols-[1fr_auto_1fr]">
                <?= $this->element('admin_profile', ['p' => $from, 'label' => 'old']) ?>
                <i class="fa-solid fa-arrow-right hidden text-zinc-500 md:block"></i>
                <?= $this->element('admin_profile', ['p' => $to, 'label' => 'stays']) ?>
            </div>
            <?= $this->Form->create(null, ['url' => ['action' => 'merge'], 'class' => 'mt-3 flex gap-2']) ?>
                <?= $this->Form->hidden('from', ['value' => $from['id']]) ?>
                <?= $this->Form->hidden('to', ['value' => $to['id']]) ?>
                <button class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold hover:bg-green-700" onclick="return confirm('Merge? This cannot be undone.')"><i class="fa-solid fa-code-merge mr-1"></i>Merge</button>
                <a href="<?= $this->Url->build(['action' => 'merge', '?' => ['from' => $to['id'], 'to' => $from['id']]]) ?>" class="rounded-lg bg-white/10 px-4 py-2 text-sm font-semibold hover:bg-white/20"><i class="fa-solid fa-right-left mr-1"></i>Swap</a>
            <?= $this->Form->end() ?>
        </div>
    <?php endif; ?>
</div>
</div>
