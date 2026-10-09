<?php
/**
 * Inbox: newest first. Weekly achievement messages (sent by
 * CalculateAchievementsCommand) are shown as achievement cards, everything
 * else as a message from the sender.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Message[] $messages
 */
$units = [
    'best_on_map' => 'points', 'total_score' => 'points', 'kd_ratio' => 'K/D',
    'headshot' => 'headshots', 'scored_with_the_flag' => 'flags', 'slashed' => 'knife kills',
    'gibbed' => 'gibs', 'suicided' => 'suicides', 'teamkills' => 'teamkills',
];
// the week an achievement was for: the last completed Monday - Sunday before it was sent
$weekOf = function ($created): string {
    $day = new DateTimeImmutable($created->format('Y-m-d'));
    $end = $day->format('w') === '0' ? $day : $day->modify('last sunday');
    $start = $end->modify('-6 days');

    return $start->format('M j') . ' – ' . $end->format($start->format('M') === $end->format('M') ? 'j' : 'M j');
};
$unread = count(array_filter($messages, fn($m) => !$m->is_read));
$deleteLink = fn($msg) => $this->Form->postLink(
    '<i class="fa-solid fa-trash-can"></i>',
    ['action' => 'delete', $msg->id],
    ['confirm' => 'Delete this message?', 'escape' => false, 'title' => 'Delete',
     'class' => 'inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-500 hover:bg-red-500/15 hover:text-red-400 transition']
);
$newBadge = '<span class="rounded-full bg-blue-500 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">New</span>';
?>
<main class="mx-auto w-full max-w-3xl flex-1 px-4 py-10 text-white sm:py-14">

    <div class="mb-8 flex items-end justify-between gap-4">
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
            <i class="fa-solid fa-inbox mr-2 text-blue-500"></i>Inbox
        </h1>
        <div class="text-sm text-zinc-400">
            <?= count($messages) ?> message<?= count($messages) === 1 ? '' : 's' ?><?= $unread ? ' · <span class="font-bold text-blue-400">' . $unread . ' new</span>' : '' ?>
        </div>
    </div>

    <?php if (empty($messages)): ?>
        <div class="rounded-xl border border-white/10 bg-black/50 px-6 py-16 text-center text-zinc-400">
            <i class="fa-regular fa-envelope-open mb-3 block text-4xl text-zinc-600"></i>
            No messages yet. Top a weekly ranking and your achievements land here.
        </div>
    <?php else: ?>

    <div class="space-y-4">
    <?php foreach ($messages as $msg):
        $a = $this->Achievements->fromMessage((string)$msg->body);
        $date = $msg->created->format('M j, Y · H:i');
        if ($a):
            $bg = $a['map'] ? $this->Layout->mapImage($a['map']) : null; ?>

        <!-- Achievement -->
        <article class="relative overflow-hidden rounded-2xl border <?= $msg->is_read ? 'border-white/10' : 'border-blue-500/60 shadow-[0_0_30px_rgba(59,130,246,.25)]' ?> bg-zinc-950">
            <?php if ($bg): ?>
                <img src="<?= h($bg) ?>" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
            <?php else: ?>
                <img src="/img/brand/cubeladder-mark-hero.webp" alt="" class="absolute -bottom-12 -right-8 h-52 w-auto opacity-20 sm:h-60">
            <?php endif; ?>
            <div class="absolute inset-0 bg-gradient-to-r from-zinc-950 via-zinc-950/85 to-zinc-950/30"></div>

            <div class="relative flex gap-4 p-5 sm:gap-6 sm:p-6">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-700 shadow-lg shadow-blue-900/50 sm:h-16 sm:w-16">
                    <?php $icon = $this->Achievements->iconClass($a['event_type']); ?>
                    <?php if (str_ends_with($icon, '.svg')): ?>
                        <img src="/img/achievements/<?= h($icon) ?>" alt="" class="h-9 w-9 sm:h-10 sm:w-10">
                    <?php else: ?>
                        <i class="<?= h($icon) ?> text-2xl text-white sm:text-3xl"></i>
                    <?php endif; ?>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-bold uppercase tracking-[0.2em] text-sky-300">
                        <span>🏆 Weekly achievement</span>
                        <span class="text-zinc-500">·</span>
                        <span class="text-zinc-300"><?= h($weekOf($msg->created)) ?></span>
                        <?php if (!$msg->is_read): ?><?= $newBadge ?><?php endif; ?>
                    </div>
                    <h2 class="mt-1 text-xl font-extrabold sm:text-2xl"><?= h($a['title']) ?></h2>

                    <div class="mt-3 flex flex-wrap items-end gap-x-6 gap-y-2">
                        <div>
                            <div class="text-[11px] uppercase tracking-wider text-zinc-400"><?= h($a['label']) ?></div>
                            <div class="font-mono text-4xl font-extrabold leading-none text-white sm:text-5xl">
                                <?= h($a['value']) ?><span class="ml-2 text-sm font-semibold text-zinc-400"><?= h($units[$a['event_type']] ?? '') ?></span>
                            </div>
                        </div>
                        <div class="text-sm text-zinc-300">
                            <?= $a['map'] ? 'You were the #1 player on this map last week.' : 'You ranked #1 for this last week.' ?>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                        <?php if ($a['map']): ?>
                            <?= $this->Html->link(
                                '<i class="fa-solid fa-map mr-1.5"></i>' . h($a['map']),
                                ['controller' => 'Maps', 'action' => 'index', '?' => ['map' => $a['map']]],
                                ['escape' => false, 'class' => 'rounded-md bg-blue-600 px-3 py-1.5 font-bold hover:bg-blue-500 transition']
                            ) ?>
                        <?php endif; ?>
                        <?= $this->Html->link(
                            '<i class="fa-solid fa-crown mr-1.5"></i>Hall of Fame',
                            '/players/hall_of_fame',
                            ['escape' => false, 'class' => 'rounded-md border border-white/20 bg-black/40 px-3 py-1.5 font-bold text-zinc-200 hover:border-white/40 transition']
                        ) ?>
                    </div>
                </div>

                <div class="flex shrink-0 flex-col items-end justify-between gap-2">
                    <span class="hidden whitespace-nowrap text-xs text-zinc-500 sm:block"><?= h($date) ?></span>
                    <?= $deleteLink($msg) ?>
                </div>
            </div>
        </article>

        <?php else: ?>

        <!-- Message -->
        <article class="rounded-2xl border <?= $msg->is_read ? 'border-white/10' : 'border-blue-500/60' ?> bg-black/60 p-5 backdrop-blur-[2px] sm:p-6">
            <div class="flex items-start gap-4">
                <?php if ($msg->sender): ?>
                    <img src="<?= h($this->Layout->playerPicture($msg->sender)) ?>" alt="" class="h-11 w-11 shrink-0 rounded-full object-cover ring-2 ring-white/10">
                <?php else: ?>
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-zinc-800 text-zinc-400"><i class="fa-solid fa-user-secret"></i></div>
                <?php endif; ?>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if ($msg->sender): ?>
                            <?= $this->Html->link(
                                h($msg->sender->name),
                                ['controller' => 'Players', 'action' => 'view', $msg->sender->id],
                                ['class' => 'font-bold hover:text-blue-400']
                            ) ?>
                            <?= $this->Layout->flag($msg->sender->country) ?>
                        <?php else: ?>
                            <span class="font-bold italic text-zinc-400">anonymous visitor</span>
                        <?php endif; ?>
                        <?php if (!$msg->is_read): ?><?= $newBadge ?><?php endif; ?>
                        <span class="text-xs text-zinc-500"><?= h($date) ?></span>
                    </div>
                    <div class="mt-2 break-words leading-relaxed text-zinc-200">
                        <?= $this->Layout->messageBody((string)$msg->body) ?>
                    </div>
                    <?php if ($msg->sender): ?>
                        <?= $this->Html->link(
                            '<i class="fa-solid fa-reply mr-1.5"></i>Reply',
                            ['controller' => 'Messages', 'action' => 'send', $msg->sender->id],
                            ['escape' => false, 'class' => 'mt-3 inline-block rounded-md border border-white/20 px-3 py-1.5 text-sm font-bold text-zinc-200 hover:border-white/40 transition']
                        ) ?>
                    <?php endif; ?>
                </div>

                <?= $deleteLink($msg) ?>
            </div>
        </article>

        <?php endif; ?>
    <?php endforeach; ?>
    </div>

    <?php endif ?>
</main>
