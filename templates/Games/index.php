<div class="max-w-6xl mx-auto px-6 py-10">
    <h1 class="text-3xl font-bold mb-8 text-white text-center">
        Games
        <small class="text-xs text-blue-500">by last played</small>
    </h1>

    <p class="text-sm mb-8 max-w-2xl mx-auto text-center">
        <?= $lastGameDateRange['start'] ?> until <?= $lastGameDateRange['end'] ?>
    </p>


    <div class="relative max-h-[70vh] overflow-y-auto scrollbar-hide rounded-xl border border-zinc-700 bg-zinc-800">
        <table class="min-w-full text-sm text-white">
            <thead class="sticky top-0 z-30 bg-zinc-900 shadow-md">
            <tr>
                <th class="px-3 py-2 text-left hidden md:table-cell">#</th>
                <th class="px-3 py-2 text-left">Date</th>
                <th class="px-3 py-2 text-left">Map</th>
                <th class="px-3 py-2 text-center hidden md:table-cell">Mode</th>
                <th class="px-3 py-2 text-center hidden md:table-cell">Players</th>
                <th class="px-3 py-2 text-left">Top Players</th>
                <th class="px-3 py-2 text-right hidden md:table-cell">Duration</th>
                <th class="px-3 py-2"></th>
            </tr>
            </thead>

            <tbody class="divide-y divide-zinc-700 bg-zinc-800 text-white text-sm">
            <?php
            $achievementLabels = [
                'total_score' => 'Most Points',
                'kills' => 'Most Kills',
                'teamkills' => 'Most Teamkills',
                'kd_ratio' => 'Best KD Ratio',
                'gibbed' => 'Most Gibs',
                'slashed' => 'Most Slashes',
                'scored_with_the_flag' => 'Most Flags scored',
                'headshot' => 'Most Headshots',
            ];
            ?>
            <?php $i = 1; foreach ($games as $game): ?>

            <?php
            $mapImage = WWW_ROOT . 'img/maps/' . $game->map->name . '.jpg';
            $mapUrl = file_exists($mapImage)
                ? '/img/maps/' . h($game->map->name) . '.jpg'
                : '/img/maps/placeholder.jpg';
            ?>

            <tr class="hover:bg-zinc-700 transition">
                <!-- Rank -->
                <td class="px-3 py-2 text-zinc-400 font-bold hidden md:table-cell">
                    <?= $i++ ?>.
                </td>

                <!-- Date -->
                <td class="px-3 py-2">
                    <?= $this->Html->link(
                        $game->started_at->format('Y-m-d'),
                        ['action' => 'view', $game->id],
                        ['class' => 'font-semibold hover:text-blue-400']
                    ) ?>
                    <div class="text-xs text-zinc-400">
                        <?= $game->started_at->format('H:i') ?> – <?= $game->ended_at->format('H:i') ?>
                    </div>
                </td>

                <!-- Map -->
                <td class="px-3 py-2">
                    <div class="flex items-center gap-2">
                        <img src="<?= $mapUrl ?>" class="w-10 h-10 rounded border border-zinc-600 hidden md:table-cell">
                        <span><?= h($game->map->name) ?><br> <?= h($game->server_name) ?></span>
                    </div>
                </td>

                <!-- Mode -->
                <td class="px-3 py-2 text-center hidden md:table-cell">
                    <?= $this->Layout->gameMode($game->mode) ?>
                </td>

                <!-- Player count -->
                <td class="px-3 py-2 text-center font-bold hidden md:table-cell">
                    <?= count($game->players) ?>
                </td>

                <!-- Top 3 -->
                <td class="px-3 py-2">
                    <div class="flex flex-col gap-0.5">
                        <?php foreach (array_slice($game->players, 0, 3) as $player): ?>
                            <div class="text-xs">
                                <?= $this->Html->link(
                                    h($player->name),
                                    ['controller' => 'Players', 'action' => 'view', $player->id],
                                    ['class' => 'hover:text-blue-400', 'escape' => false]
                                ) ?>
                                <?= $this->Layout->flag($player->country) ?>
                                <span class="text-zinc-400">
                        <?= $player->PlayerStatsPerGame['total_score'] ?> pts
                    </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </td>

                <!-- Duration -->
                <td class="px-3 py-2 text-right hidden md:table-cell">
                    <?= h($game->duration_minutes) ?> min
                </td>

                <!-- Expand -->
                <td class="px-3 py-2 text-right">
                    <button
                        class="toggle-row text-blue-400 hover:text-white"
                        data-target="game-<?= $game->id ?>">
                        ▶
                    </button>
                </td>
            </tr>
                <tr id="game-<?= $game->id ?>" class="hidden bg-zinc-900/60">
                    <td colspan="8" class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">

                            <?php foreach ($game->players as $player): ?>
                                <div class="flex border border-zinc-700 rounded px-2 py-1 gap-2">

                                    <?= $this->Html->link(
                                        h($player->name),
                                        ['controller' => 'Players', 'action' => 'view', $player->id],
                                        ['class' => 'hover:text-blue-400', 'escape' => false]
                                    ) ?>
                                    <?= $this->Layout->flag($player->country) ?>

                                    <span class="text-zinc-400">
                                        <?= $player->PlayerStatsPerGame['total_score'] ?> pts
                                    </span>

                                    <?php foreach ($achievementLabels as $stat => $icon): ?>
                                        <?php if (($game->stat_leaders[$stat] ?? null) === $player->id): ?>

                                            <img
                                                src="/img/achievements/<?= $stat ?>.svg"
                                                alt="<?= h($achievementLabels[$stat] ?? $stat) ?>"
                                                title="<?= h($achievementLabels[$stat] ?? $stat) ?>"
                                                class="w-5 h-5"
                                            >
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>



                            <div class="flex justify-between border border-zinc-700 rounded px-2 py-1 bg-zinc-600">
                                <?= $this->Html->link(
                                    "More Details",
                                    ['action' => 'view', $game->id],
                                    ['class' => 'font-semibold hover:text-blue-400']
                                ) ?>
                            </div>

                        </div>


                    </td>
                </tr>

            <?php endforeach; ?>


            </tbody>
    </table>
</div>
</div>
<script>
    document.querySelectorAll('.toggle-row').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = document.getElementById(btn.dataset.target);
            row.classList.toggle('hidden');
            btn.textContent = row.classList.contains('hidden') ? '▶' : '▼';
        });
    });
</script>
