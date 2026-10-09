<?php
/**
 * Small CTF player type badge (bin/cake CalculateRatings), as on the player
 * page: icon + label (label from md up, icon only on phones).
 *
 * @var \App\View\AppView $this
 * @var string $type e.g. "All-Rounder"
 * @var string|null $label e.g. "All-Rounder · Attack" (CalculateRatingsCommand::typeLabel)
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
<span class="inline-flex shrink-0 items-center gap-1 rounded-full px-1 py-0.5 text-[10px] sm:px-1.5 font-semibold <?= $typeColor ?>" title="<?= h($label) ?>">
    <i class="fa-solid <?= $typeIcon ?>"></i><span class="hidden md:inline"><?= h($label) ?></span>
</span>
