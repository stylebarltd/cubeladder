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
    card.className = 'fixed z-50 hidden rounded-xl border border-white/15 bg-zinc-950/95 p-4 text-white shadow-2xl backdrop-blur';
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
        card.classList.remove('hidden');
        place(link);
    }
    function close() {
        clearTimeout(timer);
        current = null;
        card.classList.add('hidden');
    }
    document.querySelectorAll('[data-hover-card]').forEach(link => {
        link.addEventListener('mouseenter', () => { clearTimeout(timer); timer = setTimeout(() => open(link), 200); });
        link.addEventListener('mouseleave', close);
    });
    window.addEventListener('scroll', close, {passive: true});
})();
</script>
