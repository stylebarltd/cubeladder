<?php

use Cake\Core\Configure;

?>
<div class="max-w-6xl mx-auto px-6 py-10">

    <!-- Player Header -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
        <img
            src="<?= $this->Layout->playerPicture($player) ?>"
            alt="<?= h($player->name) ?>"
            class="w-32 h-32 rounded-full object-cover"
        >

        <div>
            <h1 class="text-3xl font-bold text-white">
                <?= h($player->name) ?>
                <?= $this->Layout->flag($player->country) ?>
            </h1>
            <p class="text-sm text-zinc-400">
                <?= h($player->country) ?>
            </p>

            <?php if ($authPlayer && $authPlayer->id != $player->id) : ?>
                <?= $this->Html->link(
                    'Send Message',
                    ['controller' => 'Messages', 'action' => 'send',  $player->id],
                    ['class' => 'inline-flex items-center px-3 py-1.5 cursor-pointer m-5
                     rounded-lg text-sm font-medium
                     text-blue-400 border border-blue-500/40
                     hover:bg-blue-500/10 hover:text-blue-300
                     transition']
                ) ?>

            <?php endif; ?>

        </div>
    </div>


    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-10 text-sm">
        <div class="bg-zinc-900 border border-zinc-700 rounded-lg p-4 text-center">
            <div class="text-zinc-400">Total Score</div>
            <div class="text-2xl font-bold text-white"><?= $totalScore ?></div>
        </div>

        <div class="bg-zinc-900 border border-zinc-700 rounded-lg p-4 text-center">
            <div class="text-zinc-400">K/D Ratio</div>
            <div class="text-2xl font-bold text-white"><?= number_format($kdRatio, 2) ?></div>
        </div>
    </div>



    <!-- Achievements -->
    <?php
    $achievementLabels = [
        'total_score' => 'Most Points',
        'kills' => 'Most Kills',
        'teamkills' => 'Most Teamkills',
        'kd_ratio' => 'Best KD Ratio',
        'gibbed' => 'Most Gibs',
        'suicided' => 'Most Suicided',
        'slashed' => 'Most Slashes',
        'scored_with_the_flag' => 'Most Flags scored',
        'headshot' => 'Most Headshots',
        'best_on_map' => 'Best on Map',
    ];
    ?>
    <?php if(!empty($player->achievements)): ?>
        <h2 class="text-2xl font-bold text-white mb-4">Achievements</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-12">
            <?php foreach ($player->achievements as $achievement): ?>
            <?php if($achievement->event_type == 'best_on_map') continue; ?>
                <div class="bg-zinc-900 border border-zinc-700 rounded-lg p-4 text-center hover:bg-zinc-800 transition">
                    <div class="text-xl mb-1 text-zinc-300">
                        <img
                            src="/img/achievements/<?= $achievement->event_type ?>.svg"
                            alt="<?= h($achievementLabels[$achievement->event_type]) ?>"
                            title="<?= h($achievementLabels[$achievement->event_type]) ?>"
                            class="w-6 h-6 block mx-auto"
                        >
                    </div>
                    <div class="text-xs uppercase tracking-wide text-zinc-400">
                        <?= h($achievementLabels[$achievement->event_type]) ?><br>
                        <?= date("D, dS M Y",strtotime($achievement->week_end)); ?><br>

                        <?php if(!empty($achievement->map->name)): ?>

                            <?= h($achievement->map->name) ?><br>
                        <?php endif ?>

                    </div>
                    <div class="text-2xl font-bold text-white">
                        <?php if($achievement->event_type == 'kd_ratio'): ?>
                            <?= h($achievement->count) ?>
                        <?php elseif($achievement->event_type == 'best_on_map'): ?>
                            <?= h(round($achievement->count)) ?> pts
                        <?php else: ?>
                            <?= h(round($achievement->count)) ?>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif ?>


    <?php
    $statIcons = [
        'kills' => 'fa-skull',
        'headshot' => 'fa-crosshairs',
        'slashed' => 'fa-knife',
        'gibbed' => 'fa-bomb',
        'teamkills' => 'fa-skull-crossbones',
        'stole_the_flag' => 'fa-flag',
        'returned_the_flag' => 'fa-rotate-left',
        'scored_with_the_flag' => 'fa-trophy',
        'suicided' => 'fa-face-dizzy',
    ];
    ?>
    <h2 class="text-2xl font-bold text-white mb-4">Scores in the last <?=Configure::read('Ladder.maxGamesToRank') ?> games</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-12">
        <?php foreach ($statSums as $field => $value): ?>
            <?php if ($value === 0) continue; ?>
            <div class="bg-zinc-900 border border-zinc-700 rounded-lg p-4 text-center hover:bg-zinc-800 transition">
                <div class="text-xl mb-1 text-zinc-300">
                    <i class="fa-solid <?= $statIcons[$field] ?? 'fa-circle-dot' ?>"></i>
                </div>
                <div class="text-xs uppercase tracking-wide text-zinc-400">
                    <?= h(str_replace('_', ' ', $field)) ?>
                </div>
                <div class="text-2xl font-bold text-white">
                    <?= $value ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent Games Table -->
    <h2 class="text-2xl font-bold text-white mb-4"><?= h($player->name) ?> last 100 Games</h2>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-zinc-700 rounded-xl overflow-hidden">

            <thead class="bg-zinc-900 text-zinc-300 text-sm">
            <tr>
                <th class="px-3 py-2 text-left hidden md:table-cell">Date</th>
                <th class="px-3 py-2 text-left">Map</th>
                <th class="px-3 py-2 text-right">PTS</th>
                <th title="Kills" class="hover:underline cursor-help hidden md:table-cell">K</th>
                <th title="Death" class="hover:underline cursor-help hidden md:table-cell">D</th>
                <th title="Kill / Death Ratio" class="hover:underline cursor-help hidden md:table-cell">K/D</th>
                <th title="Headshots" class="hover:underline cursor-help hidden md:table-cell">HS</th>
                <th title="Flags scored / stolen / returned" class="hover:underline cursor-help hidden md:table-cell">OBJ</th>
                <th title="Teamkills & suicides" class="hover:underline cursor-help hidden md:table-cell">⚠</th>
                <th class="px-3 py-2 text-left">Result</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-zinc-700 bg-zinc-800 text-white text-sm">
            <?php foreach ($player->player_stats_per_game as $stat): ?>
                <?php
                $kd = $stat->kd_ratio;
                $kdClass = $kd >= 1.5 ? 'text-green-400'
                    : ($kd >= 1 ? 'text-blue-400' : 'text-red-400');

                $objectives = $stat->scored_with_the_flag + $stat->returned_the_flag + $stat->stole_the_flag;
                $penalties = $stat->teamkills + $stat->suicided;

                $badge = $kd >= 2 ? '🔥 Carry'
                    : ($kd >= 1.2 ? '👍 Solid'
                        : ($kd >= 0.8 ? '😐 Avg' : '💀 Rough'));
                ?>
                <tr class="hover:bg-zinc-700 transition">

                    <td class="px-3 py-2 hidden md:table-cell">

                        <?= $this->Html->link(
                            $stat->game->started_at->format('Y-m-d H:i'),
                            ['controller' => 'games', 'action' => 'view', $stat->game->id],
                            ['class' => 'font-semibold hover:text-blue-400']
                        ) ?>

                    </td>

                    <td class="px-3 py-2 flex items-center gap-2">
                        <img
                            src="<?= file_exists(WWW_ROOT . 'img/maps/' . $stat->game->map->name . '.jpg')
                                ? '/img/maps/' . $stat->game->map->name . '.jpg'
                                : '/img/maps/placeholder.jpg' ?>"
                            class="w-8 h-8 rounded object-cover border border-zinc-700 hidden md:table-cell"
                        >
                        <?= h($stat->game->map->name) ?>
                    </td>

                    <td class="px-3 py-2 text-right font-bold text-blue-400"><?= $stat->total_score ?></td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $stat->kills ?></td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $stat->deaths ?></td>

                    <td class="px-3 py-2 text-right font-bold <?= $kdClass ?> hidden md:table-cell">
                        <?= number_format($kd, 2) ?>
                    </td>

                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $stat->headshot ?></td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $objectives ?: '–' ?></td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $penalties ?: '–' ?></td>
                    <td class="px-3 py-2 font-semibold"><?= $badge ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Charts -->

    <div class="mt-8">
        <h2 class="text-2xl font-bold mb-4">Kill / Death Ratio</h2>
        <canvas id="kdChart" class="rounded-xl p-2 border border-blue-500"></canvas>
    </div>

    <div class="mt-8">
        <h2 class="text-2xl font-bold mb-4">Points</h2>
        <canvas id="scoreChart" class="rounded-xl p-2 border border-blue-500"></canvas>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const gamesData = <?= json_encode($gamesData) ?>;

    // K/D chart
    new Chart(document.getElementById('kdChart'), {
        type: 'line',
        data: {
            labels: gamesData.map(g => g.map_name + ' ' + g.played_at),
            datasets: [{
                label: 'K/D Ratio',
                data: gamesData.map(g => g.kd_ratio),
                borderColor: 'rgba(255,255,255,1)',
                backgroundColor: 'rgba(255,255,255,0.2)',
                tension: 0.2
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Score chart
    new Chart(document.getElementById('scoreChart'), {
        type: 'bar',
        data: {
            labels: gamesData.map(g => g.map_name + ' ' + g.played_at),
            datasets: [{
                label: 'Score',
                data: gamesData.map(g => g.score),
                backgroundColor: 'rgba(255,255,255,1)',
                borderColor: 'rgba(255,255,255,0.6)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });


</script>
