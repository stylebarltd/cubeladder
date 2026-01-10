<div class="max-w-6xl mx-auto px-6 py-10">

    <h1 class="text-3xl font-bold mb-2 text-white text-center">
        <?= h($game->map->name) ?>
        <?= $this->Layout->gameMode($game->mode) ?>
    </h1>

    <p class="text-sm mb-8 text-zinc-400 text-center">
        <?= $game->started_at->format('Y-m-d H:i') ?>
        – <?= $game->ended_at->format('H:i') ?>
        · <?= h($game->server_name) ?>
    </p>


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
