<div class="max-w-7xl px-6 py-10 mx-auto">


<h1 class="text-4xl font-bold text-white mb-10 text-center">
    Hall of Fame
</h1>

<?php
$genres = [
    [
        'title' => 'Most Kills',
        'color' => 'text-green-400',
        'data'  => $topKills,
    ],
    [
        'title' => 'Most Headshots',
        'color' => 'text-red-400',
        'data'  => $topHeadshots,
    ],
    [
        'title' => 'Most Flags scored',
        'color' => 'text-red-400',
        'data'  => $topFlags,
    ],
    [
        'title' => 'Most Slashes',
        'color' => 'text-purple-400',
        'data'  => $topSlashes,
    ],
    [
        'title' => 'Most Gibbed',
        'color' => 'text-yellow-400',
        'data'  => $topGibbed,
    ],
    [
        'title' => 'Flag Helpers',
        'color' => 'text-blue-400',
        'data'  => $bestFlagHelpers,
    ],


];
?>



<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">

    <?php foreach ($genres as $genre): ?>
    <div>

        <div class="block bg-zinc-900 rounded-xl p-8">
<!--            <div class="text-4xl mb-4"><i class="fa-solid fa-user"></i></div>-->
            <h3 class="text-xl font-bold mb-2 <?= $genre['color'] ?>"><?= $genre['title'] ?></h3>

            <div class="space-y-2">
                <?php $rank=1; foreach ($genre['data'] as $player):?>
                    <div class="flex items-center gap-3 p-2 bg-zinc-800 rounded-lg">

                        <div class="font-semibold">
                            <?= $rank++ ?>.
                        </div>

                        <img
                            src="<?= $this->Layout->playerPicture($player) ?>"
                            class="w-10 h-10 rounded-full"
                            alt="pic"
                        >

                        <div class="flex-1">
                            <div class="font-semibold">
                                <?= $this->Html->link($player->name, ['controller' => 'Players', 'action' => 'view', $player->player_id]) ?>
                                <?= $this->Layout->flag($player->country) ?>

                            </div>

                            <div class="flex-1 text-xs">

                                <?= $player->played_at->format('d M Y') ?>

                                <a href="<?= $this->Url->build([
                                    'controller' => 'Games',
                                    'action' => 'view',
                                    $player->game_id
                                ]) ?>"
                                   class="hover:underline text-blue-500">
                                    <?= h($player->map_name) ?>
                                </a>
                            </div>
                        </div>

                        <div class="text-blue-500 font-mono text-lg">
                            <?= number_format($player->value) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>





        </div>
    </div>
    <?php endforeach; ?>

</div>
</div>
