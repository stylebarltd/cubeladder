<div class="max-w-6xl mx-auto px-6 py-10">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
        <img
            src="<?= $this->Layout->playerPicture($player) ?>"
            alt="<?= h($player->name) ?>"
            class="w-32 h-32 rounded-full object-cover"
        >

        <div>
            <h1 class="text-3xl font-bold text-white">
                <?= h($player->name) ?>
                <?= $this->Layout->flag($player->country) ?>
            </h1>
            <p class="text-sm text-zinc-400">
                <?= h($player->country) ?>
            </p>
        </div>
    </div>



    <h2 class="text-xl font-semibold mb-4">Choose your avatar</h2>

    <div class="grid grid-cols-3 md:grid-cols-8 gap-4">

        <!-- No avatar option -->
        <?= $this->Html->link(
            '<div class="w-25 h-25 rounded-full flex items-center justify-center text-xs
                     border-4 border-dashed transition ' .
            ($player->picture === null
                ? 'border-green-500'
                : 'border-gray-400 hover:border-gray-600') .
            '">None</div>',
            ['controller' => 'Players', 'action' => 'avatar', 'none'],
            ['escape' => false, 'class' => 'group block']
        ) ?>

        <?php foreach ($avatars as $avatar): ?>

            <?= $this->Html->link(
                $this->Html->image(
                    'players/' . h($avatar),
                    [
                        'class' =>
                            'w-25 h-25 rounded-full border-4 transition ' .
                            ($player->picture === $avatar
                                ? 'border-green-500'
                                : 'border-transparent group-hover:border-gray-400')
                    ]
                ),
                ['controller' => 'Players', 'action' => 'avatar', $avatar],
                ['escape' => false, 'class' => 'group block']
            ) ?>

        <?php endforeach; ?>

    </div>




</div>
