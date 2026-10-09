<?php
/**
 * Standalone live match page for one server (linked from Discord).
 *
 * @var \App\View\AppView $this
 * @var array $server
 * @var string $key
 */
?>
<div data-live-bg class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center bg-fixed text-white transition-[background-image] duration-500"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.55), rgba(0,0,0,.3) 40%, rgba(0,0,0,.75)), url('/img/bullet.jpg');">
<div class="mx-auto w-full max-w-7xl px-4 py-6 md:px-16 md:py-8">
    <p class="text-sm mb-4">
        <a href="/live#<?= h($key) ?>" class="text-zinc-300 hover:text-white drop-shadow">&larr; all live servers</a>
    </p>
    <?= $this->element('live_match') ?>
</div>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
window.initLiveMatch(document.querySelector('[data-live-match]'), <?= json_encode($key) ?>);
</script>
<?php $this->end(); ?>
