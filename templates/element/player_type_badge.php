<?php
/**
 * Small CTF player type badge (bin/cake CalculateRatings): the icon, its
 * label slides out on hover ($full: icon + label, always shown).
 *
 * @var \App\View\AppView $this
 * @var string $type e.g. "All-Rounder"
 * @var string|null $label e.g. "All-Rounder · Attack" (CalculateRatingsCommand::typeLabel)
 * @var bool|null $full always show the label (hover cards)
 */
$label = $label ?? $type;
[$typeIcon, $typeColor] = match ($type) {
    'All-Rounder' => ['fa-star', 'bg-yellow-400/20 text-yellow-300'],
    'Flag Runner' => ['fa-person-running', 'bg-red-500/20 text-red-300'],
    'Defender' => ['fa-shield-halved', 'bg-blue-500/20 text-blue-300'],
    'Fragger' => ['fa-crosshairs', 'bg-orange-500/20 text-orange-300'],
    'Offensive Team Player' => ['fa-angles-right', 'bg-white/10 text-zinc-300'],
    default => ['fa-shield', 'bg-white/10 text-zinc-300'],
};
?>
<?php if (!empty($full)): ?>
<span class="inline-flex shrink-0 items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold <?= $typeColor ?>" title="<?= h($label) ?>">
    <i class="fa-solid <?= $typeIcon ?>"></i><?= h($label) ?>
</span>
<?php else: ?>
<!-- icon only; the label slides out in the same pill on hover -->
<span class="group/badge relative inline-flex shrink-0">
    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] <?= $typeColor ?>" aria-label="<?= h($label) ?>">
        <i class="fa-solid <?= $typeIcon ?>"></i>
    </span>
    <span class="pointer-events-none absolute left-0 top-1/2 z-30 hidden -translate-y-1/2 whitespace-nowrap rounded-full bg-zinc-950 shadow-lg group-hover/badge:inline-flex">
        <span class="inline-flex h-5 items-center gap-1 rounded-full pl-[5px] pr-2 text-[10px] font-semibold <?= $typeColor ?>">
            <i class="fa-solid <?= $typeIcon ?>"></i><?= h($label) ?>
        </span>
    </span>
</span>
<?php endif; ?>
