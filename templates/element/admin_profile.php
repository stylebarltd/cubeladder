<?php
/**
 * One profile in the admin area (PlayerMerger::profile() / DuplicateFinder row).
 *
 * @var \App\View\AppView $this
 * @var array|null $p
 * @var string $label e.g. "old" / "stays"
 */
if (!$p): ?>
    <div class="rounded-lg border border-white/10 bg-white/[0.03] p-3 text-sm text-zinc-500">profile gone (merged or deleted)</div>
<?php return; endif;
$ip = (string)($p['ip'] ?? '');
$maskedIp = preg_match('/^(\d+\.\d+\.\d+)\.\d+$/', $ip, $m) ? $m[1] . '.x' : ($ip !== '' ? substr($ip, 0, 12) . '…' : '-');
$day = fn($d) => $d ? date('j M Y', strtotime((string)$d)) : '?';
?>
<div class="flex min-w-0 items-center gap-3 rounded-lg border border-white/10 bg-white/[0.03] p-3">
    <img src="<?= $this->Layout->playerPicture((object)$p) ?>" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover">
    <div class="min-w-0 text-sm">
        <div class="flex items-center gap-2">
            <?php if (!empty($label)): ?><span class="rounded bg-white/10 px-1.5 text-[10px] uppercase tracking-wider text-zinc-400"><?= h($label) ?></span><?php endif; ?>
            <?= $this->Html->link(h($p['name']), ['controller' => 'Players', 'action' => 'view', $p['id']],
                ['escape' => false, 'class' => 'truncate font-bold hover:text-blue-300', 'target' => '_blank']) ?>
            <span><?= $this->Layout->flag($p['country'] ?? null) ?></span>
        </div>
        <div class="text-xs text-zinc-400">
            <?= number_format((int)$p['games']) ?> games · <?= number_format((int)($p['points'] ?? 0)) ?> pts
            · <?= $day($p['first_game'] ?? $p['first_seen'] ?? null) ?> – <?= $day($p['last_game'] ?? $p['last_seen'] ?? null) ?>
            · <span title="IP (last part hidden)"><?= h($maskedIp) ?></span>
            · <span class="font-mono" title="<?= h($p['id']) ?>"><?= h(substr($p['id'], 0, 8)) ?></span>
        </div>
    </div>
</div>
