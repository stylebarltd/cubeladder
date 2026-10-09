<?php
/**
 * Bare game card for the player page (loaded into its recent-games box).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Game $game
 * @var array $board
 * @var \App\Model\Entity\PlayerStatsPerGame|null $stat the player's row
 * @var string $playerId
 * @var bool $inLast100
 */
$extra = null;
if ($stat) {
    $kd = (float)$stat->kd_ratio;
    $badge = $kd >= 2 ? '🔥 Carry' : ($kd >= 1.2 ? '👍 Solid' : ($kd >= 0.8 ? '😐 Avg' : '💀 Rough'));
    $kdClass = $kd >= 1.5 ? 'text-green-400' : ($kd >= 1 ? 'text-blue-300' : 'text-red-400');
    $cells = [
        'PTS' => ['val' => (int)$stat->total_score, 'cls' => 'text-sky-300 text-2xl'],
        'K' => ['val' => (int)$stat->kills],
        'D' => ['val' => (int)$stat->deaths],
        'K/D' => ['val' => number_format($kd, 2), 'cls' => $kdClass],
        'HS' => ['val' => (int)$stat->headshot],
        'SL' => ['val' => (int)$stat->slashed],
        'GB' => ['val' => (int)$stat->gibbed],
        'OBJ' => ['val' => (int)$stat->scored_with_the_flag + (int)$stat->returned_the_flag + (int)$stat->stole_the_flag],
        '⚠' => ['val' => (int)$stat->teamkills + (int)$stat->suicided],
        'MIN' => ['val' => $stat->minutes_played ?? '?'],
    ];
    $short = $stat->minutes_played !== null
        && $stat->minutes_played < \App\Model\Table\PlayerStatsPerGameTable::MIN_MINUTES;
    ob_start(); ?>
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-lg border border-sky-400/40 bg-black/60 px-4 py-3 backdrop-blur-[2px]">
        <span class="text-xs font-bold uppercase tracking-wider text-sky-300">your game</span>
        <?php foreach ($cells as $label => $c): ?>
            <span class="text-center">
                <span class="block text-[10px] uppercase tracking-wide text-zinc-400"><?= $label ?></span>
                <span class="block font-mono text-lg font-bold leading-tight <?= $c['cls'] ?? '' ?>"><?= $c['val'] ?></span>
            </span>
        <?php endforeach; ?>
        <span class="font-semibold"><?= $badge ?></span>
        <?php if ($inLast100): ?>
            <span class="rounded bg-white/10 px-1.5 py-0.5 font-rubik text-xs">the last 100</span>
        <?php endif; ?>
        <?php if ($short): ?>
            <span class="rounded bg-zinc-500/30 px-1.5 py-0.5 text-[10px] font-bold uppercase text-zinc-200 cursor-help"
                  title="Played less than <?= \App\Model\Table\PlayerStatsPerGameTable::MIN_MINUTES ?> minutes: kills and points count, but not as a game played">short &ndash; not counted as a game</span>
        <?php endif; ?>
        <?php if ($game->inaccurate): ?>
            <span class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-300 cursor-help"
                  title="<?= h('Inaccurate game data - does not count' . ($game->inaccurate_reason ? ': ' . $game->inaccurate_reason : '')) ?>">not counted</span>
        <?php endif; ?>
    </div>
    <?php $extra = ob_get_clean();
}
echo $this->element('game_card', [
    'game' => $game, 'board' => $board, 'link' => true,
    'compact' => true, 'highlight' => $playerId, 'extra' => $extra,
]);
