<?php
/**
 * STAT CONFIG
 * key = sort value
 */

use Cake\Core\Configure;

$stats = [
    'points' => [
        'label' => 'Points',
        'value' => fn($p) => $p->total_score,
    ],
    'kd' => [
        'label' => 'KDR',
        'value' => fn($p) => $p->stats['kd_ratio'] ?? 0,
    ],
    'headshot' => [
        'label' => 'HS',
        'value' => fn($p) => $p->stats['headshot'] ?? 0,
    ],
    'kills' => [
        'label' => 'Kills',
        'value' => fn($p) => $p->stats['kills'] ?? 0,
    ],
    'gibbed' => [
        'label' => 'Gib',
        'value' => fn($p) => $p->stats['gibbed'] ?? 0,
    ],
    'slashed' => [
        'label' => 'Slash',
        'value' => fn($p) => $p->stats['slashed'] ?? 0,
    ],
    'scored_with_the_flag' => [
        'label' => 'Flags',
        'value' => fn($p) => $p->stats['scored_with_the_flag'] ?? 0,
    ],
    'teamkills' => [
        'label' => 'TK',
        'value' => fn($p) => $p->stats['teamkills'] ?? 0,
    ],
];

$activeStat = $stats[$sort] ?? $stats['points'];

/**
 * Achievement labels
 */
$achievementLabels = [
    'total_score' => 'Most Points last week',
    'games' => 'Most Games Played last week',
    'teamkills' => 'Most Teamkills last week',
    'kd_ratio' => 'Best KD Ratio last week',
    'gibbed' => 'Most Gibs last week',
    'slashed' => 'Most Slashes last week',
    'scored_with_the_flag' => 'Most Flags scored last week',
    'headshot' => 'Most Headshots last week',
];
?>

<div class="max-w-7xl px-6 py-10 mx-auto">

    <h1 class="text-4xl font-bold text-white mb-4 font-rubik text-center">
        the last 100 games
    </h1>

    <p class="text-sm mb-6 text-center">
        <?= $lastGameDateRange['start'] ?> – <?= $lastGameDateRange['end'] ?>
    </p>





    <div class="text-center mb-4">
        <form method="get">
            <select name="sort" onchange="this.form.submit()"
                    class="bg-zinc-800 text-white px-3 py-2 rounded">
                <?php foreach ($stats as $key => $stat): ?>
                    <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>>
                        <?= $stat['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="relative max-h-[70vh] overflow-y-auto scrollbar-hide rounded-xl border border-zinc-700 bg-zinc-800">

        <table class="min-w-full text-sm text-white">

            <thead class="sticky top-0 z-30 bg-zinc-900 shadow-md">
            <tr>
                <th class="px-3 py-2 text-left">Rank</th>
                <th class="px-3 py-2 text-left">Player</th>
                <th class="px-3 py-2 hidden md:table-cell text-center">Weapons</th>
                <th class="px-3 py-2 hidden md:table-cell text-center">AM</th>

                <?php foreach ($stats as $key => $stat): ?>
                    <th class="px-3 py-2 text-right
            <?= $sort === $key ? 'text-blue-400 bg-zinc-800/60' : '' ?>
            <?= $sort !== $key ? 'hidden md:table-cell' : '' ?>">
                        <?= $stat['label'] ?>
                    </th>
                <?php endforeach; ?>
            </tr>
            </thead>

            <tbody class="divide-y divide-zinc-700">

            <?php $i = 1; foreach ($players as $player):

                if (
                    empty($player->total_score) ||
                    $player->total_score < 0 ||
                    $player->stats['kills'] < 1
                ) continue;

                $weapons = $this->Layout->weapon($player->stats);
                ?>

                <tr class="hover:bg-zinc-700 transition">

                    <td class="px-3 py-2 text-zinc-400 font-bold"><?= $i++ ?>.</td>

                    <td class="px-3 py-2">
                        <div class="flex items-center gap-3">
                            <img src="<?= $this->Layout->playerPicture($player) ?>"
                                 class="w-10 h-10 rounded-full object-cover">
                            <?= $this->Html->link(
                                h($player->name) . ' ' . $this->Layout->flag($player->country),
                                ['controller' => 'Players', 'action' => 'view', $player->id],
                                ['escape' => false, 'class' => 'font-semibold hover:text-blue-400']
                            ) ?>
                        </div>
                    </td>

                    <!-- Weapons -->
                    <td class="px-3 py-2 hidden md:table-cell">
                        <div class="flex justify-center gap-1">
                            <?php foreach ($weapons['weapons'] as $weapon): ?>
                                <img src="/img/weapons/<?= $weapon ?>.svg" class="w-5 h-5">
                            <?php endforeach; ?>
                        </div>
                    </td>

                    <!-- Achievements -->
                    <td class="px-3 py-2 hidden md:table-cell">
                        <div class="flex justify-center gap-1">
                            <?php if (!empty($achievementPlayers[$player->id])): ?>
                                <?php foreach ($achievementPlayers[$player->id] as $stat => $count): ?>
                                    <img
                                        src="/img/achievements/<?= $stat ?>.svg"
                                        alt="<?= h($achievementLabels[$stat] ?? $stat) ?>"
                                        title="<?= h($achievementLabels[$stat] ?? $stat) ?>"
                                        class="w-6 h-6"
                                    >
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <!-- Stats -->
                    <?php foreach ($stats as $key => $stat): ?>
                        <td class="px-3 py-2 text-right
    <?= $sort === $key ? 'text-blue-300 font-bold bg-zinc-800/40' : '' ?>
    <?= $sort !== $key ? 'hidden md:table-cell' : '' ?>">
                            <?= h($stat['value']($player)) ?>

<!--                            --><?php //if($stat['label']=='Points'): ?>
<!--                                <br><span class="text-xs font-thin">avg. --><?php //= $player->avg_score ?><!--</span>-->
<!--                            --><?php //endif ?>
                        </td>
                    <?php endforeach; ?>

                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>
    </div>
</div>
