<?php
/**
 * Hover card of a player on /maps (MapsController::playerStats), in the
 * player card style: the player on this map, then their overall CTF rating
 * and type and their stats on the map.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Map $map
 * @var \App\Model\Entity\Player $player
 * @var \Cake\Datasource\EntityInterface|null $rating
 * @var array|null $stats
 */
use App\Command\CalculateRatingsCommand;

$this->start('mapBody'); ?>
<div class="rounded-xl border border-white/10 bg-[#0d1528] p-3">
    <?php if ($rating): ?>
        <!-- overall CTF rating, rank and type -->
        <div class="mb-2 flex items-center justify-between gap-3 border-b border-white/10 pb-2">
            <div class="flex items-baseline gap-2">
                <span class="font-mono text-3xl font-extrabold leading-none <?= $this->Layout->ratingClass((float)$rating->rating) ?>"><?= number_format((float)$rating->rating, 1) ?><?= $this->Layout->trendArrow($rating->trend !== null ? (float)$rating->trend : null, 'ml-0.5 align-top text-xs') ?></span>
                <span class="text-[10px] uppercase tracking-wider text-zinc-400">CTF rating · #<?= (int)$rating->rank ?></span>
            </div>
            <span class="truncate text-sm font-extrabold text-orange-300"><?= h(CalculateRatingsCommand::typeLabel($rating->type, (int)$rating->attack_pct, (int)$rating->defense_pct, (int)$rating->combat_pct)) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($stats): ?>
        <?= $this->element('map_player_stats', ['stats' => $stats, 'grid' => 'grid-cols-4']) ?>
    <?php else: ?>
        <p class="text-sm text-zinc-400">No counted game on this map.</p>
    <?php endif; ?>
</div>
<?php $this->end(); ?>
<?= $this->element('player_card_hover', [
    'player' => $player,
    'rating' => $rating,
    'map' => ['name' => $map->name],
    'subtitle' => 'on ' . $this->Layout->cleanMapName($map->name),
    'body' => $this->fetch('mapBody'),
]) ?>
