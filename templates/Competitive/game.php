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
    <p class="text-xs text-zinc-500"><?= $afps
        ? 'From actionfps.com, the clan scene\'s old ladder: flags and frags only.'
        : 'Seen by the live view every few seconds (these servers have no logs we get): no kills by weapon' . ($hasAcc ? '.' : ', and no accuracy - the server hides it in match mode.') ?></p>
</div>
</div>
