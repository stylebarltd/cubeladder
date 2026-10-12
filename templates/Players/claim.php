<?php
/**
 * "This was my profile": the visitor asks to merge an old profile into
 * their current one (PlayersController::claim).
 *
 * @var \App\View\AppView $this
 * @var array $old
 * @var array $me
 * @var bool $pending already sent
 */
$this->assign('title', 'Merge profiles · cubeLadder');
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-8">
    <h1 class="text-3xl font-extrabold tracking-wide">Was this your profile?</h1>
    <p class="text-zinc-300">
        If you played as <b><?= h($old['name']) ?></b> before (another PC, a reinstall, a new name), we merge it into your profile:
        all games, points and awards move over, and both PCs log in to the same profile from then on. An admin checks it first.
    </p>
    <div class="grid items-center gap-2 md:grid-cols-[1fr_auto_1fr]">
        <?= $this->element('admin_profile', ['p' => $old, 'label' => 'old']) ?>
        <i class="fa-solid fa-arrow-right hidden text-zinc-500 md:block"></i>
        <?= $this->element('admin_profile', ['p' => $me, 'label' => 'you']) ?>
    </div>
    <?php if ($pending): ?>
        <p class="rounded-lg bg-amber-400/15 p-3 text-amber-200"><i class="fa-solid fa-hourglass-half mr-2"></i>Already sent - the admin merges it soon.</p>
    <?php else: ?>
        <?= $this->Form->create(null, ['class' => 'flex flex-col gap-3']) ?>
            <label class="text-sm text-zinc-400">Anything the admin should know? (optional)
                <?= $this->Form->textarea('note', ['rows' => 3, 'maxlength' => 1000, 'class' => 'mt-1 w-full rounded-lg border border-white/15 bg-zinc-900 p-2 text-sm text-white focus:border-blue-400 focus:outline-none']) ?>
            </label>
            <div class="flex gap-3">
                <button class="rounded-lg bg-green-600 px-4 py-2 font-semibold hover:bg-green-700"><i class="fa-solid fa-code-merge mr-1"></i>Yes, that was me</button>
                <?= $this->Html->link('Cancel', ['action' => 'view', $me['id']], ['class' => 'rounded-lg bg-white/10 px-4 py-2 font-semibold hover:bg-white/20']) ?>
            </div>
        <?= $this->Form->end() ?>
    <?php endif; ?>
</div>
</div>
