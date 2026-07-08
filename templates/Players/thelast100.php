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

    <div class="relative max-h-[75vh] overflow-y-auto scrollbar-hide space-y-3">

        <?php $i = 1; foreach ($players as $player):

            if (
                empty($player->total_score) ||
                $player->total_score < 0 ||
                $player->stats['kills'] < 1
            ) continue;

            $weapons = $this->Layout->weapon($player->stats);
            $rank = $i++;
            ?>

            <!-- Player card (full width) -->
            <div class="flex flex-col md:flex-row md:items-center gap-4
                        rounded-xl border border-zinc-700 bg-zinc-800 p-4
                        hover:bg-zinc-700/60 transition">

                <!-- Identity -->
                <div class="flex items-center gap-4 md:w-80 md:shrink-0">

                    <div class="w-8 text-center text-2xl font-bold
                                <?= $rank <= 3 ? 'text-yellow-400' : 'text-zinc-500' ?>">
                        <?= $rank ?>
                    </div>

                    <img src="<?= $this->Layout->playerPicture($player) ?>"
                         class="w-14 h-14 rounded-full object-cover shrink-0">

                    <div class="min-w-0">
                        <?= $this->Html->link(
                            h($player->name) . ' ' . $this->Layout->flag($player->country),
                            ['controller' => 'Players', 'action' => 'view', $player->id],
                            ['escape' => false, 'class' => 'font-semibold text-lg hover:text-blue-400 truncate block']
                        ) ?>

                        <div class="flex items-center flex-wrap gap-x-3 gap-y-1 mt-1 text-xs text-zinc-400">
                            <!-- Last seen -->
                            <?php if (!empty($player->last_seen)):
                                $lastSeen = $player->last_seen instanceof \Cake\I18n\DateTime
                                    ? $player->last_seen
                                    : new \Cake\I18n\DateTime($player->last_seen);
                                $days = (int) floor((time() - $lastSeen->getTimestamp()) / 86400);
                                $daysLabel = $days <= 0
                                    ? 'today'
                                    : ($days === 1 ? '1 day ago' : $days . ' days ago');
                                ?>
                                <span title="<?= h($lastSeen->format('M j, Y H:i')) ?>"
                                      class="whitespace-nowrap">
                                    <i class="far fa-clock"></i>
                                    <?= h($daysLabel) ?>
                                </span>
                            <?php endif; ?>

                            <!-- Weapons -->
                            <?php if (!empty($weapons['weapons'])): ?>
                                <span class="flex items-center gap-1">
                                    <?php foreach ($weapons['weapons'] as $weapon): ?>
                                        <img src="/img/weapons/<?= $weapon ?>.svg" class="w-4 h-4">
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>

                            <!-- Achievements -->
                            <?php if (!empty($achievementPlayers[$player->id])): ?>
                                <span class="flex items-center gap-1">
                                    <?php foreach ($achievementPlayers[$player->id] as $ach => $count): ?>
                                        <img
                                            src="/img/achievements/<?= $ach ?>.svg"
                                            alt="<?= h($achievementLabels[$ach] ?? $ach) ?>"
                                            title="<?= h($achievementLabels[$ach] ?? $ach) ?>"
                                            class="w-5 h-5"
                                        >
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-4 sm:grid-cols-8 gap-2 flex-1 w-full">
                    <?php foreach ($stats as $key => $stat): ?>
                        <div class="rounded-lg px-2 py-2 text-center
                            <?= $sort === $key
                                ? 'bg-blue-500/15 ring-1 ring-blue-400/50'
                                : 'bg-zinc-900/40' ?>">
                            <div class="text-[10px] uppercase tracking-wide
                                <?= $sort === $key ? 'text-blue-300' : 'text-zinc-500' ?>">
                                <?= $stat['label'] ?>
                            </div>
                            <div class="text-base font-bold
                                <?= $sort === $key ? 'text-blue-200' : 'text-white' ?>">
                                <?= h($stat['value']($player)) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>

        <?php endforeach; ?>

    </div>
</div>
