<?php
/**
 * Admin: messages to the admin (contact form, merge requests, replies), newest first.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Message[] $messages
 * @var string $tab
 */
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 md:px-10 md:py-8">
    <?= $this->element('admin_nav') ?>
    <?php if (!$messages): ?><p class="text-sm text-zinc-500">No messages.</p><?php endif; ?>
    <?php foreach ($messages as $msg): ?>
        <div class="rounded-xl border <?= $msg->is_read ? 'border-white/10' : 'border-blue-400/50' ?> bg-black/50 p-4">
            <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-zinc-400">
                <?php if (!$msg->is_read): ?><span class="rounded-full bg-blue-500 px-2 py-0.5 text-[10px] font-bold uppercase text-white">New</span><?php endif; ?>
                <?php if ($msg->sender): ?>
                    <?= $this->Html->link(h($msg->sender->name), ['controller' => 'Players', 'action' => 'view', $msg->sender->id], ['escape' => false, 'class' => 'font-bold text-white hover:text-blue-300']) ?>
                    <?= $this->Layout->flag($msg->sender->country) ?>
                <?php else: ?>
                    <span class="font-bold text-zinc-300">anonymous visitor</span>
                <?php endif; ?>
                <span><?= h($msg->created?->format('j M Y, H:i')) ?></span>
            </div>
            <div class="text-sm leading-relaxed text-zinc-200"><?= $this->Layout->messageBody((string)$msg->body) ?></div>
            <?php if ($msg->sender): ?>
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm text-blue-300 hover:text-blue-200"><i class="fa-solid fa-reply mr-1"></i>Reply</summary>
                    <?= $this->Form->create(null, ['url' => ['action' => 'reply', $msg->id], 'class' => 'mt-2 flex flex-col gap-2']) ?>
                    <?= $this->Form->textarea('body', ['rows' => 3, 'required' => true, 'class' => 'w-full rounded-lg border border-white/15 bg-zinc-900 p-2 text-sm focus:border-blue-400 focus:outline-none']) ?>
                    <button class="self-start rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-semibold hover:bg-blue-700">Send</button>
                    <?= $this->Form->end() ?>
                </details>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
</div>
