<?php
/**
 * Ranking scoreboard on the bullet artwork - All Time Ranking and the
 * last 100 games.
 *
 * @var \App\View\AppView $this
 * @var iterable $players
 * @var array $stats key => [label, value(fn), title?, html? (value is HTML, not escaped)] (key = ?sort= value)
 * @var string $sort
 * @var array $activeStat
 * @var array $achievementPlayers
 * @var array $achievementLabels
 * @var string $title
 * @var string|null $titleClass
 * @var array $meta [[fa-icon, text], ...] shown under the title
 */
?>
<?php
$sortUrl = fn(string $key) => $this->Url->build(['?' => ['sort' => $key]]);
$shown = array_values(array_filter(
    is_array($players) ? $players : iterator_to_array($players),
    fn($p) => !empty($p->total_score) && $p->total_score > 0 && ($p->stats['kills'] ?? 0) >= 1
));
// On phones only Points / Rating (where shown) or Points / KDR / Kills (plus the sorted stat) fit
$mobileStats = array_unique(isset($stats['rating']) ? ['points', 'rating', $sort] : ['points', 'kd', 'kills', $sort]);
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center bg-fixed text-white"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.55), rgba(0,0,0,.25) 40%, rgba(0,0,0,.7)), url('/img/bullet.jpg');">

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 px-4 py-6 md:px-16 md:py-8">

        <!-- Title + sort -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="drop-shadow-lg">
                <h1 class="text-3xl md:text-5xl tracking-wide <?= $titleClass ?? 'font-extrabold' ?>"><?= h($title) ?></h1>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-200">
                    <span><i class="fa-solid fa-users mr-1"></i><?= count($shown) ?> players</span>
                    <?php foreach ($meta ?? [] as [$icon, $text]): ?>
                        <span><i class="fa-solid <?= $icon ?> mr-1"></i><?= h($text) ?></span>
                    <?php endforeach; ?>
                    <span><i class="fa-solid fa-arrow-down-wide-short mr-1"></i>by <?= h($activeStat['label']) ?></span>
                </div>
            </div>
            <form method="get" class="w-full sm:w-auto">
                <label class="flex items-center gap-2 rounded-lg border border-white/15 bg-black/60 px-3 py-2 text-sm backdrop-blur-[2px]">
                    <span class="text-zinc-400">Sort by</span>
                    <select name="sort" onchange="this.form.submit()"
                            class="flex-1 bg-transparent font-semibold text-white focus:outline-none [&>option]:bg-zinc-900">
                        <?php foreach ($stats as $key => $stat): ?>
                            <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $stat['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        </div>

        <!-- Scoreboard -->
        <div class="overflow-x-auto rounded-lg border border-white/15 bg-black/60 font-mono text-xs md:text-sm backdrop-blur-[2px]">
            <table class="w-full tabular-nums">
                <thead class="text-zinc-400">
                <tr class="border-b border-white/10">
                    <th class="px-1.5 py-2 sm:px-2 text-right font-normal">#</th>
                    <th class="px-1.5 py-2 sm:px-2 text-left font-normal">name</th>
                    <?php foreach ($stats as $key => $stat): ?>
                        <th class="px-1.5 py-2 sm:px-2 text-right font-normal <?= in_array($key, $mobileStats, true) ? '' : 'hidden sm:table-cell' ?>">
                            <a href="<?= $sortUrl($key) ?>" <?= !empty($stat['title']) ? 'title="' . h($stat['title']) . '"' : '' ?>
                               class="lowercase hover:text-white <?= $sort === $key ? 'font-bold text-sky-300' : '' ?>">
                                <?= $stat['label'] ?><?= $sort === $key ? ' ▾' : '' ?>
                            </a>
                        </th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($shown as $i => $player):
                    $rank = $i + 1;
                    $weapons = $this->Layout->weapon($player->stats);
                    ?>
                    <tr class="border-b border-white/5 hover:bg-white/5 <?= $rank === 1 ? 'bg-yellow-400/10' : '' ?>">
                        <td class="px-1.5 py-2 sm:px-2 text-right text-base font-extrabold <?= $rank <= 3 ? 'text-yellow-300' : 'text-zinc-500' ?>"><?= $rank ?></td>
                        <td class="px-1.5 py-2 sm:px-2">
                            <div class="flex items-center gap-3 min-w-0">
                                <img src="<?= $this->Layout->playerPicture($player) ?>" alt=""
                                     class="hidden h-9 w-9 shrink-0 rounded-full object-cover sm:block md:h-10 md:w-10">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <?= $this->Html->link(h($player->name), ['controller' => 'Players', 'action' => 'view', $player->id],
                                            ['escape' => false, 'class' => 'truncate font-semibold hover:text-blue-300']) ?>
                                        <span class="shrink-0"><?= $this->Layout->flag($player->country) ?></span>
                                        <?php if (!empty($player->player_type)): ?>
                                            <?= $this->element('player_type_badge', ['type' => $player->player_type, 'label' => $player->player_type_label ?? null]) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-zinc-400 sm:gap-x-3">
                                        <?php if (!empty($player->last_seen)):
                                            $lastSeen = $player->last_seen instanceof \Cake\I18n\DateTime
                                                ? $player->last_seen
                                                : new \Cake\I18n\DateTime($player->last_seen);
                                            $days = (int)floor((time() - $lastSeen->getTimestamp()) / 86400);
                                            $daysLabel = $days <= 0 ? 'today' : ($days === 1 ? '1 day ago' : $days . ' days ago');
                                            ?>
                                            <?= !empty($player->last_game_id)
                                                ? $this->Html->link('<i class="far fa-clock"></i> ' . h($daysLabel),
                                                    ['controller' => 'Games', 'action' => 'view', $player->last_game_id],
                                                    ['escape' => false, 'title' => $lastSeen->format('M j, Y H:i'), 'class' => 'whitespace-nowrap hover:text-blue-300'])
                                                : '<span class="whitespace-nowrap" title="' . h($lastSeen->format('M j, Y H:i')) . '"><i class="far fa-clock"></i> ' . h($daysLabel) . '</span>' ?>
                                        <?php endif; ?>
                                        <?php if (!empty($weapons['weapons'])): ?>
                                            <span class="flex items-center gap-1">
                                                <?php foreach ($weapons['weapons'] as $weapon): ?>
                                                    <img src="/img/weapons/<?= $weapon ?>.svg" alt="<?= h($weapon) ?>" class="h-4 w-4">
                                                <?php endforeach; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($achievementPlayers[$player->id])): ?>
                                            <span class="flex items-center gap-1">
                                                <?php foreach ($achievementPlayers[$player->id] as $ach => $count): ?>
                                                    <img src="/img/achievements/<?= $ach ?>.svg"
                                                         alt="<?= h($achievementLabels[$ach] ?? $ach) ?>"
                                                         title="<?= h($achievementLabels[$ach] ?? $ach) ?>" class="h-5 w-5">
                                                <?php endforeach; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <?php foreach ($stats as $key => $stat): ?>
                            <td class="px-1.5 py-2 sm:px-2 text-right <?= in_array($key, $mobileStats, true) ? '' : 'hidden sm:table-cell' ?>
                                <?= $sort === $key ? 'bg-sky-400/10 font-bold text-sky-300' : ($key === 'points' ? 'font-bold' : 'text-zinc-200') ?>">
                                <?= !empty($stat['html']) ? $stat['value']($player) : h($stat['value']($player)) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$shown): ?>
                    <tr><td colspan="<?= count($stats) + 2 ?>" class="px-2 py-6 text-center text-zinc-400">No ranked players yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
