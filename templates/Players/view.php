<?php

use Cake\Core\Configure;

?>
<div class="max-w-7xl px-6 py-10 mx-auto">

    <!-- Player Header -->
    <div class="grid grid-cols-1 gap-6 mb-10">

            <a href="/img/players/<?= $player->picture ?>">
                <img
                    src="<?= $this->Layout->playerPicture($player) ?>"
                    alt="<?= h($player->name) ?>"
                    class="w-32 h-32 rounded-full object-cover"
                >
            </a>



        <div>
            <h1 class="text-3xl font-bold text-white">
                <b><?= $player->rankAllTime ?>.</b>
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
                    ['class' => 'inline-flex items-center px-3 py-1.5 cursor-pointer mt-5
                     rounded-lg text-sm font-medium
                     text-blue-400 border border-blue-500/40
                     hover:bg-blue-500/10 hover:text-blue-300
                     transition']
                ) ?>

            <?php endif; ?>

        </div>
    </div>


    <?php if(!empty($player->achievements)):
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
        <h2 class="text-2xl font-bold text-white mb-4">Achievements</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-10">
            <?php foreach ($player->achievements as $achievement): ?>
                <?php if($achievement->event_type == 'best_on_map') continue; ?>
                <div class="bg-zinc-900 rounded-lg p-4 text-center hover:bg-zinc-800 transition">
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

                    </div>
                    <div class="text-2xl font-bold text-white">
                        <?php if($achievement->event_type == 'kd_ratio'): ?>
                            <?= h($achievement->count) ?>
                        <?php else: ?>
                            <?= h(round($achievement->count)) ?>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="bg-zinc-900 rounded-lg p-4 text-center hover:bg-zinc-800 transition">
                <div class="text-xl mb-1 text-zinc-300">
                    <img
                        src="/img/achievements/best_on_map.svg"
                        alt="<?= h($achievementLabels['best_on_map']) ?>"
                        title="<?= h($achievementLabels['best_on_map']) ?>"
                        class="w-6 h-6 block mx-auto"
                    >
                </div>
                <div class="text-xs uppercase tracking-wide text-zinc-400">
                    Best on Map
                </div>
                <div class="text-2xl font-bold text-white">
                    <?php
                    $mapCount = 0;
                    foreach ($player->achievements as $achievement): ?>
                        <?php $mapCount++; ?>
                    <?php endforeach; ?>
                    <?= $mapCount; ?> times
                </div>

