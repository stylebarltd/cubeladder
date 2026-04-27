<div class="max-w-7xl px-6 py-10 mx-auto">

    <?php
    $mapImage = WWW_ROOT . 'img/maps/' . $game->map->name . '.jpg';
    $mapUrl = file_exists($mapImage)
        ? '/img/maps/' . h($game->map->name) . '.jpg'
        : '/img/maps/placeholder.jpg';
    ?>

    <div class="relative rounded-xl overflow-hidden shadow-xl m-5">

        <!-- Background Image -->
        <div class="absolute inset-0">
            <img src="<?= $mapUrl ?>"
                 class="w-full h-full object-cover">
        </div>

        <!-- Dark Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/60 to-black/30"></div>

        <!-- Content -->
        <div class="relative z-10 py-16 px-6 text-white">

            <h1 class="text-4xl md:text-5xl font-extrabold tracking-wider drop-shadow-lg">
                <i class="fa-solid fa-map-location-dot"></i> <?= h($game->map->name) ?>
            </h1>

            <p class="mt-4 text-sm text-zinc-300">
                <i class="fa-solid fa-calendar mr-1"></i>
                <?= $game->started_at->format('Y-m-d') ?>

            </p>

            <p class="mt-2 text-sm text-zinc-300">
                <i class="fa-solid fa-clock mr-1"></i>
                <?= $game->started_at->format('H:i') ?> – <?= $game->ended_at->format('H:i') ?>
            </p>

            <p class="mt-2 text-sm text-zinc-300">
                <i class="fa-solid fa-server mr-1"></i>
                <?= $this->Layout->serverName($game->server_name) ?>
            </p>

            <p class="mt-2 text-sm">
                <?= $this->Layout->gameModeIcon($game->mode) ?>
            </p>

        </div>
    </div>

    <div class="overflow-x-auto p-4">
        <table class="min-w-full border border-zinc-700 rounded-xl overflow-hidden">

            <thead class="bg-zinc-900 text-zinc-300 text-sm">
            <tr>
                <th class="px-3 py-2 text-center">#</th>
                <th class="px-3 py-2 text-left">Player</th>
                <th class="px-3 py-2 text-right hover:underline cursor-help" title="Total Score">PTS</th>
                <th class="px-3 py-2 text-right hover:underline cursor-help hidden md:table-cell" title="Kills">K</th>
                <th class="px-3 py-2 text-right hover:underline cursor-help hidden md:table-cell" title="Deaths">D</th>
                <th class="px-3 py-2 text-right hover:underline cursor-help" title="Kill / Death Ratio">KDR</th>
                <th class="px-3 py-2 text-right hover:underline cursor-help hidden md:table-cell" title="Headshots">HS</th>
                <th class="px-3 py-2 text-right hover:underline cursor-help hidden md:table-cell" title="Gibbs">Gib</th>
                <th class="px-3 py-2 text-right hover:underline cursor-help hidden md:table-cell" title="Slashes">Slash</th>
                <th class="px-3 py-2 text-right hover:underline cursor-help hidden md:table-cell" title="Objectives (Flags)">OBJ</th>
                <th class="px-3 py-2 text-right hover:underline cursor-help hidden md:table-cell" title="Suicides + Teamkills">⚠</th>
                <th class="px-3 py-2 text-left">Result</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-zinc-700 bg-zinc-800 text-white text-sm">
            <?php foreach ($rankedStats as $row):

                ?>

                <?php
                $kd = $row['kd_ratio'];
                $kdClass = $kd >= 1.5 ? 'text-green-400'
                    : ($kd >= 1 ? 'text-blue-400' : 'text-red-400');
                $badge = $kd >= 2 ? '🔥 Carry'
                    : ($kd >= 1.2 ? '👍 Solid'
                        : ($kd >= 0.8 ? '😐 Avg' : '💀 Rough'));
                ?>

                <tr class="
        hover:bg-zinc-700 transition
        <?= $row['is_mvp'] ? 'bg-yellow-500/10' : '' ?>
    ">

                    <!-- Rank -->
                    <td class="px-3 py-2 text-center font-bold text-zinc-400">
                        <?= $row['rank'] ?>
                        <?= $row['is_mvp'] ? '🏆' : '' ?>
                    </td>

                    <!-- Player -->
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-3">
                            <img
                                src="<?= $this->Layout->playerPicture($row['player']) ?>"
                                class="w-8 h-8 rounded-full object-cover"
                                alt="<?= h($row['player']->name) ?>"
                            >

                            <div>
                                <?= $this->Html->link(
                                    h($row['player']->name),
                                    ['controller' => 'Players', 'action' => 'view', $row['player']->id],
                                    ['class' => 'font-semibold hover:text-blue-400', 'escape' => false]
                                ) ?>
                                <?= $this->Layout->flag($row['player']->country) ?>
                            </div>
                        </div>
                    </td>

                    <!-- Stats -->
                    <td class="px-3 py-2 text-right font-bold text-blue-400">
                        <?= $row['score'] ?>
                    </td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $row['kills'] ?></td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $row['deaths'] ?></td>
                    <td class="px-3 py-2 text-right <?= $kdClass ?>"><?= number_format($row['kd_ratio'], 2) ?></td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $row['headshots'] ?></td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $row['gibbed'] ?></td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $row['slashed'] ?></td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $row['objectives'] ?></td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $row['suicides'] ?></td>
                    <td class="px-3 py-2 text-right">


                        <?= $badge ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
