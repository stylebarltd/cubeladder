<?php
/**
 * Admin login (AdminController::login).
 *
 * @var \App\View\AppView $this
 * @var bool $noPassword
 */
$this->assign('title', 'Admin · cubeLadder');
?>
<div class="flex min-h-[calc(100svh-4rem)] items-center justify-center bg-zinc-950 px-4 text-white">
    <div class="w-full max-w-sm rounded-2xl border border-white/10 bg-black/60 p-6 shadow-xl">
        <h1 class="mb-5 text-2xl font-extrabold"><i class="fa-solid fa-shield-halved mr-2 text-amber-300"></i>Admin</h1>
        <?php if ($noPassword): ?>
            <p class="text-sm text-zinc-300">No admin password set yet. On the server run:</p>
            <pre class="mt-2 rounded bg-white/5 p-2 text-xs">bin/cake admin_password</pre>
        <?php else: ?>
            <?= $this->Form->create(null) ?>
            <label class="mb-1 block text-sm text-zinc-400" for="password">Password</label>
            <?= $this->Form->password('password', ['id' => 'password', 'required' => true, 'autofocus' => true, 'autocomplete' => 'current-password',
                'class' => 'mb-4 w-full rounded-lg border border-white/15 bg-zinc-900 px-3 py-2 focus:border-blue-400 focus:outline-none']) ?>
            <button class="w-full rounded-lg bg-blue-600 py-2 font-semibold hover:bg-blue-700">Log in</button>
            <?= $this->Form->end() ?>
        <?php endif; ?>
    </div>
</div>
