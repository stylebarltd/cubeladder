<?php
/**
 * Milestone wall + fun facts on the player page (bin/cake CalculateMilestones).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Player $player
 * @var array $milestones player_milestones rows (reached_at ascending)
 * @var array $milestoneProgress current totals
 * @var array $funFacts
 * @var array $timePlayed
 * @var \Cake\Datasource\EntityInterface|null $rating
 * @var string $panel
 */

use App\Service\MilestoneService;

// highest reached tier per milestone, with its date
$reached = [];
foreach ($milestones as $m) {
    if (!isset($reached[$m->milestone]) || $m->tier > $reached[$m->milestone]->tier) {
        $reached[$m->milestone] = $m;
    }
}
$tierStyle = [
    0 => ['text-zinc-500', 'bg-white/5', 'bg-zinc-600'],
    1 => ['text-amber-600', 'bg-amber-700/20', 'bg-amber-600'],
    2 => ['text-zinc-200', 'bg-zinc-300/15', 'bg-zinc-300'],
    3 => ['text-yellow-300', 'bg-yellow-400/20', 'bg-yellow-400'],
    4 => ['text-teal-300', 'bg-teal-400/20', 'bg-teal-300'],
    5 => ['text-sky-300', 'bg-sky-400/20', 'bg-sky-300'],
    6 => ['text-fuchsia-300', 'bg-fuchsia-400/20', 'bg-fuchsia-400'],
    7 => ['text-red-300', 'bg-red-500/20', 'bg-red-400'],
];
$icon = fn(string $icon, string $class) => str_starts_with($icon, '/')
    ? '<img src="' . h($icon) . '" alt="" class="h-5 w-5 ' . $class . '">'
    : '<i class="fa-solid ' . h($icon) . ' ' . $class . '"></i>';
