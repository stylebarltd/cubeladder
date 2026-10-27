<?php
/**
 * /q/<token>: a QR code found in a map (QrController::find, QrHunt).
 *
 * @var \App\View\AppView $this
 * @var array|null $code
 * @var array|null $result new, place, level, name (recognised player)
 * @var string|null $linkCode chat code for a browser we don't know yet
 * @var int $total codes in active maps
 * @var list<array> $hunters
 * @var \App\Model\Entity\Player|null $authPlayer
 */
use App\Service\QrHunt;

$this->assign('title', 'QR hunt');
$this->start('meta'); ?>
    <meta name="robots" content="noindex, nofollow">
<?php $this->end();
$card = 'rounded-xl border border-white/15 bg-black/60 p-5 backdrop-blur-[2px]';
$ord = fn(int $n) => $n . (in_array($n % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th'));
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center text-white"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.75), rgba(0,0,0,.6) 40%, rgba(0,0,0,.85)), url('/img/bullet.jpg');">
<div class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-8">

    <div class="drop-shadow-lg">
        <div class="text-xs uppercase tracking-[0.3em] text-zinc-400"><i class="fa-solid fa-qrcode mr-1"></i> QR hunt</div>
        <h1 class="mt-1 text-3xl md:text-5xl font-extrabold tracking-wide">
            <?php if (!$code): ?>Unknown code
            <?php elseif (!$code['active']): ?>This code has moved
            <?php elseif ($result): ?>You found it!
            <?php else: ?>You found a hidden code
            <?php endif; ?>
        </h1>
    </div>

    <?php if (!$code || !$code['active']): ?>
        <div class="<?= $card ?>">
            <p class="text-zinc-300"><?= $code
                ? 'This code was hidden in an older version of ' . h($code['map']) . '. Look for it in the new one.'
                : 'This is not one of our codes. Codes are hidden in cubeLadder maps &ndash; go and look.' ?></p>
        </div>

    <?php elseif ($result): ?>
        <div class="<?= $card ?> border-yellow-300/50">
            <p class="text-lg">
                <?= $result['new'] ? 'A new find for' : 'You already have this one,' ?> <b><?= h($authPlayer->name) ?></b>
                &ndash; you are the <b><?= $ord((int)$result['place']) ?></b> player to find it.
            </p>
            <div class="mt-4 flex items-center gap-4">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-yellow-400 text-3xl font-extrabold text-black"><?= (int)$result['level'] ?></div>
                <div>
                    <div class="text-xs uppercase tracking-widest text-zinc-400">Your level</div>
                    <div class="text-2xl font-bold text-yellow-300"><?= h((string)$result['name']) ?></div>
                    <div class="text-sm text-zinc-400"><?= (int)$result['level'] ?> of <?= (int)$total ?> codes found</div>
                </div>
            </div>
            <?php if (!empty($code['hint']) && $result['level'] < $total): ?>
                <p class="mt-4 rounded-lg bg-white/5 p-3 text-sm italic text-zinc-300"><i class="fa-solid fa-scroll mr-1 text-yellow-300"></i> <?= h($code['hint']) ?></p>
            <?php elseif ($result['level'] >= $total): ?>
                <p class="mt-4 text-sm text-zinc-300">That's all of them &ndash; for now. New maps bring new codes.</p>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- not recognised: the chat code (PlayerLink), the page waits and reloads -->
        <div class="<?= $card ?> border-yellow-300/50" id="qr-link">
            <p class="text-zinc-300">To count it for you, cubeLadder needs to know who you are. Say this in the chat of any game on a cubeLadder server:</p>
            <div class="my-3 select-all text-center font-mono text-4xl font-extrabold tracking-widest text-yellow-300"><?= h($linkCode) ?></div>
            <p class="text-sm text-zinc-400">The servers' logs are read every 10 minutes. Keep this page open: it recognises you by itself and counts the find
                &ndash; on this browser for a year.</p>
            <p class="mt-2 text-sm text-zinc-400 js-state"><i class="fa-solid fa-circle-notch fa-spin mr-1 text-yellow-300"></i> waiting for your code&hellip;</p>
        </div>
    <?php endif; ?>

    <div class="<?= $card ?>">
        <h2 class="mb-3 flex items-baseline justify-between text-lg font-bold">QR hunters <small class="text-xs font-normal text-zinc-400"><?= (int)$total ?> codes hidden so far</small></h2>
        <?php if ($hunters): ?>
            <div class="space-y-1.5">
                <?php foreach ($hunters as $i => $h): ?>
                    <div class="flex items-center gap-3 rounded-lg bg-white/5 px-3 py-1.5">
                        <div class="w-6 text-center font-bold text-zinc-400"><?= $i + 1 ?></div>
                        <div class="min-w-0 flex-1 truncate font-semibold">
                            <?= $this->Html->link(h($h['name']), ['controller' => 'Players', 'action' => 'view', $h['player_id']], ['escape' => false, 'class' => 'hover:text-blue-400']) ?>
                            <?= $this->Layout->flag($h['country']) ?>
                        </div>
                        <div class="text-sm text-yellow-300"><?= h((string)QrHunt::levelName($h['level'])) ?></div>
                        <div class="w-8 text-right font-mono text-zinc-400"><?= $h['level'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-zinc-400">Nobody yet. Be the first.</p>
        <?php endif; ?>
        <p class="mt-3 text-xs text-zinc-500">Just for fun &ndash; not part of the ladder.</p>
    </div>
</div>
</div>

<?php if ($linkCode): ?>
<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const state = document.querySelector('#qr-link .js-state');
    const started = Date.now();
    const tick = async () => {
        if (Date.now() - started > <?= \App\Service\PlayerLink::VALID_HOURS ?> * 3600e3) { state.textContent = 'Code expired - reload the page for a new one.'; return; }
        try {
            const r = await (await fetch('/play/link', {headers: {'Accept': 'application/json'}, cache: 'no-store'})).json();
            if (r.linked) { state.textContent = 'Got it - counting your find…'; setTimeout(() => location.reload(), 1200); return; }
        } catch (e) { /* try again */ }
        setTimeout(tick, 20000);
    };
    setTimeout(tick, 5000);
})();
</script>
<?php $this->end(); ?>
<?php endif; ?>
