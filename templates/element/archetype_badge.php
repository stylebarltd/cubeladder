<?php
/**
 * Character badge (bin/cake CalculateRatings): the emblem from
 * webroot/img/characters, bigger than the other badges, with the name and
 * what the player stands out for. #1 in the stat: "The Butcher" with a crown.
 *
 * @var \App\View\AppView $this
 * @var string $archetype e.g. "Butcher"
 * @var int|null $pct percentile in its stat
 * @var int|null $rank place in its stat
 */
use App\Command\CalculateRatingsCommand;

$rank = $rank ?? null;
$label = CalculateRatingsCommand::archetypeLabel($archetype, $rank);
$text = CalculateRatingsCommand::archetypeText($archetype, $pct ?? null, $rank);
$color = match ($archetype) {
    'Headhunter' => 'text-sky-300 ring-sky-400/50 bg-sky-500/15',
    'Butcher' => 'text-red-300 ring-red-400/50 bg-red-500/15',
    'Bomber' => 'text-orange-300 ring-orange-400/50 bg-orange-500/15',
    'Pistolero' => 'text-emerald-300 ring-emerald-400/50 bg-emerald-500/15',
    'Berserker' => 'text-purple-300 ring-purple-400/50 bg-purple-500/15',
    default => 'text-zinc-200 ring-white/20 bg-white/10',
};
?>
<a href="/about#characters" class="inline-flex shrink-0 items-center gap-2 rounded-full py-1 pl-1 pr-4 tracking-normal ring-1 transition hover:brightness-125 <?= $color ?>"
   title="<?= h($text) ?> (last 100 CTF games) - how characters work">
    <span class="relative shrink-0">
        <img src="/img/characters/<?= h(strtolower($archetype)) ?>.svg" alt="" class="h-10 w-10 drop-shadow-[0_0_8px_currentColor]">
        <?php if ($rank === 1): ?>
            <i class="fa-solid fa-crown absolute -top-2 left-1/2 -translate-x-1/2 text-[11px] text-yellow-300 drop-shadow"></i>
        <?php endif; ?>
    </span>
    <span class="leading-tight">
        <span class="block text-sm font-extrabold uppercase"><?= h($label) ?></span>
        <span class="block text-[11px] font-medium text-zinc-300"><?= h($text) ?></span>
    </span>
</a>