$date = fn($dt) => $dt ? $dt->format('j M Y') : '';
$progress = $milestoneProgress;
$earnedTiers = array_sum(array_map(fn($m) => isset(MilestoneService::MILESTONES[$m->milestone]) ? 1 : 0, $milestones));
$totalTiers = array_sum(array_map(fn($d) => count($d['tiers']), MilestoneService::MILESTONES));
?>
<!-- Milestones -->
<section class="<?= $panel ?> mb-6 p-5" id="milestones">
    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-lg font-bold"><i class="fa-solid fa-medal mr-2 text-yellow-300"></i>Milestones</h2>
        <span class="text-xs text-zinc-400"><?= $earnedTiers ?> of <?= $totalTiers ?> tiers reached</span>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7">
        <?php foreach (MilestoneService::MILESTONES as $key => $def):
            $value = $progress[$key] ?? 0;
            $hit = $reached[$key] ?? null;
            $tier = $hit ? (int)$hit->tier : 0;
            [$tierText, $tierBg, $tierBar] = $tierStyle[$tier] ?? $tierStyle[0];
            $next = $def['tiers'][$tier] ?? null;
            $prev = $tier > 0 ? $def['tiers'][$tier - 1] : 0;
            $pct = $next === null ? 100 : max(2, min(100, (int)round(100 * ($value - $prev) / max(1, $next - $prev))));
            $title = $hit
                ? MilestoneService::TIER_NAMES[$tier] . ': ' . number_format((int)$hit->threshold) . ' ' . $def['unit'] . ' on ' . $date($hit->reached_at)
                : 'First tier at ' . number_format($def['tiers'][0]) . ' ' . $def['unit'];
            ?>
            <div class="flex flex-col gap-1.5 rounded-lg <?= $tierBg ?> p-3" title="<?= h($title) ?>">
                <div class="flex items-center gap-2 text-[11px] uppercase tracking-wide <?= $tier ? 'text-zinc-200' : 'text-zinc-500' ?>">
                    <?= $icon($def['icon'], $tier ? '' : 'opacity-40') ?><span class="truncate"><?= h($def['label']) ?></span>
                </div>
                <div class="font-mono text-xl font-extrabold tabular-nums leading-none <?= $tier ? 'text-white' : 'text-zinc-400' ?>">
                    <?= number_format((float)$value, 0) ?>
                </div>
                <div class="text-[11px] font-semibold <?= $tierText ?>">
                    <?= $tier ? MilestoneService::TIER_NAMES[$tier] : 'not yet' ?>
                    <?php if ($hit): ?><span class="font-normal text-zinc-400">· <?= $date($hit->reached_at) ?></span><?php endif; ?>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-black/40">
                    <div class="h-full rounded-full <?= $next === null ? $tierBar : 'bg-white/60' ?>" style="width: <?= $pct ?>%"></div>
                </div>
                <div class="text-[10px] text-zinc-400">
                    <?= $next === null ? 'top tier reached' : 'next: ' . number_format($next) . ' ' . h($def['unit']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <!-- Special milestones -->
    <section class="<?= $panel ?> p-5">
        <h2 class="mb-4 text-lg font-bold"><i class="fa-solid fa-star mr-2 text-fuchsia-300"></i>Specials</h2>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <?php foreach (MilestoneService::SPECIALS as $key => $def):
                $hit = $reached[$key] ?? null;
                $close = match ($key) {
                    'marathon' => 'best day: ' . (int)($progress['most_games_in_a_day'] ?? 0) . ' / 20 games',
                    'map_addict' => !empty($progress['top_map']) ? $progress['top_map']['name'] . ': ' . (int)$progress['top_map']['games'] . ' / 100 games' : '',
                    'tourist' => (int)($progress['servers'] ?? 0) . ' / ' . (int)($progress['servers_needed'] ?? 0) . ' servers',
                    default => '',
                };
                $count = match ($key) {
                    'untouchable' => (int)($progress['untouchable'] ?? 0),
                    'hattrick' => (int)($progress['hattricks'] ?? 0),
                    default => 0,
                };
                ?>
                <div class="flex items-center gap-3 rounded-lg p-2.5 <?= $hit ? 'bg-fuchsia-400/15' : 'bg-white/5' ?>">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full <?= $hit ? 'bg-fuchsia-400/25 text-fuchsia-200' : 'bg-white/5 text-zinc-600' ?>">
                        <i class="fa-solid <?= $def['icon'] ?>"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold <?= $hit ? 'text-white' : 'text-zinc-500' ?>">
                            <?= h($def['label']) ?><?= $count > 1 ? ' <span class="text-xs font-normal text-fuchsia-200">×' . $count . '</span>' : '' ?>
                        </div>
                        <div class="truncate text-[11px] text-zinc-400">
                            <?php if ($hit): ?>
                                <?= $hit->game_id
                                    ? $this->Html->link(h($date($hit->reached_at)), ['controller' => 'Games', 'action' => 'view', $hit->game_id], ['class' => 'hover:text-blue-300', 'escape' => false])
                                    : h($date($hit->reached_at)) ?>
                                <?= $hit->detail ? ' · ' . h($hit->detail) : '' ?>
                            <?php else: ?>
                                <?= h($def['text']) ?><?= $close !== '' ? ' · ' . h($close) : '' ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Fun facts -->
    <section class="<?= $panel ?> p-5">
        <h2 class="mb-4 text-lg font-bold"><i class="fa-solid fa-face-grin-stars mr-2 text-green-300"></i>Fun facts</h2>
        <ul class="space-y-2.5 text-sm text-zinc-200">
            <?php
            $minutes = (int)($timePlayed['all'] ?? 0);
            if ($minutes >= 60):
                $d = intdiv($minutes, 1440);
                $h = intdiv($minutes % 1440, 60);
                ?>
                <li class="flex gap-3"><i class="fa-solid fa-hourglass-half mt-1 w-4 text-green-300"></i>
                    <span><?= $this->Layout->duration($minutes) ?> on our servers &ndash; that&rsquo;s
                        <b><?= $d > 0 ? $d . ' day' . ($d === 1 ? '' : 's') . ' and ' : '' ?><?= $h ?> hour<?= $h === 1 ? '' : 's' ?></b> non-stop.</span></li>
            <?php endif; ?>
            <?php if (!empty($funFacts['players'])): ?>
                <li class="flex gap-3"><i class="fa-solid fa-users mt-1 w-4 text-green-300"></i>
                    <span>Played with or against <b><?= number_format($funFacts['players']) ?></b> different players
                        from <b><?= (int)$funFacts['countries'] ?></b> countries.</span></li>
            <?php endif; ?>
            <?php if (!empty($funFacts['teammate'])): ?>
                <li class="flex gap-3"><i class="fa-solid fa-handshake mt-1 w-4 text-green-300"></i>
                    <span>Most played teammate:
                        <?= $this->Html->link(h($funFacts['teammate']['name']), ['controller' => 'Players', 'action' => 'view', $funFacts['teammate']['id']], ['class' => 'font-bold hover:text-blue-300', 'escape' => false]) ?>
                        &ndash; on the same team <b><?= number_format((int)$funFacts['teammate']['n']) ?></b> times.</span></li>
            <?php endif; ?>
            <?php if (!empty($funFacts['server']) || ($rating && $rating->weapon !== 'Mixed')): ?>
                <li class="flex gap-3"><i class="fa-solid fa-heart mt-1 w-4 text-green-300"></i>
                    <span>
                        <?php if (!empty($funFacts['server'])): ?>Favourite server <b><?= h($funFacts['server']['name']) ?></b> (<?= number_format($funFacts['server']['n']) ?> games)<?php endif; ?><?php if ($rating && $rating->weapon !== 'Mixed'): ?><?= !empty($funFacts['server']) ? ', favourite' : 'Favourite' ?> weapon <b><?= h($rating->weapon) ?></b><?php endif; ?>.
                    </span></li>
            <?php endif; ?>
            <?php if (!empty($funFacts['weekday'])): ?>
                <li class="flex gap-3"><i class="fa-solid fa-calendar-week mt-1 w-4 text-green-300"></i>
                    <span>Plays most on <b><?= h($funFacts['weekday']['day']) ?>s</b>.</span></li>
            <?php endif; ?>
            <?php if (!empty($funFacts['gg'])): ?>
                <li class="flex gap-3"><i class="fa-solid fa-comment mt-1 w-4 text-green-300"></i>
                    <span>Said <b>gg</b> <?= number_format($funFacts['gg']) ?> time<?= $funFacts['gg'] === 1 ? '' : 's' ?> in the chat.</span></li>
            <?php endif; ?>
            <?php if (($funFacts['names'] ?? 0) > 1): ?>
                <li class="flex gap-3"><i class="fa-solid fa-masks-theater mt-1 w-4 text-green-300"></i>
                    <span>Played under <b><?= (int)$funFacts['names'] ?></b> different names.</span></li>
            <?php endif; ?>
            <?php if (!empty($funFacts['since'])): ?>
                <li class="flex gap-3"><i class="fa-solid fa-seedling mt-1 w-4 text-green-300"></i>
                    <span>On cubeLadder since <b><?= h((new \Cake\I18n\DateTime($funFacts['since']))->format('j M Y')) ?></b>.</span></li>
            <?php endif; ?>
        </ul>
    </section>
</div>
