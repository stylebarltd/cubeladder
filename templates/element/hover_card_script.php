<?php
/**
 * Hover cards: a link with data-hover-card="<url>" shows the HTML fragment of
 * that URL in a floating card while the mouse is on it (desktop only).
 * Used by /maps (MapsController::playerStats) and the rankings
 * (PlayersController::hoverCard). Include once per page.
 *
 * @var \App\View\AppView $this
 */
?>
<script>
(function () {
    if (!window.matchMedia('(hover: hover)').matches) return;
    const card = document.createElement('div');
    // plain box; a fragment with [data-framed] brings its own frame (the player card style)
    const plain = 'fixed z-50 hidden rounded-xl border border-white/15 bg-zinc-950/95 p-4 text-white shadow-2xl backdrop-blur';
    const framed = 'fixed z-50 hidden rounded-2xl shadow-2xl';
    card.className = plain;
    card.style.width = 'min(32rem, calc(100vw - 2rem))';
    document.body.appendChild(card);
    const cache = {};
    let timer = null, current = null;

    function place(link) {
        const r = link.getBoundingClientRect();
        const w = card.offsetWidth, h = card.offsetHeight;
        card.style.left = Math.min(Math.max(8, r.left), window.innerWidth - w - 8) + 'px';
        let top = r.bottom + 8;
        if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 8); // above when there is no room below
        card.style.top = top + 'px';
    }
    async function open(link) {
        const url = link.dataset.hoverCard;
        current = link;
        if (!(url in cache)) cache[url] = fetch(url).then(r => r.ok ? r.text() : '').catch(() => '');
        const html = await cache[url];
        if (current !== link || !html) return;
        card.innerHTML = html;
        card.className = card.querySelector('[data-framed]') ? framed : plain;
        card.classList.remove('hidden');
        place(link);
    }
    function close() {
        clearTimeout(timer);
        current = null;
        card.classList.add('hidden');
    }
    // delegated, so links added later (game cards loaded into a page) work too
    document.addEventListener('mouseover', e => {
        const link = e.target.closest && e.target.closest('[data-hover-card]');
        if (!link || link === current || link.contains(e.relatedTarget)) return;
        clearTimeout(timer);
        current = link;
        timer = setTimeout(() => open(link), 200);
    });
    document.addEventListener('mouseout', e => {
        const link = e.target.closest && e.target.closest('[data-hover-card]');
        if (link && !link.contains(e.relatedTarget)) close();
    });
    window.addEventListener('scroll', close, {passive: true});
})();
</script>
