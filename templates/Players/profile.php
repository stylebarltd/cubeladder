<div class="max-w-7xl px-6 py-10 mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
        <img
            src="<?= $this->Layout->playerPicture($player) ?>"
            alt="<?= h($player->name) ?>"
            class="w-32 h-32 rounded-full object-cover"
        >

        <div>
            <h1 class="text-3xl font-bold text-white">
                <?= $this->Html->link(h($player->name),
                    ['controller' => 'Players', 'action' => 'view', $player->id]
                ) ?>

                <?= $this->Layout->flag($player->country) ?>
            </h1>
            <p class="text-sm text-zinc-400">
                <?= h($player->country) ?>
            </p>
        </div>
    </div>



    <!-- Privacy -->
    <div class="mb-10 max-w-2xl rounded-xl border border-zinc-700 bg-zinc-900 p-6">
        <h2 class="text-xl font-semibold mb-3">Privacy</h2>
        <?= $this->Form->create(null, ['url' => ['controller' => 'Players', 'action' => 'privacy']]) ?>
            <label class="flex cursor-pointer items-start gap-3">
                <?= $this->Form->checkbox('dont_track', [
                    'checked' => (int)$player->track === 0,
                    'class' => 'mt-1 h-5 w-5 accent-blue-600',
                ]) ?>
                <span>
                    <span class="font-semibold text-white">Don't track me</span>
                    <span class="block text-sm text-zinc-400">
                        Hide me from all rankings, the Hall of Fame, achievements and my public player page.
                        You can switch it back on any time.
                    </span>
                </span>
            </label>
            <button type="submit" class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition">
                Save
            </button>
        <?= $this->Form->end() ?>
    </div>

    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-xl font-semibold">Choose your avatar</h2>
        <span class="text-sm text-zinc-400"><?= count($avatars) ?> available &middot; click one to use it</span>
    </div>

    <!-- Masonry: every free avatar in its own shape -->
    <div class="columns-2 sm:columns-3 md:columns-4 lg:columns-6 gap-3">

        <!-- No avatar -->
        <?= $this->Html->link(
            '<span class="flex aspect-square w-full items-center justify-center rounded-lg border-4 border-dashed text-sm transition ' .
                ($player->picture === null ? 'border-green-500 text-green-400' : 'border-zinc-600 text-zinc-400 hover:border-zinc-400') .
            '">No avatar</span>',
            ['controller' => 'Players', 'action' => 'avatar', 'none'],
            ['escape' => false, 'class' => 'mb-3 block break-inside-avoid']
        ) ?>

        <?php foreach ($avatars as $avatar): $current = $player->picture === $avatar; ?>
            <?= $this->Html->link(
                '<span class="relative block overflow-hidden rounded-lg ring-4 transition ' .
                    ($current ? 'ring-green-500' : 'ring-transparent hover:ring-zinc-400') . '">' .
                    '<img src="/img/players/' . h(rawurlencode($avatar)) . '" alt="" loading="lazy" class="block w-full">' .
                    ($current ? '<span class="absolute right-2 top-2 rounded-full bg-green-500 px-2 py-0.5 text-xs font-bold text-black">current</span>' : '') .
                '</span>',
                ['controller' => 'Players', 'action' => 'avatar', $avatar],
                ['escape' => false, 'class' => 'mb-3 block break-inside-avoid', 'title' => $current ? 'Your current avatar' : 'Use this avatar']
            ) ?>
        <?php endforeach; ?>
    </div>

</div>
