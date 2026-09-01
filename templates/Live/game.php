<?php
/**
 * Standalone live match page for one server (linked from Discord).
 *
 * @var \App\View\AppView $this
 * @var array $server
 * @var string $key
 */
?>
<div class="w-full max-w-6xl px-4 sm:px-6 py-10 mx-auto">
    <p class="text-sm mb-4">
        <a href="/live#<?= h($key) ?>" class="text-zinc-500 hover:text-zinc-300">&larr; all live servers</a>
    </p>
    <?= $this->element('live_match') ?>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
window.initLiveMatch(document.querySelector('[data-live-match]'), <?= json_encode($key) ?>);
</script>
<?php $this->end(); ?>
