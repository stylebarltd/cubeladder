<?php
/**
 * Admin: likely duplicate profiles (DuplicateFinder), best matches first.
 * Per pair: merge now, ask the player by message, or dismiss; or tick
 * several and do it for all of them.
 *
 * @var \App\View\AppView $this
 * @var array $candidates
 * @var string $tab
 */
$btn = 'rounded-lg px-2.5 py-1 text-xs font-semibold transition';
?>
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-950 text-white">
<div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 md:px-10 md:py-8">
    <?= $this->element('admin_nav') ?>
    <p class="text-sm text-zinc-400">
        Profiles that are probably one player: same name (also without clan tag), same IP or network, one ending before the other started.
        Pairs that ever played in the same game are left out. <b>Ask</b> sends the newer profile a message with a confirm link;
        when they confirm, the request shows under Merge requests. Asked and dismissed pairs are not suggested again.
    </p>

    <?php if (!$candidates): ?>
        <p class="text-zinc-500">No likely duplicates.</p>
    <?php else: ?>
    <?= $this->Form->create(null, ['url' => ['action' => 'pair'], 'id' => 'pairs-form']) ?>
    <div class="sticky top-16 z-10 flex flex-wrap items-center gap-2 rounded-xl border border-white/10 bg-zinc-900/95 p-2 text-sm backdrop-blur">
        <label class="flex items-center gap-2 px-2"><input type="checkbox" id="check-all"> all</label>
        <span class="text-zinc-400">selected:</span>
        <button name="do" value="ask" class="<?= $btn ?> bg-blue-600 hover:bg-blue-700" onclick="return confirm('Send a message to every selected player?')"><i class="fa-solid fa-envelope mr-1"></i>Ask</button>
        <button name="do" value="dismiss" class="<?= $btn ?> bg-white/10 hover:bg-white/20">Dismiss</button>
        <span class="ml-auto pr-2 text-xs text-zinc-500"><?= count($candidates) ?> pairs</span>
    </div>

    <div class="flex flex-col gap-3">
    <?php foreach ($candidates as $c): $pair = $c['old']['id'] . '|' . $c['new']['id']; ?>
        <div class="rounded-xl border border-white/10 bg-black/50 p-3">
            <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                <input type="checkbox" name="pairs[]" value="<?= h($pair) ?>" class="pair-check">
                <span class="rounded bg-amber-400/20 px-2 py-0.5 font-bold text-amber-300" title="match score">score <?= $c['score'] ?></span>
                <?php foreach ($c['reasons'] as $why): ?>
                    <span class="rounded px-2 py-0.5 <?= $why === 'different country' ? 'bg-red-500/20 text-red-300' : 'bg-white/10 text-zinc-300' ?>"><?= h($why) ?></span>
                <?php endforeach; ?>
                <span class="ml-auto flex gap-1.5">
                    <button name="single" value="merge|<?= h($pair) ?>" class="<?= $btn ?> bg-green-600 hover:bg-green-700"
                            onclick="return confirm('Merge <?= h(addslashes($c['old']['name'])) ?> into <?= h(addslashes($c['new']['name'])) ?>? This cannot be undone.')"><i class="fa-solid fa-code-merge mr-1"></i>Merge</button>
                    <button name="single" value="ask|<?= h($pair) ?>" class="<?= $btn ?> bg-blue-600 hover:bg-blue-700"><i class="fa-solid fa-envelope mr-1"></i>Ask</button>
                    <button name="single" value="dismiss|<?= h($pair) ?>" class="<?= $btn ?> bg-white/10 hover:bg-white/20" title="Not the same player - don't suggest again">Dismiss</button>
                    <a href="<?= $this->Url->build(['action' => 'merge', '?' => ['from' => $c['new']['id'], 'to' => $c['old']['id']]]) ?>"
                       class="<?= $btn ?> bg-white/5 text-zinc-400 hover:bg-white/10" title="Keep the older profile instead"><i class="fa-solid fa-right-left"></i></a>
                </span>
            </div>
            <div class="grid items-center gap-2 md:grid-cols-[1fr_auto_1fr]">
                <?= $this->element('admin_profile', ['p' => $c['old'], 'label' => 'old']) ?>
                <i class="fa-solid fa-arrow-right hidden text-zinc-500 md:block"></i>
                <?= $this->element('admin_profile', ['p' => $c['new'], 'label' => 'stays']) ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?= $this->Form->end() ?>
    <?php endif; ?>
</div>
</div>
<?php $this->start('scriptBottom'); ?>
<script>
document.getElementById('check-all')?.addEventListener('change', (e) => {
    document.querySelectorAll('.pair-check').forEach(c => c.checked = e.target.checked);
});
</script>
<?php $this->end(); ?>
