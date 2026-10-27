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



    <!-- QR hunt unlocks (QrHunt::UNLOCKS): what the player's points open; the built ones have their settings here -->
    <?php
    $unlocks = \App\Service\QrHunt::UNLOCKS;
    $level = (int)($qrLevel ?? 0);
    $profile = $profile ?? ['card_color' => null, 'card_tagline' => null, 'bio' => null];
    $field = 'w-full rounded-lg border border-zinc-600 bg-zinc-950 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-yellow-400 focus:outline-none';
    ?>
    <div class="mb-10 max-w-3xl rounded-xl border border-yellow-400/40 bg-zinc-900 p-6" id="unlocks">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-xl font-semibold"><i class="fa-solid fa-qrcode mr-1 text-yellow-300"></i> Unlocks</h2>
            <span class="text-sm text-zinc-400">
                <b class="text-yellow-300"><?= $level ?></b> point<?= $level === 1 ? '' : 's' ?>
                &middot; one for every QR code you find hidden in our maps
            </span>
        </div>

        <div class="space-y-3">
            <?php foreach ($unlocks as $key => [$pts, $title, $desc, $built]): $open = $level >= $pts; ?>
                <div class="rounded-lg border <?= $open ? 'border-yellow-400/40 bg-black/40' : 'border-zinc-700 bg-black/20 opacity-60' ?> p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full font-bold <?= $open ? 'bg-yellow-400 text-black' : 'bg-zinc-700 text-zinc-300' ?>"><?= (int)$pts ?></div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold"><?= h($title) ?>
                                <?php if (!$open): ?><span class="ml-1 text-xs font-normal text-zinc-400"><i class="fa-solid fa-lock"></i> unlocks at <?= (int)$pts ?> point<?= $pts === 1 ? '' : 's' ?></span>
                                <?php elseif (!$built): ?><span class="ml-1 text-xs font-normal text-zinc-400">coming soon</span><?php endif; ?>
                            </div>
                            <div class="text-sm text-zinc-400"><?= h($desc) ?></div>

                            <?php if ($open && $key === 'card'): ?>
                                <?= $this->Form->create(null, ['url' => ['controller' => 'Players', 'action' => 'cardStyle'], 'class' => 'mt-3 space-y-3']) ?>
                                    <?php $this->Form->unlockField('card_color'); $this->Form->unlockField('card_tagline'); // hand-written inputs (swatches), CSRF still applies ?>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach (\App\Service\QrHunt::CARD_COLORS as $ck => [$label, $rgb]): ?>
                                            <label class="cursor-pointer" title="<?= h($label) ?>">
                                                <input type="radio" name="card_color" value="<?= h($ck) ?>" class="peer sr-only" <?= ($profile['card_color'] ?? 'blue') === $ck ? 'checked' : '' ?>>
                                                <span class="block h-8 w-8 rounded-full ring-2 ring-transparent ring-offset-2 ring-offset-zinc-900 peer-checked:ring-white" style="background: rgb(<?= implode(',', $rgb) ?>)"></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="text" name="card_tagline" maxlength="<?= \App\Service\QrHunt::TAGLINE_MAX ?>" value="<?= h((string)$profile['card_tagline']) ?>"
                                           placeholder="Tagline after your name (up to <?= \App\Service\QrHunt::TAGLINE_MAX ?> characters, no links)" class="<?= $field ?>">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <button type="submit" class="rounded-lg bg-yellow-400 px-4 py-2 text-sm font-semibold text-black hover:bg-yellow-300 transition">Save card</button>
                                        <a href="/players/card/<?= h($player->id) ?>?v=<?= time() ?>" target="_blank" rel="noopener" class="text-sm text-yellow-300 hover:underline">See your card</a>
                                    </div>
                                <?= $this->Form->end() ?>
                            <?php elseif ($open && $key === 'bio'): ?>
                                <?= $this->Form->create(null, ['url' => ['controller' => 'Players', 'action' => 'bio'], 'class' => 'mt-3 space-y-3']) ?>
                                    <?php $this->Form->unlockField('bio'); ?>
                                    <textarea name="bio" rows="3" maxlength="<?= \App\Service\QrHunt::BIO_MAX ?>"
                                              placeholder="A few words about you (up to <?= \App\Service\QrHunt::BIO_MAX ?> characters, no links)" class="<?= $field ?>"><?= h((string)$profile['bio']) ?></textarea>
                                    <button type="submit" class="rounded-lg bg-yellow-400 px-4 py-2 text-sm font-semibold text-black hover:bg-yellow-300 transition">Save text</button>
                                <?= $this->Form->end() ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($level < 1): ?>
            <p class="mt-4 text-sm text-zinc-400">QR codes are hidden in cubeLadder maps &ndash; start in cl_angkorwat. Scan one with your phone in the game.</p>
        <?php endif; ?>
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
        <?= $this->Form->postLink(
            '<span class="flex aspect-square w-full items-center justify-center rounded-lg border-4 border-dashed text-sm transition ' .
                ($player->picture === null ? 'border-green-500 text-green-400' : 'border-zinc-600 text-zinc-400 hover:border-zinc-400') .
            '">No avatar</span>',
            ['controller' => 'Players', 'action' => 'avatar', 'none'],
            ['escape' => false, 'class' => 'mb-3 block break-inside-avoid']
        ) ?>

        <?php foreach ($avatars as $avatar): $current = $player->picture === $avatar; ?>
            <?= $this->Form->postLink(
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
