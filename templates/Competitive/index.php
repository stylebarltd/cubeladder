<?php
/**
 * The competitive ladder: Elo of the clan match servers' games
 * (CompetitiveTracker / CompetitiveRatings), the latest games and what the
 * tracker is watching right now.
 *
 * @var \App\View\AppView $this
 * @var array $ratings comp_ratings rows (+ profile_name, country): ranked
 * @var array $provisional active players with fewer than $minGames games
 * @var int $inactive ranked players without a game in $activeDays days
 * @var int $activeDays
 * @var array $games comp_games rows, latest first
 * @var array $players comp_player_stats by game id
 * @var array $running comp_running rows
 * @var array $servers
 * @var int $minGames
 * @var bool $public
 * @var array|null $example a rated player of the latest counted game, for "How the Elo works"
 * @var array $k K factors: new (first newGames games) / normal
 * @var float $minShare part of a game a player needs to be rated
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
                A game counts when it starts with even teams and runs its full 15 minutes &ndash; seen live, every few seconds. Rating: Elo &ndash; everyone starts at
                <?= (int)\App\Controller\CompetitiveController::start() ?>, beating a stronger team brings more. Ranked from <?= $minGames ?> games on (the first ones only find your level), with a game in the last <?= $activeDays ?> days &ndash; names grey out the longer someone hasn't played.
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

    <?php $start = (int)\App\Controller\CompetitiveController::start(); ?>
    <details class="group scroll-mt-20 rounded-xl border border-amber-300/30 bg-black/50 p-4" id="how">
        <summary class="cursor-pointer list-none text-lg font-bold">
            <i class="fa-solid fa-calculator mr-2 text-amber-300"></i>How the Elo works
            <span class="ml-1 text-sm font-normal text-zinc-400 group-open:hidden">&ndash; show the calculation</span>
        </summary>
        <div class="mt-3 grid gap-4 text-sm text-zinc-300 lg:grid-cols-2">
            <ol class="list-decimal space-y-2 pl-5">
                <li>Everyone starts at <b class="text-white"><?= $start ?></b>.</li>
                <li><b class="text-white">Team strength</b> = the average Elo of the team's players before the game. Only players who
                    played at least <?= round($minShare * 100) ?>% of the game count, and only they gain or lose.</li>
                <li><b class="text-white">Win chance</b> = 1 / (1 + 10<sup>(their strength &minus; your strength) / 400</sup>).
                    Equal teams: 50%. A team 100 stronger: 64%; 200 stronger: 76%.</li>
                <li><b class="text-white">Result</b>: a win is 1, a draw &frac12;, a loss 0.</li>
                <li><b class="text-white">Change</b> = K &times; (result &minus; win chance). K is <?= (int)$k['new'] ?> for your first
                    <?= (int)$k['newGames'] ?> games (finding your level fast), then <?= (int)$k['normal'] ?>.
                    Beating a stronger team brings more than beating a weaker one; losing to a weaker one costs more.</li>
                <li>Everything is replayed from the first counted game in order, so every number below can be checked on the game pages.</li>
            </ol>
            <div class="space-y-3">
                <?php if ($example):
                    $res = $example['winner'] === null ? 0.5 : ($example['winner'] === $example['team'] ? 1 : 0);
                    $chg = (float)$example['rating_after'] - (float)$example['rating_before']; ?>
                    <div class="rounded-lg bg-white/5 p-3">
                        <div class="mb-1 text-xs uppercase tracking-wide text-zinc-400">Example: the latest game,
                            <a class="text-blue-300 hover:underline" href="<?= $this->Url->build(['action' => 'game', $example['game_id']]) ?>"><?= h($this->Layout->cleanMapName((string)$example['map'])) ?> <?= h($example['team_size']) ?></a></div>
                        <table class="w-full font-mono text-xs">
                            <tr><td class="py-0.5 text-zinc-400">Player</td><td class="text-right text-white"><?= h($example['name']) ?> (<?= h($example['team']) ?>)</td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Elo before</td><td class="text-right"><?= number_format((float)$example['rating_before'], 1, '.', '') ?></td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Own team strength</td><td class="text-right"><?= number_format((float)$example['team_rating'], 1, '.', '') ?></td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Other team strength</td><td class="text-right"><?= number_format((float)$example['opp_rating'], 1, '.', '') ?></td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Win chance</td><td class="text-right">1 / (1 + 10<sup>(<?= number_format((float)$example['opp_rating'], 1, '.', '') ?> &minus; <?= number_format((float)$example['team_rating'], 1, '.', '') ?>) / 400</sup>) = <?= number_format((float)$example['expected'] * 100, 1) ?>%</td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Result</td><td class="text-right"><?= $res === 1 ? 'win = 1' : ($res === 0 ? 'loss = 0' : 'draw = 0.5') ?></td></tr>
                            <tr><td class="py-0.5 text-zinc-400">Change</td><td class="text-right"><?= (int)$example['k_factor'] ?> &times; (<?= $res ?> &minus; <?= number_format((float)$example['expected'], 3) ?>) = <b class="<?= $chg >= 0 ? 'text-green-400' : 'text-red-400' ?>"><?= ($chg >= 0 ? '+' : '') . number_format($chg, 1) ?></b></td></tr>
                            <tr class="border-t border-white/10"><td class="pt-1 text-zinc-400">Elo after</td><td class="pt-1 text-right font-bold text-amber-300"><?= number_format((float)$example['rating_after'], 1, '.', '') ?></td></tr>
                        </table>
                    </div>
                <?php endif; ?>
                <?php $iw = \App\Service\CompetitiveRatings::IND_WEIGHTS['flags']; ?>
                <div class="rounded-lg bg-white/5 p-3 text-xs text-zinc-300">
                    <div class="mb-1 font-bold text-white"><i class="fa-solid fa-user mr-1 text-amber-300"></i>Individual rating (Ind.)</div>
                    The Elo only knows who won. Next to it, <b>Ind.</b> rates your own play, 0.1 &ndash; 9.9 (5.0 = average): in every rated game
                    your numbers are compared with the other players of that game, per share of the game you played &ndash;
                    flags scored <?= round($iw['flags'] * 100) ?>%, damage dealt <?= round($iw['damage'] * 100) ?>%, K/D <?= round($iw['kd'] * 100) ?>%,
                    team result <?= round($iw['win'] * 100) ?>%, teamkills <?= round($iw['discipline'] * 100) ?>% (minus). In modes without flags:
                    damage <?= round(\App\Service\CompetitiveRatings::IND_WEIGHTS['other']['damage'] * 100) ?>%, K/D <?= round(\App\Service\CompetitiveRatings::IND_WEIGHTS['other']['kd'] * 100) ?>%.
                    Your Ind. is the average of your last <?= \App\Service\CompetitiveRatings::IND_WINDOW ?> games; each game's own rating is on the game page. It never changes the Elo.
                </div>
                <p class="text-xs text-zinc-400">In the table: <b class="text-zinc-200">Elo = <?= $start ?> + Gained &minus; Lost</b>.
                    <b class="text-zinc-200">Score</b> is what you got (wins + &frac12; per draw), <b class="text-zinc-200">Expected</b> what the win chances
                    of all your games add up to: scoring more than expected is what raises your Elo. <b class="text-zinc-200">Opp.</b> is the average
                    strength of the teams you played against.</p>
            </div>
        </div>
    </details>
    <script>if (location.hash === '#how') document.getElementById('how').open = true;</script>

    <section class="rounded-xl border border-white/10 bg-black/50 p-4">
        <h2 class="mb-3 text-lg font-bold">Elo ranking <a href="#how" onclick="document.getElementById('how').open = true" class="ml-1 text-xs font-normal text-amber-300 hover:underline">how it works</a></h2>
        <?php if (!$ratings): ?>
            <p class="text-sm text-zinc-500">Nobody active has <?= $minGames ?> counted games yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-[11px] uppercase tracking-wide text-zinc-400">
                    <tr>
                        <th class="px-2 py-1 text-right">#</th>
                        <th class="px-2 py-1 text-left">Player</th>
                        <th class="px-2 py-1 text-right">Elo</th>
                        <th class="px-2 py-1 text-right" title="individual rating 0.1 - 9.9 (5.0 = average): own play against the others in the same games, average of the last <?= \App\Service\CompetitiveRatings::IND_WINDOW ?> games - does not change the Elo">Ind.</th>
                        <th class="px-2 py-1 text-right">Peak</th>
                        <th class="hidden px-2 py-1 text-right sm:table-cell" title="Elo won in the games you gained from (Elo = <?= $start ?> + Gained - Lost)">Gained</th>
                        <th class="hidden px-2 py-1 text-right sm:table-cell" title="Elo given up in the games you lost points in">Lost</th>
                        <th class="hidden px-2 py-1 text-right md:table-cell" title="what you scored: wins + half a point per draw">Score</th>
                        <th class="hidden px-2 py-1 text-right md:table-cell" title="the win chances of all your games added up: what an average player would have scored in your games">Expected</th>
                        <th class="hidden px-2 py-1 text-right lg:table-cell" title="average strength (Elo) of the teams you played against">Opp.</th>
                        <th class="px-2 py-1 text-right" title="frags per death">K/D</th>
                        <th class="hidden px-2 py-1 text-right xl:table-cell" title="damage done out of the damage the shots could have done - not in match mode, the server hides it there">Acc</th>
                        <th class="px-2 py-1 text-right">Games</th>
                        <th class="px-2 py-1 text-right" title="wins - draws - losses">W-D-L</th>
                        <th class="hidden px-2 py-1 text-right sm:table-cell">Last game</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ratings as $i => $r): ?>
                        <tr class="border-t border-white/5">
                            <td class="px-2 py-1 text-right font-mono text-zinc-400"><?= $i + 1 ?></td>
                            <?php // the longer since the last game, the greyer the name: 20 / 40 / 60 / 80 days
                            $idle = (int)floor((time() - strtotime((string)$r['last_game_at'])) / 86400);
                            $shade = $idle >= 80 ? 'text-zinc-600' : ($idle >= 60 ? 'text-zinc-500' : ($idle >= 40 ? 'text-zinc-400' : ($idle >= 20 ? 'text-zinc-300' : 'text-white'))); ?>
                            <td class="px-2 py-1 <?= $shade ?>" title="last game <?= $idle ?> days ago"><?= $playerLink($r['player_id'], (string)($r['profile_name'] ?? $r['name']), $r['country']) ?></td>
                            <td class="px-2 py-1 text-right font-mono font-bold text-amber-300"><?= (int)round((float)$r['rating']) ?></td>
                            <?php $ind = $r['ind_rating'] !== null ? (float)$r['ind_rating'] : null; ?>
                            <td class="px-2 py-1 text-right font-mono font-bold <?= $ind === null ? 'text-zinc-600' : ($ind >= 6 ? 'text-green-400' : ($ind >= 4 ? 'text-zinc-200' : 'text-red-400')) ?>"><?= $ind !== null ? number_format($ind, 1) : '–' ?></td>
                            <td class="px-2 py-1 text-right font-mono text-zinc-400"><?= (int)round((float)$r['peak']) ?></td>
                            <td class="hidden px-2 py-1 text-right font-mono text-green-400 sm:table-cell">+<?= (int)round($r['gained']) ?></td>
                            <td class="hidden px-2 py-1 text-right font-mono text-red-400 sm:table-cell">&minus;<?= (int)round($r['lost']) ?></td>
                            <td class="hidden px-2 py-1 text-right font-mono md:table-cell"><?= rtrim(rtrim(number_format($r['score'], 1), '0'), '.') ?></td>
                            <td class="hidden px-2 py-1 text-right font-mono md:table-cell <?= $r['expected'] !== null && $r['score'] >= $r['expected'] ? 'text-green-300' : 'text-red-300' ?>"
                                title="<?= $r['expected'] !== null ? 'score against expected: ' . ($r['score'] >= $r['expected'] ? '+' : '') . number_format($r['score'] - $r['expected'], 1) : '' ?>"><?= $r['expected'] !== null ? number_format($r['expected'], 1) : '–' ?></td>
                            <td class="hidden px-2 py-1 text-right font-mono text-zinc-400 lg:table-cell"><?= $r['opp'] !== null ? (int)round($r['opp']) : '–' ?></td>
                            <td class="px-2 py-1 text-right font-mono"><?= $r['kd'] !== null ? number_format($r['kd'], 2) : '–' ?></td>
                            <td class="hidden px-2 py-1 text-right font-mono text-zinc-300 xl:table-cell"><?= $r['acc'] !== null ? round($r['acc']) . '%' : '<span class="text-zinc-600">–</span>' ?></td>
                            <td class="px-2 py-1 text-right font-mono"><?= (int)$r['games'] ?></td>
                            <td class="px-2 py-1 text-right font-mono"><span class="text-green-400"><?= (int)$r['wins'] ?></span>-<?= (int)$r['draws'] ?>-<span class="text-red-400"><?= (int)$r['losses'] ?></span></td>
                            <td class="hidden px-2 py-1 text-right text-zinc-400 sm:table-cell"><?= h(date('d M Y', strtotime((string)$r['last_game_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($inactive): ?>
            <p class="mt-3 text-xs text-zinc-500"><?= $inactive ?> more without a game in the last <?= $activeDays ?> days - they are back as soon as they play.</p>
        <?php endif; ?>

        <?php if ($provisional): ?>
            <h3 class="mt-6 mb-1 text-sm font-bold uppercase tracking-wide text-zinc-400">Provisional</h3>
            <p class="mb-2 text-xs text-zinc-500">Fewer than <?= $minGames ?> games: their Elo is still finding its level.</p>
            <div class="flex flex-wrap gap-1.5 text-xs">
                <?php foreach ($provisional as $r): ?>
                    <span class="rounded-md bg-white/5 px-2 py-1 text-zinc-400">
                        <?= $playerLink($r['player_id'], (string)($r['profile_name'] ?? $r['name']), $r['country'], 'hover:text-blue-300') ?>
                        <span class="ml-1 font-mono text-zinc-500"><?= (int)round((float)$r['rating']) ?></span>
                        <span class="font-mono text-zinc-600">&middot; <?= (int)$r['games'] ?>/<?= $minGames ?></span>
                    </span>
                <?php endforeach; ?>
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
