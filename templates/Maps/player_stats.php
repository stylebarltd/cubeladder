<?php
/**
 * Hover card of a player on /maps (MapsController::playerStats).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Map $map
 * @var \App\Model\Entity\Player $player
 * @var \Cake\Datasource\EntityInterface|null $rating
 * @var array|null $stats
 */
?>
<div class="mb-3 flex items-center gap-3">
    <img src="<?= $this->Layout->playerPicture($player) ?>" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover">
    <div class="min-w-0 flex-1">
        <div class="truncate font-bold"><?= h($player->name) ?> <?= $this->Layout->flag($player->country) ?></div>
        <div class="text-[11px] text-zinc-400">on <?= h($this->Layout->cleanMapName($map->name)) ?></div>
    </div>
    <?php if ($rating): ?>
        <!-- overall CTF rating, rank and type -->
        <div class="shrink-0 text-right">
            <div class="font-mono text-2xl font-extrabold leading-none <?= $this->Layout->ratingClass((float)$rating->rating) ?>"><?= number_format((float)$rating->rating, 1) ?><?= $this->Layout->trendArrow($rating->trend !== null ? (float)$rating->trend : null, 'ml-0.5 align-top text-xs') ?></div>
            <div class="mt-1 text-[10px] text-zinc-400">CTF rating · #<?= (int)$rating->rank ?></div>
        </div>
    <?php endif; ?>
</div>
<?php if ($rating): ?>
    <div class="mb-3">
        <?= $this->element('player_type_badge', ['type' => $rating->type, 'label' => \App\Command\CalculateRatingsCommand::typeLabel($rating->type, (int)$rating->attack_pct, (int)$rating->defense_pct, (int)$rating->combat_pct)]) ?>
    </div>
<?php endif; ?>
<?php if ($stats): ?>
    <?= $this->element('map_player_stats', ['stats' => $stats, 'grid' => 'grid-cols-4']) ?>
<?php else: ?>
    <p class="text-sm text-zinc-400">No counted game on this map.</p>
<?php endif; ?>
