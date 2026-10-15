<?php
/**
 * Admin: players whose numbers look like cheating (SuspicionService), most
 * suspicious first, with the reasons, the server's warnings as evidence and
 * the admin's verdict (OK takes them off the list).
 *
 * @var \App\View\AppView $this
 * @var array $players
 * @var array $evidence player id => latest 'anticheat' events
 * @var array $statuses
 * @var bool $all
 * @var string $tab
 */
$kindStyle = [
    'aim' => 'bg-red-500/20 text-red-200',
    'server' => 'bg-orange-500/20 text-orange-200',
    'burst' => 'bg-amber-500/20 text-amber-200',
    'jump' => 'bg-fuchsia-500/20 text-fuchsia-200',
    'kills' => 'bg-sky-500/15 text-sky-200',
    'new' => 'bg-violet-500/20 text-violet-200',
    'votes' => 'bg-white/10 text-zinc-200',
];
$statusStyle = ['ok' => 'bg-green-500/20 text-green-300', 'watch' => 'bg-amber-500/20 text-amber-300', 'cheater' => 'bg-red-600/30 text-red-200'];
$evidenceText = function (array $d): string {
    return match ($d['kind'] ?? '') {
        'flag' => ($d['blocked'] ?? false ? 'tried to touch' : 'touched') . ' the ' . h($d['what'] ?? 'flag'),
        'item' => ($d['blocked'] ?? false ? 'tried to pick up' : 'picked up') . ' ' . h($d['what'] ?? 'an item'),
        'burst' => sprintf('%d kills in %d seconds', $d['kills'] ?? 0, $d['seconds'] ?? 0),
        default => h($d['kind'] ?? '?'),
    } . (isset($d['dist']) ? ' <span class="text-zinc-400">at ' . number_format((float)$d['dist'], 1) . ' cubes</span>' : '')
      . (!empty($d['unknown_position']) ? ' <span class="text-zinc-500">(position unknown - lag?)</span>' : '')
      . (isset($d['ping']) ? ' <span class="font-mono ' . ($d['ping'] >= 2 * \App\Service\SuspicionService::limit('lagPing') ? 'text-red-400'
            : ($d['ping'] >= \App\Service\SuspicionService::limit('lagPing') ? 'text-amber-300' : 'text-green-400')) . '">ping ' . (int)$d['ping'] . '</span>' : '');
};
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 md:px-10 md:py-8">
    <?= $this->element('admin_nav') ?>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-3xl text-sm text-zinc-400">
            Numbers that stand out, not proof: a very good player looks like this too. Strongest signs: a sniper headshot rate far above
            everyone else, the server blocking flag touches / pickups from too far away (it already allows for ping), and kills per minute
            jumping in someone's last <?= \App\Service\SuspicionService::limit('recentGames') ?> games. Blocked warnings at a high ping
            (<?= \App\Service\SuspicionService::limit('lagPing') ?>+ ms) count half, <?= 2 * \App\Service\SuspicionService::limit('lagPing') ?>+ ms a quarter &ndash; lag explains those more. Look at their games, then mark them &ndash;
            <b>OK</b> takes a player off the list, <b>Cheater</b> also takes them out of the rankings, the Hall of Fame, the CTF rating,
            weekly achievements and milestones (their games and scoreboards stay).
        </p>
        <a href="<?= $this->Url->build(['action' => 'suspicious', '?' => $all ? [] : ['all' => 1]]) ?>"
           class="rounded-lg bg-white/5 px-3 py-1.5 text-sm text-zinc-300 hover:bg-white/10"><?= $all ? 'Hide the ones marked OK' : 'Show the ones marked OK too' ?></a>
    </div>

    <?php if (!$players): ?>
        <p class="text-zinc-500">Nobody stands out right now.</p>
    <?php endif; ?>

    <div class="flex flex-col gap-3">
    <?php foreach ($players as $p): $st = $p['stats']; $rev = $p['review']; ?>
        <div id="p-<?= h($p['player_id']) ?>" class="scroll-mt-20 rounded-xl border border-white/10 bg-black/50 p-4">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="rounded bg-red-500/20 px-2 py-0.5 font-mono text-sm font-bold text-red-200" title="suspicion score"><?= number_format($p['score'], 1) ?></span>
                <?= $this->Html->link(h($p['name']), ['controller' => 'Players', 'action' => 'view', $p['player_id']],
                    ['class' => 'text-lg font-bold hover:text-blue-300', 'escape' => false]) ?>
                <span><?= $this->Layout->flag($p['country']) ?></span>
                <?php if ($rev): ?>
                    <span class="rounded-full px-2 py-0.5 text-xs font-bold uppercase <?= $statusStyle[$rev['status']] ?? '' ?>"><?= h($statuses[$rev['status']] ?? $rev['status']) ?></span>
                <?php endif; ?>
                <?php if ($st): ?>
                    <span class="ml-auto font-mono text-xs text-zinc-400">
                        <?= (int)$st['games'] ?> games &middot; <?= round($st['mins'] / 60) ?> h &middot;
                        <?= number_format($st['kpm'], 1) ?> kills/min &middot; K/D <?= number_format($st['kd'], 2) ?>
                        <?php if ($st['hs_rate'] !== null): ?>&middot; sniper HS <?= round($st['hs_rate'] * 100) ?>%<?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>

            <ul class="mt-2 flex flex-wrap gap-1.5 text-xs">
                <?php foreach ($p['reasons'] as $r): ?>
                    <li class="rounded-lg px-2 py-1 <?= $kindStyle[$r['kind']] ?? 'bg-white/10' ?>">
                        <?= h($r['text']) ?> <span class="ml-1 font-mono opacity-60">+<?= $r['points'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (!empty($evidence[$p['player_id']])): ?>
                <details class="mt-2">
                    <summary class="cursor-pointer text-xs text-zinc-400 hover:text-zinc-200">Latest server warnings &amp; kill bursts</summary>
                    <ul class="mt-1 space-y-0.5 text-xs">
                        <?php foreach ($evidence[$p['player_id']] as $e): ?>
                            <li class="flex gap-2">
                                <span class="w-32 shrink-0 font-mono text-zinc-500"><?= h(date('d M Y H:i', strtotime((string)$e['event_time']))) ?></span>
                                <span class="min-w-0 flex-1"><?= $evidenceText($e['details']) ?></span>
                                <?php if ($e['game_id']): ?>
                                    <?= $this->Html->link(h($this->Layout->cleanMapName((string)$e['map'])) . ' &rarr;', ['controller' => 'Games', 'action' => 'view', $e['game_id']],
                                        ['class' => 'shrink-0 text-zinc-400 hover:text-blue-300', 'escape' => false]) ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>

            <?= $this->Form->create(null, ['url' => ['action' => 'review', '?' => $all ? ['all' => 1] : []], 'class' => 'mt-3 flex flex-wrap items-center gap-2 text-sm']) ?>
                <?= $this->Form->hidden('player_id', ['value' => $p['player_id']]) ?>
                <input type="text" name="note" value="<?= h($rev['note'] ?? '') ?>" maxlength="500" placeholder="Note (what you saw)"
                       class="min-w-0 flex-1 rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-sm text-white placeholder-zinc-500">
                <?php foreach ($statuses as $value => $label): ?>
                    <button name="status" value="<?= $value ?>"
                            class="rounded-lg px-2.5 py-1 text-xs font-semibold transition <?= ($rev['status'] ?? null) === $value ? 'ring-1 ring-white ' : '' ?><?= $statusStyle[$value] ?> hover:brightness-125"><?= $label ?></button>
                <?php endforeach; ?>
                <?php if ($rev): ?>
                    <button name="status" value="" class="rounded-lg px-2 py-1 text-xs text-zinc-400 hover:text-white">reset</button>
                <?php endif; ?>
            <?= $this->Form->end() ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>
</div>
