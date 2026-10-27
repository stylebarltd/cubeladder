<?php
/**
 * Profile page for a browser that is not proven to belong to the player
 * (PlayersController::profile): the chat code (PlayerLink). Once it is said in
 * a game the page reloads and the profile opens - from this browser for a year.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Player|null $player
 * @var string $linkCode
 */
$this->assign('title', 'Your profile');
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-2xl flex-col gap-5 px-4 py-10">
    <h1 class="text-3xl font-extrabold tracking-wide">Your profile</h1>
    <div class="rounded-xl border border-yellow-300/50 bg-black/60 p-5" id="proof">
        <p class="text-zinc-300">
            <?= $player ? 'To edit the profile of <b>' . h($player->name) . '</b>, show us it\'s really you.' : 'To edit your profile, cubeLadder needs to know who you are.' ?>
            Say this in the chat of any game on a cubeLadder server:
        </p>
        <div class="my-3 select-all text-center font-mono text-4xl font-extrabold tracking-widest text-yellow-300"><?= h($linkCode) ?></div>
        <p class="text-sm text-zinc-400">The servers' logs are read every 10 minutes. Keep this page open: it opens your profile by itself
            &ndash; and from then on this browser can edit it for a year, whatever internet connection you are on.</p>
        <p class="mt-2 text-sm text-zinc-400 js-state"><i class="fa-solid fa-circle-notch fa-spin mr-1 text-yellow-300"></i> waiting for your code&hellip;</p>
    </div>
</div>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const state = document.querySelector('#proof .js-state');
    const started = Date.now();
    const tick = async () => {
        if (Date.now() - started > <?= \App\Service\PlayerLink::VALID_HOURS ?> * 3600e3) { state.textContent = 'Code expired - reload the page for a new one.'; return; }
        try {
            const r = await (await fetch('/play/link', {headers: {'Accept': 'application/json'}, cache: 'no-store'})).json();
            if (r.linked) { state.textContent = 'Got it - opening your profile…'; setTimeout(() => location.reload(), 1200); return; }
        } catch (e) { /* try again */ }
        setTimeout(tick, 20000);
    };
    setTimeout(tick, 5000);
})();
</script>
<?php $this->end(); ?>
