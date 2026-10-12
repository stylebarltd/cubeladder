<?php
/**
 * Admin: merge requests (AdminController::index).
 *
 * @var \App\View\AppView $this
 * @var array $requests open: pending (a player asked) / asked (we asked the player)
 * @var array $merges last merges (player_merges)
 * @var array $profiles id => PlayerMerger::profile()
 * @var string $tab
 */
$btn = 'rounded-lg px-3 py-1.5 text-sm font-semibold transition';
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 md:px-10 md:py-8">
    <?= $this->element('admin_nav') ?>

    <section class="flex flex-col gap-3">
        <h2 class="text-lg font-bold">Open requests</h2>
        <?php if (!$requests): ?><p class="text-sm text-zinc-500">Nothing open. Players send one with "This was my profile" on their old profile page.</p><?php endif; ?>
        <?php foreach ($requests as $r): ?>
            <div class="rounded-xl border <?= $r->status === 'pending' ? 'border-amber-400/40' : 'border-white/10' ?> bg-black/50 p-4">
                <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-zinc-400">
                    <?php if ($r->status === 'pending'): ?>
                        <span class="rounded bg-amber-400/20 px-2 py-0.5 font-bold uppercase text-amber-300">player asks</span>
                    <?php else: ?>
                        <span class="rounded bg-white/10 px-2 py-0.5 font-bold uppercase">we asked - no answer yet</span>
                    <?php endif; ?>
                    <span><?= h($r->created?->format('j M Y, H:i')) ?></span>
                </div>
                <div class="grid items-center gap-2 md:grid-cols-[1fr_auto_1fr]">
                    <?= $this->element('admin_profile', ['p' => $profiles[$r->from_id], 'label' => 'old']) ?>
                    <i class="fa-solid fa-arrow-right hidden text-zinc-500 md:block"></i>
                    <?= $this->element('admin_profile', ['p' => $profiles[$r->to_id], 'label' => 'stays']) ?>
                </div>
                <?php if ($r->note): ?><p class="mt-3 rounded bg-white/5 p-2 text-sm text-zinc-300"><?= nl2br(h($r->note)) ?></p><?php endif; ?>
                <div class="mt-3 flex flex-wrap gap-2">
                    <?= $this->Form->postLink('<i class="fa-solid fa-code-merge mr-1"></i>Merge', ['action' => 'approve', $r->id],
                        ['escape' => false, 'class' => "$btn bg-green-600 hover:bg-green-700", 'confirm' => 'Merge the old profile into the one that stays? This cannot be undone.']) ?>
                    <?= $this->Form->postLink('Reject', ['action' => 'reject', $r->id], ['class' => "$btn bg-white/10 hover:bg-white/20"]) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <?php if ($merges): ?>
    <section class="flex flex-col gap-2">
        <h2 class="text-lg font-bold">Last merges</h2>
        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="w-full text-sm">
                <thead class="bg-white/5 text-left text-xs uppercase text-zinc-400"><tr><th class="px-3 py-2">When</th><th class="px-3 py-2">Old profile</th><th class="px-3 py-2">Into</th><th class="px-3 py-2 text-right">Games</th><th class="px-3 py-2">By</th></tr></thead>
                <tbody class="divide-y divide-white/5">
                <?php foreach ($merges as $m): $to = $profiles[$m->to_id] ?? null; ?>
                    <tr>
                        <td class="whitespace-nowrap px-3 py-2 text-zinc-400"><?= h($m->merged_at?->format('j M, H:i')) ?></td>
                        <td class="px-3 py-2"><?= h($m->from_name) ?></td>
                        <td class="px-3 py-2"><?= $to ? $this->Html->link(h($to['name']), ['controller' => 'Players', 'action' => 'view', $to['id']], ['escape' => false, 'class' => 'hover:text-blue-300']) : '-' ?></td>
                        <td class="px-3 py-2 text-right tabular-nums"><?= (int)$m->games ?></td>
                        <td class="px-3 py-2 text-zinc-400"><?= h($m->source) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</div>
</div>