<!--                --><?php //foreach ($player->achievements as $achievement): ?>
<!--                    --><?php //if($achievement->event_type != 'best_on_map') continue; ?>
<!---->
<!--                    <div class="text-xs uppercase tracking-wide text-zinc-400">-->
<!--                        --><?php //= date("dS M Y",strtotime($achievement->week_end)); ?>
<!---->
<!--                        --><?php //if(!empty($achievement->map->name)): ?>
<!---->
<!--                            --><?php //= h($achievement->map->name) ?>
<!--                        --><?php //endif ?>
<!--                        --><?php //= h(round($achievement->count)) ?><!-- pts <br>-->
<!---->
<!--                    </div>-->
<!---->
<!--                --><?php //endforeach; ?>
            </div>
        </div>
    <?php endif ?>


    <!--the last 100-->
    <?php if($player->rankTheLast100 > 0): ?>
    <div class="relative bg-[url('/img/bullet.jpg')] bg-cover bg-center bg-no-repeat rounded-xl p-8">

        <div class="absolute inset-0 bg-black/60 rounded-xl"></div>

        <div class="relative z-10">

            <h2 class="text-4xl font-bold text-white mb-4 font-rubik">
                the last 100
            </h2>

            <p class="text-sm mb-8 max-w-2xl text-zinc-300">
                <?= $lastGameDateRange['start'] ?> until <?= $lastGameDateRange['end'] ?>
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10 text-sm">

                <div class="bg-zinc-900/40 hover:bg-zinc-800/20 backdrop-blur rounded-lg p-4 text-center">
                    <div class="text-zinc-400">Rank</div>
                    <div class="text-4xl font-extrabold text-white"><?= $player->rankTheLast100 ?>.</div>
                </div>

                <div class="bg-zinc-900/40 hover:bg-zinc-800/20 backdrop-blur rounded-lg p-4 text-center">
                    <div class="text-zinc-400">Total Score</div>
                    <div class="text-2xl font-bold text-white"><?= $totalScore ?></div>
                </div>

                <div class="bg-zinc-900/40 hover:bg-zinc-800/20 backdrop-blur rounded-lg p-4 text-center">
                    <div class="text-zinc-400">K/D Ratio</div>
                    <div class="text-2xl font-bold text-white"><?= number_format($kdRatio, 2) ?></div>
                </div>

            </div>

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

            <?php if(!empty($statSums)): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-12">
                    <div class="bg-zinc-900/40 backdrop-blur rounded-lg p-4 text-center hover:bg-zinc-800/20 transition">

                        <?php
                        $kills = ['headshot','busted','shredded','sprayed','punctured','splattered','slashed','gibbed','teamkills','picked_off',];
                        $killCount=0;
                        foreach ($kills as $value): ?>
                            <?php $killCount+=$statSums[$value]; ?>
                        <?php endforeach; ?>
                        <div class="text-xl mb-1 text-zinc-300">
                            <i class="fa-solid <?= $statIcons['kills'] ?? 'fa-circle-dot' ?>"></i>
                        </div>
                        <div class="text-xs uppercase tracking-wide text-zinc-400">
                            Kills
                        </div>
                        <div class="text-2xl font-bold text-white">
                            <?= $killCount ?>
                        </div>
                        <div class="text-xs uppercase tracking-wide text-zinc-400">
                            <?php
                            foreach ($statSums as $field => $value): ?>
                                <?php if (!in_array($field, $kills) || $value === 0) continue; ?>
                                <?= h(str_replace('_', ' ', $field)) ?>
                                <?= $value ?><br>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-zinc-900/40 backdrop-blur rounded-lg p-4 text-center hover:bg-zinc-800/20 transition">
                        <?php
                        $flags = ['stole_the_flag','lost_the_flag','returned_the_flag','scored_with_the_flag',];
                        $flagCount=0;
                        foreach ($flags as $value): ?>
                            <?php $flagCount+=$statSums[$value]; ?>
                        <?php endforeach; ?>

                        <div class="text-xl mb-1 text-zinc-300">
                            <i class="fa-solid <?= $statIcons['scored_with_the_flag'] ?? 'fa-circle-dot' ?>"></i>
                        </div>
                        <div class="text-xs uppercase tracking-wide text-zinc-400">
                            Flags / Objectives
                        </div>
                        <div class="text-2xl font-bold text-white">
                            <?= $flagCount ?>
                        </div>
                        <div class="text-xs uppercase tracking-wide text-zinc-400">
                            <?php
                            $flags = ['stole_the_flag','lost_the_flag','returned_the_flag','scored_with_the_flag',];
                            foreach ($statSums as $field => $value): ?>
                                <?php if (!in_array($field, $flags) || $value === 0) continue; ?>
                                <?= h(str_replace('_', ' ', $field)) ?>
                                <?= $value ?><br>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="bg-zinc-900/40 backdrop-blur rounded-lg p-4 text-center hover:bg-zinc-800/20 transition">
                        <div class="text-xl mb-1 text-zinc-300">
                            <i class="fa-solid <?= $statIcons['deaths'] ?? 'fa-circle-dot' ?>"></i>
                        </div>
                        <div class="text-xs uppercase tracking-wide text-zinc-400">
                            <?= h(str_replace('_', ' ', 'deaths')) ?>
                        </div>
                        <div class="text-2xl font-bold text-white">
                            <?= $statSums['deaths'] ?>
                        </div>
                        <div class="text-xs uppercase tracking-wide text-zinc-400">
                            <?php
                            $kills = ['suicided',];
                            foreach ($statSums as $field => $value): ?>
                                <?php if (!in_array($field, $kills) || $value === 0) continue; ?>
                                <?= h(str_replace('_', ' ', $field)) ?>
                                <?= $value ?><br>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            <?php endif; ?>

        </div>
    </div>
    <?php endif; ?>













    <?php if (!empty($nemeses) || !empty($victims) || !empty($quotes)): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <?php
        $duelCards = [
            ['title' => '💀 Nemesis', 'sub' => 'kills ' . h($player->name) . ' the most', 'rows' => $nemeses ?? [], 'color' => 'text-red-400'],
            ['title' => '🎯 Favorite victims', 'sub' => h($player->name) . ' kills them the most', 'rows' => $victims ?? [], 'color' => 'text-green-400'],
        ];
        foreach ($duelCards as $card): if (empty($card['rows'])) continue; ?>
        <div class="bg-zinc-900 rounded-xl p-6">
            <h3 class="text-xl font-bold text-white mb-1"><?= $card['title'] ?></h3>
            <div class="text-xs text-zinc-500 mb-4"><?= $card['sub'] ?> <span class="text-zinc-600">· since Sep 2026</span></div>
            <div class="space-y-2">
                <?php foreach ($card['rows'] as $r): ?>
                <a href="/players/view/<?= h($r['id']) ?>" class="flex items-center gap-3 p-2 bg-zinc-800 rounded-lg hover:bg-zinc-700 transition">
                    <img src="<?= !empty($r['picture']) ? '/img/players/' . h($r['picture']) : '/img/acl.png' ?>" class="w-8 h-8 rounded-full object-cover" alt="">
                    <div class="flex-1 font-semibold text-white truncate"><?= h($r['name']) ?> <?= $this->Layout->flag($r['country']) ?></div>
                    <div class="<?= $card['color'] ?> font-mono text-lg"><?= (int)$r['n'] ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php // hidden for now - flip to true to show the in-game chat quotes ?>
        <?php if (false && !empty($quotes)): ?>
        <div class="bg-zinc-900 rounded-xl p-6">
            <h3 class="text-xl font-bold text-white mb-1">💬 Latest words</h3>
            <div class="text-xs text-zinc-500 mb-4">in-game chat · since Sep 2026</div>
            <div class="space-y-2">
                <?php foreach ($quotes as $q): if ($q['msg'] === '') continue; ?>
                <div class="p-2 bg-zinc-800 rounded-lg text-sm text-zinc-300">
                    &ldquo;<?= h($q['msg']) ?>&rdquo;
                    <?php if (!empty($q['at'])): ?><span class="text-xs text-zinc-600">· <?= h(date('M j', strtotime($q['at']))) ?></span><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Recent Games Table -->
    <h2 class="text-2xl font-bold text-white mb-4 mt-6"><?= h($player->name) ?> recent games</h2>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-zinc-700 rounded-xl overflow-hidden">

            <thead class="bg-zinc-900 text-zinc-300 text-sm">
            <tr>
                <th class="px-3 py-2 text-left hidden md:table-cell">Date</th>
                <th class="px-3 py-2"></th>
                <th class="px-3 py-2 text-left">Map</th>
                <th class="px-3 py-2 text-right">PTS</th>
                <th title="Kills" class="hover:underline cursor-help hidden md:table-cell">K</th>
                <th title="Death" class="hover:underline cursor-help hidden md:table-cell">D</th>
                <th title="Kill / Death Ratio" class="hover:underline cursor-help hidden md:table-cell">K/D</th>
                <th title="Headshots" class="hover:underline cursor-help hidden md:table-cell">HS</th>
                <th title="Slashes" class="hover:underline cursor-help hidden md:table-cell">SL</th>
                <th title="Gibbs" class="hover:underline cursor-help hidden md:table-cell">GB</th>
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
                $isPartOfTheLast100 = '<td></td>';
                if(isset($gamesDataGlobal[$stat->game_id])){
                    //$isPartOfTheLast100 = 'bg-blue-900 text-white';
                    $isPartOfTheLast100 = '<td class="px-3 py-2 md:table-cell text-center">
                        <p class="text-xs font-rubik">the last<br> '.Configure::read('Ladder.maxGamesToRank').'</p>
                    </td>';
                }

                ?>

                <tr class="hover:bg-zinc-700 transition">

                    <td class="px-3 py-2 hidden md:table-cell">

                        <?= $this->Html->link(
                            $stat->game->started_at->format('Y-m-d'),
                            ['controller' => 'games', 'action' => 'view', $stat->game->id],
                            ['class' => 'font-semibold hover:text-blue-400']
                        ) ?>

                    </td>

                    <?=$isPartOfTheLast100?>



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
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $stat->slashed ?></td>
                    <td class="px-3 py-2 text-right hidden md:table-cell"><?= $stat->gibbed ?></td>
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
