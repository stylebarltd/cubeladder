<div class="max-w-6xl mx-auto px-6 py-10">

    <h1 class="text-3xl font-bold mb-6 text-white text-center">
        Maps
        <small class="text-xs text-blue-500">by most played</small>
    </h1>

    <p class="text-sm mb-6 max-w-2xl mx-auto text-center text-zinc-400">
        <?= h($lastGameDateRange['start']) ?> until <?= h($lastGameDateRange['end']) ?>
    </p>

    <div class="relative max-h-[70vh] overflow-y-auto scrollbar-hide rounded-xl border border-zinc-700 bg-zinc-800">
        <table class="min-w-full text-sm text-white">
            <thead class="sticky top-0 z-30 bg-zinc-900 shadow-md">
            <tr>
                <th class="px-3 py-2 text-left hidden md:table-cell">#</th>
                <th class="px-3 py-2 text-left">Map</th>
                <th class="px-3 py-2 text-right">Played</th>
                <th class="px-3 py-2 text-left hidden md:table-cell">Top Players by Total Points</th>
            </tr>
            </thead>

            <!-- BODY -->
            <tbody class="divide-y divide-zinc-700 bg-zinc-800 text-white text-sm">

            <?php $i = 1; foreach ($maps as $map): ?>
                <?php if (empty($map->top_players)) continue; ?>

                <tr class="hover:bg-zinc-700 transition">

                    <!-- Rank -->
                    <td class="px-3 py-2 font-bold text-zinc-400 hidden md:table-cell">
                        <?= $i++ ?>.
                    </td>

                    <!-- Map -->
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-3">

                            <?php
                            $mapImage = WWW_ROOT . 'img/maps/' . $map->name . '.jpg';
                            $mapUrl = file_exists($mapImage)
                                ? '/img/maps/' . h($map->name) . '.jpg'
                                : '/img/maps/placeholder.jpg';
                            ?>

                            <img
                                src="<?= $mapUrl ?>"
                                alt="<?= h($map->name) ?>"
                                class="w-20 h-14 rounded object-cover border border-zinc-600"
                            >

                            <div>
                                <div class="font-semibold text-white">
                                    <?= h($map->name) ?>
                                </div>
                                <?php if (!empty($achievementMaps)): ?>
                                    <?php foreach ($achievementMaps as $achievement):
                                        if($achievement->map_id == $map->id): ?>
                                            <p class="text-sm text-blue-500"> 🏆
                                                <?= $this->Html->link(
                                                    h($achievement->player->name),
                                                    ['controller' => 'Players', 'action' => 'view', $achievement->player->id],
                                                    ['class' => 'hover:text-blue-400', 'escape' => false, 'title' => 'Last Week Top Player on Map']
                                                ) ?>
                                                <?= $this->Layout->flag($achievement->player->country) ?>
 (<?= round($achievement->count) ?>) </p>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>

                    <!-- Times played -->
                    <td class="px-3 py-2 text-right font-bold text-blue-400">
                        <?= h($map->times_played ?? count($map->games)) ?>
                    </td>

                    <!-- Top players -->
                    <td class="px-3 py-2 hidden md:table-cell">
                        <ul class="space-y-1">
                            <?php foreach ($map->top_players as $player): ?>
                                <li class="flex justify-between">

                                    <span>
                                        <?= $this->Html->link(
                                            h($player->player->name),
                                            ['controller' => 'Players', 'action' => 'view', $player->player->id],
                                            ['class' => 'hover:text-blue-400', 'escape' => false]
                                        ) ?>
                                        <?= $this->Layout->flag($player->player->country) ?>
                                    </span>

                                    <span class="font-bold text-zinc-300">
                                        <?= (int)$player->score ?> pts
                                    </span>

                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>
    </div>
</div>
