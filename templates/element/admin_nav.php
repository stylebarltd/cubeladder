<?php
/**
 * Admin area: title and tabs (AdminController).
 *
 * @var \App\View\AppView $this
 * @var string $tab requests | duplicates | messages | merge | suspicious | competitive | visitors | untracked
 */
$this->assign('title', 'Admin · cubeLadder');
$tabs = [
    'requests' => ['index', 'fa-code-merge', 'Merge requests'],
    'duplicates' => ['duplicates', 'fa-people-arrows', 'Duplicates'],
    'messages' => ['messages', 'fa-inbox', 'Messages'],
    'merge' => ['merge', 'fa-wrench', 'Merge by hand'],
    'suspicious' => ['suspicious', 'fa-user-secret', 'Suspicious'],
    'competitive' => ['competitive', 'fa-chess-knight', 'Competitive names'],
    'visitors' => ['visitors', 'fa-chart-simple', 'Visitors'],
    'untracked' => ['untracked', 'fa-eye-slash', 'Not tracked'],
];
?>
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-3xl font-extrabold tracking-wide"><i class="fa-solid fa-shield-halved mr-2 text-amber-300"></i>Admin</h1>
    <?= $this->Form->postLink('<i class="fa-solid fa-right-from-bracket mr-1"></i>Log out', ['controller' => 'Admin', 'action' => 'logout'],
        ['escape' => false, 'class' => 'text-sm text-zinc-400 hover:text-white']) ?>
</div>
<nav class="flex flex-wrap gap-2 border-b border-white/10 pb-3">
    <?php foreach ($tabs as $key => [$action, $icon, $label]): ?>
        <a href="<?= $this->Url->build(['controller' => 'Admin', 'action' => $action]) ?>"
           class="rounded-lg px-3 py-1.5 text-sm font-semibold transition <?= $tab === $key ? 'bg-white text-black' : 'bg-white/5 text-zinc-300 hover:bg-white/10' ?>">
            <i class="fa-solid <?= $icon ?> mr-1.5"></i><?= $label ?>
        </a>
    <?php endforeach; ?>
    <?php // the competitive ladder lives on its own page (hidden from players until Ladder.competitive.public) ?>
    <a href="<?= $this->Url->build(['controller' => 'Competitive', 'action' => 'index']) ?>"
       class="rounded-lg bg-white/5 px-3 py-1.5 text-sm font-semibold text-zinc-300 transition hover:bg-white/10">
        <i class="fa-solid fa-chess-knight mr-1.5"></i>Competitive
    </a>
</nav>
