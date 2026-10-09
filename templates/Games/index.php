<?php

use Cake\Core\Configure;

$slideCount = count($games);
?>
<div class="w-full">

    <h1 class="sr-only">Recent games</h1>

    <?php if ($slideCount === 0): ?>
        <p class="text-center text-zinc-500">No games to show yet.</p>
    <?php else: ?>

    <div id="game-slideshow"
         class="relative select-none overflow-hidden"
         data-count="<?= $slideCount ?>">

        <!-- Track -->
        <div id="game-track"
             class="flex transition-transform duration-300 ease-out"
             style="transform: translateX(0%);">

            <?php foreach ($games as $game): ?>
                <!-- Slide -->
                <div class="game-slide w-full flex-shrink-0">
                    <?= $this->element('game_card', ['game' => $game, 'board' => $boards[$game->id], 'link' => true]) ?>
                </div>
            <?php endforeach; ?>

        </div>

        <!-- Prev / Next arrows -->
        <button type="button" id="game-prev" aria-label="Previous game"
                class="absolute top-4 right-16 md:right-auto md:top-1/2 md:left-2 md:-translate-y-1/2 z-20
                       h-10 w-10 flex items-center justify-center rounded-full
                       bg-black/50 hover:bg-black/70 text-white transition">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button type="button" id="game-next" aria-label="Next game"
                class="absolute top-4 right-2 md:top-1/2 md:-translate-y-1/2 z-20
                       h-10 w-10 flex items-center justify-center rounded-full
                       bg-black/50 hover:bg-black/70 text-white transition">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    <?php endif; ?>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const root = document.getElementById('game-slideshow');
    if (!root) return;

    const track   = document.getElementById('game-track');
    const slides   = Array.from(track.children);
    const count   = slides.length;
    let index = 0;

    function render() {
        track.style.transform = 'translateX(' + (-index * 100) + '%)';
    }

    function go(i) {
        index = (i + count) % count; // wrap around
        render();
    }
    const next = () => go(index + 1);
    const prev = () => go(index - 1);

    document.getElementById('game-next').addEventListener('click', next);
    document.getElementById('game-prev').addEventListener('click', prev);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') next();
        else if (e.key === 'ArrowLeft') prev();
    });

    // Touch swipe (mobile)
    let startX = 0, startY = 0, tracking = false;
    root.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        tracking = true;
    }, { passive: true });

    root.addEventListener('touchend', (e) => {
        if (!tracking) return;
        tracking = false;
        const dx = e.changedTouches[0].clientX - startX;
        const dy = e.changedTouches[0].clientY - startY;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
            dx < 0 ? next() : prev();
        }
    }, { passive: true });

    render();
})();
</script>
<?php $this->end(); ?>
