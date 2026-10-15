<?php
/**
 * The competitive ladder: Elo of the clan match servers' games
 * (CompetitiveTracker / CompetitiveRatings), the latest games and what the
 * tracker is watching right now.
 *
 * @var \App\View\AppView $this
 * @var array $ratings comp_ratings rows (+ profile_name, country)
 * @var array $games comp_games rows, latest first
 * @var array $players comp_player_stats by game id
 * @var array $running comp_running rows
 * @var array $servers
 * @var int $minGames
 * @var bool $public
 */
$this->assign('title', 'Competitive · cubeLadder');
$serverName = fn(string $key) => $this->Layout->serverName($key);
$playerLink = function (?string $id, string $name, ?string $country = null, string $class = 'hover:text-blue-300') {
    $label = h($name) . ($country ? ' ' . $this->Layout->flag($country) : '');

    return $id ? $this->Html->link($label, ['controller' => 'Players', 'action' => 'view', $id], ['escape' => false, 'class' => $class]) : '<span>' . $label . '</span>';
};
$teamText = ['CLA' => 'text-red-400', 'RVSF' => 'text-blue-400'];
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-extrabold tracking-wide"><i class="fa-solid fa-chess-knight mr-2 text-amber-300"></i>Competitive</h1>
            <p class="mt-1 max-w-3xl text-sm text-zinc-400">
                Team games of 2 vs 2 and up on the clan match servers (<?= h(implode(', ', array_map($serverName, $servers))) ?>), apart from the ladder.
                A game counts when it starts with even teams and runs its full 15 minutes. Earlier games come from actionfps.com, the clan scene's old ladder. Rating: Elo &ndash; everyone starts at
                <?= (int)\App\Controller\CompetitiveController::start() ?>, beating a stronger team brings more. Ranked from <?= $minGames ?> games on.
            </p>
        </div>
        <?php if (!$public): ?>
            <span class="rounded-lg bg-amber-500/20 px-3 py-1.5 text-xs font-semibold text-amber-200"><i class="fa-solid fa-lock mr-1"></i>Admins only (Ladder.competitive.public is off)</span>
        <?php endif; ?>
    </div>

    <?php if ($running): ?>
        <section class="rounded-xl border border-white/10 bg-black/50 p-4">
            <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-zinc-400">Being watched now</h3>
            <ul class="space-y-1 text-sm">
                <?php foreach ($running as $r): $st = json_decode($r['state'], true) ?: []; ?>
                    <li class="flex flex-wrap gap-x-3">
                        <b><?= h($serverName($r['server_name'])) ?></b>
                        <span><?= h($this->Layout->cleanMapName((string)($st['map'] ?? ''))) ?></span>
                        <span class="text-zinc-400"><?= h(\App\Service\AcExtInfoService::MODES[$st['mode'] ?? -1] ?? '') ?></span>
                        <span class="text-zinc-400"><?= (int)($st['start']['CLA'] ?? 0) ?>v<?= (int)($st['start']['RVSF'] ?? 0) ?> at the start</span>
                        <span class="text-zinc-500"><?= (int)($st['last_min'] ?? 0) ?> min left</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="rounded-xl border border-white/10 bg-black/50 p-4">
        <h2 class="mb-3 text-lg font-bold">Elo ranking</h2>
        <?php if (!$ratings): ?>
            <p class="text-sm text-zinc-500">Nobody has <?= $minGames ?> counted games yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-[11px] uppercase tracking-wide text-zinc-400">
                    <tr>
                        <th class="px-2 py-1 text-right">#</th>
                        <th class="px-2 py-1 text-left">Player</th>
                        <th class="px-2 py-1 text-right">Elo</th>
                        <th class="px-2 py-1 text-right">Peak</th>
                        <th class="px-2 py-1 text-right">Games</th>
                        <th class="px-2 py-1 text-right" title="wins - draws - losses">W-D-L</th>
                        <th class="hidden px-2 py-1 text-right sm:table-cell">Last game</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ratings as $i => $r): ?>
                        <tr class="border-t border-white/5">
                            <td class="px-2 py-1 text-right font-mono text-zinc-400"><?= $i + 1 ?></td>
                            <td class="px-2 py-1"><?= $playerLink($r['player_id'], (string)($r['profile_name'] ?? $r['name']), $r['country']) ?></td>
                            <td class="px-2 py-1 text-right font-mono font-bold text-amber-300"><?= (int)round((float)$r['rating']) ?></td>
                            <td class="px-2 py-1 text-right font-mono text-zinc-400"><?= (int)round((float)$r['peak']) ?></td>
                            <td class="px-2 py-1 text-right font-mono"><?= (int)$r['games'] ?></td>
                            <td class="px-2 py-1 text-right font-mono"><span class="text-green-400"><?= (int)$r['wins'] ?></span>-<?= (int)$r['draws'] ?>-<span class="text-red-400"><?= (int)$r['losses'] ?></span></td>
                            <td class="hidden px-2 py-1 text-right text-zinc-400 sm:table-cell"><?= h(date('d M Y', strtotime((string)$r['last_game_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-xl border border-white/10 bg-black/50 p-4">
        <h2 class="mb-3 text-lg font-bold">Latest games</h2>
        <?php if (!$games): ?>
            <p class="text-sm text-zinc-500">No games yet &ndash; they show up here as soon as one ends.</p>
        <?php endif; ?>
        <ul class="space-y-2">
            <?php foreach ($games as $g): $sc = json_decode($g['team_scores'], true) ?: []; $flagMode = in_array($g['mode'], ['ctf', 'htf', 'tktf'], true); ?>
                <li>
                    <a href="<?= $this->Url->build(['action' => 'game', $g['id']]) ?>" class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-white/5 px-3 py-2 text-sm transition hover:bg-white/10">
                        <span class="w-28 shrink-0 font-mono text-xs text-zinc-500"><?= h(date('d M H:i', strtotime((string)$g['started_at']))) ?></span>
                        <b><?= h($this->Layout->cleanMapName((string)$g['map'])) ?></b>
                        <span class="text-zinc-400"><?= h(strtoupper((string)$g['mode'])) ?> &middot; <?= h($g['team_size']) ?></span>
                        <span class="font-mono font-bold">
                            <span class="text-red-400"><?= (int)($sc['CLA'][$flagMode ? 'flags' : 'frags'] ?? 0) ?></span>
                            :
                            <span class="text-blue-400"><?= (int)($sc['RVSF'][$flagMode ? 'flags' : 'frags'] ?? 0) ?></span>
                        </span>
                        <span class="min-w-0 flex-1 truncate text-xs text-zinc-400">
                            <?= h(implode(', ', array_map(fn($p) => $p['name'], $players[$g['id']] ?? []))) ?>
                        </span>
                        <?php if (!$g['counted']): ?>
                            <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-bold uppercase text-zinc-400" title="<?= h((string)$g['reason']) ?>">not counted</span>
                        <?php endif; ?>
                        <span class="text-xs text-zinc-500"><?= ($g['source'] ?? 'live') === 'actionfps' ? 'ActionFPS' : h($serverName($g['server_name'])) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
</div>
