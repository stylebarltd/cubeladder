<?php
/**
 * Weekly achievement badge (last week's best, bin/cake CalculateAchievements)
 * in the cubeLadder logo blue, next to the player type badge: the icon,
 * its text slides out on hover ($large: icon + label, always shown).
 *
 * @var \App\View\AppView $this
 * @var string $event e.g. "kd_ratio"
 * @var string|null $title full text for the tooltip, e.g. "Best KD Ratio last week"
 * @var bool|null $large bigger, label always shown (player page header)
 */
$short = [
    'total_score' => 'Top scorer', 'games' => 'Most games', 'teamkills' => 'Most teamkills',
    'kd_ratio' => 'Best K/D', 'gibbed' => 'Most gibs', 'slashed' => 'Most slashes',
    'scored_with_the_flag' => 'Most flags', 'headshot' => 'Most headshots',
][$event] ?? $event;
?>
<?php if (!empty($large)): ?>
<span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-blue-500/25 px-3 py-1 text-sm font-semibold tracking-normal text-blue-200 ring-1 ring-blue-500/60"
      title="<?= h($title ?? $short) ?>">
    <img src="/img/achievements/<?= h($event) ?>.svg" alt="" class="h-4 w-4"><?= h($short) ?>
</span>
<?php else: ?>
<!-- icon only; the text slides out in the same pill on hover -->
<span class="group/badge relative inline-flex shrink-0">
    <span class="inline-flex items-center rounded-full bg-blue-500/25 p-1 ring-1 ring-blue-500/60">
        <img src="/img/achievements/<?= h($event) ?>.svg" alt="<?= h($title ?? $short) ?>" class="h-3 w-3">
    </span>
    <span class="pointer-events-none absolute left-0 top-1/2 z-30 hidden w-max -translate-y-1/2 whitespace-nowrap rounded-full bg-zinc-950 shadow-lg group-hover/badge:inline-flex">
        <span class="inline-flex items-center gap-1 rounded-full bg-blue-500/25 py-1 pl-1 pr-2 text-[10px] font-semibold text-blue-200 ring-1 ring-blue-500/60">
            <img src="/img/achievements/<?= h($event) ?>.svg" alt="" class="h-3 w-3"><?= h($title ?? $short) ?>
        </span>
    </span>
</span>
<?php endif; ?>
