<?php
/**
 * Small player card for the hover on the rankings (PlayersController::hoverCard).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Player $player
 * @var \Cake\Datasource\EntityInterface|null $rating
 * @var int $ratedPlayers
 * @var array $progress PlayerMilestoneProgress totals
 * @var string|null $lastSeen
 * @var array $badges last week's achievements
 * @var array $weapons PlayersController::weaponsOfChoice()
 */
$labels = [
    'total_score' => 'Most Points', 'games' => 'Most Games Played', 'teamkills' => 'Most Teamkills', 'kd_ratio' => 'Best KD Ratio',
    'gibbed' => 'Most Gibs', 'slashed' => 'Most Slashes', 'scored_with_the_flag' => 'Most Flags scored', 'headshot' => 'Most Headshots',
];
$seenLabel = null;
if ($lastSeen) {
    $days = (int)floor((time() - strtotime((string)$lastSeen)) / 86400);
    $seenLabel = $days <= 0 ? 'today' : ($days === 1 ? 'yesterday' : $days . ' days ago');
}
?>
<div class="flex items-center gap-3">
    <img src="<?= $this->Layout->playerPicture($player) ?>" alt="" class="h-14 w-14 shrink-0 rounded-full object-cover ring-2 ring-white/20">
    <div class="min-w-0 flex-1">
        <div class="truncate text-lg font-extrabold"><?= h($player->name) ?> <?= $this->Layout->flag($player->country) ?></div>
        <div class="text-[11px] text-zinc-400">
            <?= !empty($progress['hours']) ? number_format((float)$progress['hours']) . ' h played · ' : '' ?><?= !empty($progress['games']) ? number_format((int)$progress['games']) . ' games' : '' ?><?= $seenLabel ? ' · last seen ' . h($seenLabel) : '' ?>
        </div>
    </div>
    <?php if ($rating): ?>
        <div class="shrink-0 text-right">
            <div class="font-mono text-3xl font-extrabold leading-none <?= $this->Layout->ratingClass((float)$rating->rating) ?>"><?= number_format((float)$rating->rating, 1) ?><?= $this->Layout->trendArrow($rating->trend !== null ? (float)$rating->trend : null, 'ml-0.5 align-top text-xs') ?></div>
            <div class="mt-1 text-[10px] text-zinc-400">CTF rating · #<?= (int)$rating->rank ?> of <?= number_format($ratedPlayers) ?></div>
        </div>
    <?php endif; ?>
</div>

<?php if ($rating || $badges): ?>
    <div class="mt-3 flex flex-wrap items-center gap-1.5">
        <?php foreach ($badges as $event): ?>
            <?= $this->element('achievement_badge', ['event' => $event, 'title' => ($labels[$event] ?? $event) . ' last week', 'large' => true]) ?>
        <?php endforeach; ?>
        <?php if ($rating): ?>
            <?= $this->element('player_type_badge', ['type' => $rating->type, 'label' => \App\Command\CalculateRatingsCommand::typeLabel($rating->type, (int)$rating->attack_pct, (int)$rating->defense_pct, (int)$rating->combat_pct), 'full' => true]) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($rating): ?>
    <!-- attack / defense / combat against all rated players -->
    <div class="mt-3 grid grid-cols-[4.5rem_1fr] items-center gap-x-3 gap-y-1.5 text-[10px] uppercase tracking-wider text-zinc-400">
        <?php foreach (['Attack' => [$rating->attack_pct, 'bg-red-400'], 'Defense' => [$rating->defense_pct, 'bg-blue-400'], 'Combat' => [$rating->combat_pct, 'bg-amber-400']] as $label => [$value, $bar]): ?>
            <span><?= $label ?></span>
            <span class="h-2 w-full overflow-hidden rounded-full bg-white/10"><span class="block h-full rounded-full <?= $bar ?>" style="width: <?= max(2, (int)$value) ?>%"></span></span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($weapons['choice'])): ?>
    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-white/10 pt-3">
        <?php foreach ($weapons['choice'] as $weapon): ?>
            <div class="flex items-center gap-2">
                <img src="/img/weapons/<?= h($weapon['key']) ?>.svg" alt="" class="h-7 w-7 shrink-0">
                <div class="leading-tight">
                    <div class="text-xs font-bold"><?= h($weapon['name']) ?></div>
                    <div class="text-[10px] text-zinc-400"><?= $weapon['pct'] ?>% of kills</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($progress): ?>
    <div class="mt-3 grid grid-cols-4 gap-2 border-t border-white/10 pt-3 text-center">
        <?php foreach (['kills' => 'kills', 'flags' => 'flags', 'wins' => 'wins', 'mvp' => 'MVP'] as $key => $label): ?>
            <div>
                <div class="font-mono text-sm font-bold"><?= number_format((int)($progress[$key] ?? 0)) ?></div>
                <div class="text-[10px] uppercase tracking-wide text-zinc-400"><?= $label ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
