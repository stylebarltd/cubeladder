<?php
/**
 * One competitive game: the two teams with each player's numbers and Elo change.
 *
 * @var \App\View\AppView $this
 * @var array $game comp_games row
 * @var array $players comp_player_stats rows (+ profile_name, country)
 */
$flagMode = in_array($game['mode'], ['ctf', 'htf', 'tktf'], true);
$scores = json_decode($game['team_scores'], true) ?: [];
$map = $this->Layout->cleanMapName((string)$game['map']);
$this->assign('title', $map . ' · Competitive · cubeLadder');
$afps = ($game['source'] ?? 'live') === 'actionfps';
$hasAcc = (bool)array_filter($players, fn($p) => (int)($p['shot_damage'] ?? 0) > 0);
$teamStyle = ['CLA' => ['band' => 'bg-red-600/45', 'text' => 'text-red-400'], 'RVSF' => ['band' => 'bg-blue-600/45', 'text' => 'text-blue-400']];
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-5xl flex-col gap-5 px-4 py-6 md:px-10 md:py-8">
    <div>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="text-sm text-zinc-400 hover:text-white"><i class="fa-solid fa-chevron-left mr-1"></i>Competitive</a>
        <h1 class="mt-1 text-3xl font-extrabold"><?= h($map) ?></h1>
        <p class="mt-1 flex flex-wrap gap-x-3 text-sm text-zinc-400">
            <span class="rounded bg-amber-500/20 px-2 py-0.5 text-xs font-bold uppercase text-amber-200">Competitive</span>
            <span><?= h(strtoupper((string)$game['mode'])) ?> &middot; <?= h($game['team_size']) ?></span>
            <span><?= $afps ? 'from ActionFPS' : h($this->Layout->serverName($game['server_name'])) ?></span>
            <span><?= h(date('D, d M Y H:i', strtotime((string)$game['started_at']))) ?> UTC</span>
            <?php if (!$game['counted']): ?>
                <span class="text-amber-300">not counted: <?= h((string)$game['reason']) ?></span>
            <?php endif; ?>
        </p>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <?php foreach (['CLA', 'RVSF'] as $team): $rows = array_values(array_filter($players, fn($p) => $p['team'] === $team)); $sc = $scores[$team] ?? []; ?>
            <section class="overflow-hidden rounded-xl border border-white/10 bg-black/50">
                <div class="flex items-center justify-between px-4 py-2 <?= $teamStyle[$team]['band'] ?>">
                    <span class="text-xl font-extrabold"><?= $team ?><?= $game['winner'] === $team ? ' <span class="ml-1 rounded bg-yellow-400/30 px-1.5 py-0.5 text-xs">wins</span>' : '' ?></span>
                    <span class="font-mono text-2xl font-extrabold"><?= (int)($sc[$flagMode ? 'flags' : 'frags'] ?? 0) ?></span>
                </div>
                <table class="w-full text-sm">
                    <thead class="text-[11px] uppercase tracking-wide text-zinc-400">
                    <tr>
                        <th class="px-3 py-1 text-left">Player</th>
                        <?php if ($flagMode): ?><th class="px-2 py-1 text-right">Flags</th><?php endif; ?>
                        <th class="px-2 py-1 text-right">Frags</th>
                        <th class="px-2 py-1 text-right">Deaths</th>
                        <?php if ($hasAcc): ?><th class="px-2 py-1 text-right" title="damage done out of the damage the shots could have done">Acc</th><?php endif; ?>
                        <th class="px-2 py-1 text-right" title="individual rating of this game, 0.1 - 9.9 (5.0 = average): flags, damage, K/D, result, teamkills against the others in this game">Rtg</th>
                        <th class="px-2 py-1 text-right" title="Elo change in this game">Elo</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $p): $delta = $p['rating_after'] !== null ? (float)$p['rating_after'] - (float)$p['rating_before'] : null; ?>
                        <tr class="border-t border-white/5 <?= $p['at_end'] ? '' : 'text-zinc-500' ?>">
                            <td class="px-3 py-1">
                                <?= $p['player_id']
                                    ? $this->Html->link(h((string)($p['profile_name'] ?? $p['name'])) . ' ' . $this->Layout->flag($p['country']), ['controller' => 'Players', 'action' => 'view', $p['player_id']], ['escape' => false, 'class' => 'hover:text-blue-300'])
                                    : h($p['name']) ?>
                                <?php if (!$p['at_end']): ?><span class="text-[10px]" title="played <?= round((float)$p['share'] * 100) ?>% of the game">left</span><?php endif; ?>
                            </td>
                            <?php if ($flagMode): ?><td class="px-2 py-1 text-right font-mono"><?= (int)$p['flags'] ?></td><?php endif; ?>
                            <td class="px-2 py-1 text-right font-mono"><?= (int)$p['frags'] ?></td>
                            <td class="px-2 py-1 text-right font-mono text-zinc-400"><?= $afps ? '–' : (int)$p['deaths'] ?></td>
                            <?php if ($hasAcc): ?><td class="px-2 py-1 text-right font-mono text-zinc-300" title="<?= (int)$p['damage'] ?> of <?= (int)$p['shot_damage'] ?> damage"><?= (int)$p['shot_damage'] > 0 ? round((int)$p['damage'] / (int)$p['shot_damage'] * 100) . '%' : '–' ?></td><?php endif; ?>
                            <?php $ir = $p['ind_rating'] !== null ? (float)$p['ind_rating'] : null; ?>
                            <td class="px-2 py-1 text-right font-mono <?= $ir === null ? 'text-zinc-600' : ($ir >= 6 ? 'text-green-400' : ($ir >= 4 ? 'text-zinc-200' : 'text-red-400')) ?>"><?= $ir !== null ? number_format($ir, 1) : '–' ?></td>
                            <td class="px-2 py-1 text-right font-mono <?= $delta === null ? 'text-zinc-600' : ($delta >= 0 ? 'text-green-400' : 'text-red-400') ?>"
                                title="<?= $delta === null ? 'not rated (played under half of it, or the game was not counted)' : (int)round((float)$p['rating_before']) . ' → ' . (int)round((float)$p['rating_after']) ?>">
                                <?= $delta === null ? '–' : ($delta >= 0 ? '+' : '') . number_format($delta, 1) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endforeach; ?>
    </div>
    <?php // the Elo calculation of every rated player (CompetitiveRatings): before + K x (result - win chance) = after
    $rated = array_values(array_filter($players, fn($p) => $p['rating_after'] !== null && $p['expected'] !== null));
    usort($rated, fn($a, $b) => [$a['team'], -(float)$a['rating_before']] <=> [$b['team'], -(float)$b['rating_before']]); ?>
    <?php if ($rated): ?>
        <section class="overflow-hidden rounded-xl border border-white/10 bg-black/50">
            <div class="flex flex-wrap items-baseline justify-between gap-2 px-4 pt-3">
                <h2 class="text-lg font-bold"><i class="fa-solid fa-calculator mr-2 text-amber-300"></i>Elo calculation</h2>
                <a href="<?= $this->Url->build(['action' => 'index']) ?>#how" class="text-xs text-amber-300 hover:underline">how the Elo works</a>
            </div>
            <p class="px-4 pb-2 text-xs text-zinc-400">Team strength = average Elo of its rated players before the game.
                Win chance = 1 / (1 + 10<sup>(other &minus; own) / 400</sup>). Change = K &times; (result &minus; win chance).</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-[11px] uppercase tracking-wide text-zinc-400">
                    <tr>
                        <th class="px-3 py-1 text-left">Player</th>
                        <th class="px-2 py-1 text-right">Before</th>
                        <th class="px-2 py-1 text-right" title="average Elo of the player's team">Team</th>
                        <th class="px-2 py-1 text-right" title="average Elo of the other team">Other</th>
                        <th class="px-2 py-1 text-right">Win chance</th>
                        <th class="px-2 py-1 text-right" title="win 1, draw 0.5, loss 0">Result</th>
                        <th class="px-2 py-1 text-right" title="<?= (int)\App\Service\CompetitiveRatings::K_NEW ?> for the first <?= \App\Service\CompetitiveRatings::NEW_GAMES ?> games, then <?= (int)\App\Service\CompetitiveRatings::K ?>">K</th>
                        <th class="px-2 py-1 text-right">Change</th>
                        <th class="px-2 py-1 text-right">After</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rated as $p):
                        $res = $game['winner'] === null ? 0.5 : ($game['winner'] === $p['team'] ? 1 : 0);
                        $delta = (float)$p['rating_after'] - (float)$p['rating_before']; ?>
                        <tr class="border-t border-white/5">
                            <td class="px-3 py-1 <?= $teamStyle[$p['team']]['text'] ?>"><?= h((string)($p['player_id'] ? ($p['profile_name'] ?? $p['name']) : $p['name'])) ?></td>
                            <td class="px-2 py-1 text-right font-mono"><?= number_format((float)$p['rating_before'], 1, '.', '') ?></td>
                            <td class="px-2 py-1 text-right font-mono text-zinc-400"><?= number_format((float)$p['team_rating'], 1, '.', '') ?></td>
                            <td class="px-2 py-1 text-right font-mono text-zinc-400"><?= number_format((float)$p['opp_rating'], 1, '.', '') ?></td>
                            <td class="px-2 py-1 text-right font-mono"><?= number_format((float)$p['expected'] * 100, 1) ?>%</td>
                            <td class="px-2 py-1 text-right font-mono"><?= $res === 1 ? '1' : ($res === 0 ? '0' : '½') ?></td>
                            <td class="px-2 py-1 text-right font-mono text-zinc-400"><?= (int)$p['k_factor'] ?></td>
                            <td class="px-2 py-1 text-right font-mono <?= $delta >= 0 ? 'text-green-400' : 'text-red-400' ?>"
                                title="<?= (int)$p['k_factor'] ?> × (<?= $res ?> − <?= number_format((float)$p['expected'], 3) ?>)"><?= ($delta >= 0 ? '+' : '') . number_format($delta, 1) ?></td>
                            <td class="px-2 py-1 text-right font-mono font-bold text-amber-300"><?= number_format((float)$p['rating_after'], 1, '.', '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
    <p class="text-xs text-zinc-500"><?= $afps
        ? 'From actionfps.com, the clan scene\'s old ladder: flags and frags only.'
        : 'Seen by the live view every few seconds (these servers have no logs we get): no kills by weapon' . ($hasAcc ? '.' : ', and no accuracy - the server hides it in match mode.') ?></p>
</div>
</div>
