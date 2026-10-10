<?php
/**
 * Weekly achievement badge (last week's best, bin/cake CalculateAchievements)
 * in the cubeLadder logo blue, next to the player type badge: icon + short
 * label (label from md up, icon only on phones).
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
<span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-blue-500/25 px-1 py-0.5 text-[10px] font-semibold text-blue-200 ring-1 ring-blue-500/60 sm:px-1.5"
      title="<?= h($title ?? $short) ?>">
    <img src="/img/achievements/<?= h($event) ?>.svg" alt="" class="h-3 w-3"><span class="hidden md:inline"><?= h($short) ?></span>
</span>
<?php endif; ?>
