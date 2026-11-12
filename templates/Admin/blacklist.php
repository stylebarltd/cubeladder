<?php
/**
 * Admin: blacklisted and admin-untracked players, the blacklist (AssaultCube
 * keys and IPs) and the profiles on a blacklisted IP to decide on
 * (App\Service\Blacklist). The buttons that set a status are on the player's page.
 *
 * @var \App\View\AppView $this
 * @var array $players Blacklist::players()
 * @var array $entries Blacklist::entries()
 * @var array $matches Blacklist::ipMatches()
 * @var string $tab
 */
$mask = fn($ip) => preg_match('/^(\d+\.\d+\.\d+)\.\d+$/', (string)$ip, $m) ? $m[1] . '.x' : substr((string)$ip, 0, 12) . '…';
$day = fn($d) => $d ? date('j M Y', strtotime((string)$d)) : '?';
$labels = ['blacklisted' => ['Blacklisted', 'bg-red-500/20 text-red-300'], 'untracked' => ['Not tracked', 'bg-amber-500/20 text-amber-300']];
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
    <?= $this->element('admin_nav') ?>

    <p class="max-w-3xl text-sm text-zinc-400">
        Set on a player's page (admin buttons under the name). <b>Blacklisted</b>: out of every ranking, board, Hall of Fame,
        rating, milestone and achievement, Anonymous in games, live and Discord, no messages to players; their AssaultCube keys
        are listed, so a new profile with the same key is blacklisted by the next import. <b>Not tracked</b>: Anonymous and
        hidden, and the player cannot switch it back. Their games keep counting for everyone else.
    </p>

    <section>
        <h2 class="mb-2 text-lg font-bold"><i class="fa-solid fa-network-wired mr-2 text-amber-300"></i>Same IP as a blacklisted player</h2>
        <?php if (!$matches): ?>
            <p class="text-sm text-zinc-500">Nothing to decide.</p>
        <?php else: ?>
            <p class="mb-2 text-sm text-zinc-400">IPs are shared (mobile networks, cafés) and change: check before you blacklist.</p>
            <div class="grid gap-2">
                <?php foreach ($matches as $m): ?>
                    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-amber-400/30 bg-amber-500/5 p-3 text-sm">
                        <?= $this->Html->link(h($m['name']), ['controller' => 'Players', 'action' => 'view', $m['id']], ['class' => 'font-bold hover:text-blue-300', 'target' => '_blank']) ?>
                        <span><?= $this->Layout->flag($m['country'] ?? null) ?></span>
                        <span class="text-xs text-zinc-400"><?= number_format((int)$m['games']) ?> games · <?= $day($m['first_seen']) ?> – <?= $day($m['last_seen']) ?> · <?= h($mask($m['ip'])) ?></span>
                        <span class="text-xs text-zinc-300">same IP as <?= $m['source_id'] ? $this->Html->link(h((string)$m['source']), ['controller' => 'Players', 'action' => 'view', $m['source_id']], ['class' => 'underline hover:text-blue-300', 'target' => '_blank']) : '?' ?></span>
                        <?= $this->Form->create(null, ['url' => ['action' => 'blacklist'], 'class' => 'ml-auto flex gap-2']) ?>
                            <input type="hidden" name="player_id" value="<?= h($m['id']) ?>">
                            <input type="hidden" name="source" value="<?= h((string)$m['source']) ?>">
                            <button name="do" value="block" class="rounded-lg bg-red-600 px-3 py-1 text-xs font-semibold hover:bg-red-700">Blacklist</button>
                            <button name="do" value="ok" class="rounded-lg bg-white/10 px-3 py-1 text-xs font-semibold hover:bg-white/20">Someone else</button>
                        <?= $this->Form->end() ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-bold"><i class="fa-solid fa-user-slash mr-2 text-red-300"></i>Players</h2>
        <?php if (!$players): ?>
            <p class="text-sm text-zinc-500">Nobody is blacklisted or untracked by the admin.</p>
        <?php else: ?>
            <div class="grid gap-2 md:grid-cols-2">
                <?php foreach ($players as $p): [$label, $cls] = $labels[$p['admin_status']] ?? [$p['admin_status'], 'bg-white/10']; ?>
                    <div class="rounded-lg border border-white/10 bg-white/[0.03] p-3 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="rounded px-1.5 text-[10px] uppercase tracking-wider <?= $cls ?>"><?= h($label) ?></span>
                            <?= $this->Html->link(h($p['name']), ['controller' => 'Players', 'action' => 'view', $p['id']], ['class' => 'truncate font-bold hover:text-blue-300', 'target' => '_blank']) ?>
                            <span><?= $this->Layout->flag($p['country'] ?? null) ?></span>
                        </div>
                        <div class="text-xs text-zinc-400"><?= number_format((int)$p['games']) ?> games · last seen <?= $day($p['last_seen']) ?> · <?= h($mask($p['ip'])) ?></div>
                        <?php if (!empty($p['admin_note'])): ?><div class="mt-1 text-xs text-zinc-300"><?= h($p['admin_note']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-bold"><i class="fa-solid fa-key mr-2 text-zinc-300"></i>Blacklist entries</h2>
        <?php if (!$entries): ?>
            <p class="text-sm text-zinc-500">The list is empty.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase tracking-wider text-zinc-500"><tr><th class="py-1 pr-3">Kind</th><th class="pr-3">Value</th><th class="pr-3">Player</th><th class="pr-3">Note</th><th class="pr-3">Added</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($entries as $e): ?>
                    <tr class="border-t border-white/5">
                        <td class="py-1.5 pr-3"><?= $e['kind'] === 'ip' ? 'IP' : 'Key' ?></td>
                        <td class="pr-3 font-mono text-xs" title="<?= $e['kind'] === 'ip' ? '' : h($e['value']) ?>"><?= h($e['kind'] === 'ip' ? $mask($e['value']) : substr($e['value'], 0, 16) . '…') ?></td>
                        <td class="pr-3"><?= $e['player_id'] && $e['name'] !== null ? $this->Html->link(h($e['name']), ['controller' => 'Players', 'action' => 'view', $e['player_id']], ['class' => 'hover:text-blue-300', 'target' => '_blank']) : '<span class="text-zinc-500">gone</span>' ?></td>
                        <td class="pr-3 text-xs text-zinc-400"><?= h((string)$e['note']) ?></td>
                        <td class="pr-3 text-xs text-zinc-400"><?= $day($e['created']) ?></td>
                        <td>
                            <?= $this->Form->create(null, ['url' => ['action' => 'blacklist']]) ?>
                                <input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>">
                                <button name="do" value="remove" class="text-xs text-zinc-400 hover:text-white" title="Remove this entry (the player keeps their status)">remove</button>
                            <?= $this->Form->end() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>
</div>
</div>
