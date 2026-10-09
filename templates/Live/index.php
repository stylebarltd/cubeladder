<?php
/**
 * Live page: a server picker (like the /maps dropdown: map thumbnail, name,
 * map · mode, players) on top, the selected server's running game (shared
 * live_match element) below - on the live map as page background.
 * Selection is kept in the URL hash (/live#acka-assault).
 *
 * @var \App\View\AppView $this
 * @var array $servers  Ladder.servers minus hidden ones
 */
?>
<div data-live-bg class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center bg-fixed text-white"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.55), rgba(0,0,0,.3) 40%, rgba(0,0,0,.75)), url('/img/bullet.jpg');">
<div class="mx-auto w-full max-w-7xl px-4 py-6 md:px-16 md:py-8">

<?php if (empty($servers)): ?>
    <h1 class="text-3xl md:text-5xl font-extrabold tracking-wide">Live</h1>
    <p class="mt-4 text-zinc-400">No servers configured (Ladder.servers).</p>
<?php else: ?>

<!-- Title + server picker -->
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="drop-shadow-lg">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-wide">Live</h1>
        <p class="mt-2 text-sm text-zinc-300">Who is playing right now &middot; <span id="live-updated">loading&hellip;</span></p>
        <p id="live-elsewhere-sub" class="mt-1 text-xs text-zinc-400"></p>
    </div>

    <div class="relative w-full sm:w-96" id="srv-picker">
        <button type="button" id="srv-btn" aria-haspopup="listbox" aria-expanded="false"
                class="flex w-full items-center gap-3 rounded-lg border border-white/15 bg-black/60 p-2 text-left backdrop-blur-[2px] hover:bg-black/75 transition">
            <span class="relative h-10 w-[72px] shrink-0">
                <img id="srv-btn-thumb" src="/img/bullet-thumb.jpg" alt="" class="h-10 w-[72px] rounded object-cover">
                <span id="srv-btn-dot" class="absolute -right-1 -top-1 h-3 w-3 rounded-full bg-zinc-600 ring-2 ring-black"></span>
            </span>
            <span class="min-w-0 flex-1">
                <span id="srv-btn-name" class="block truncate text-sm font-semibold">&nbsp;</span>
                <span id="srv-btn-sub" class="block truncate text-xs text-zinc-400">choose a server&hellip;</span>
            </span>
            <span id="srv-btn-count" class="shrink-0 font-mono text-xs text-zinc-300"></span>
            <i class="fas fa-chevron-down text-zinc-400"></i>
        </button>

        <div id="srv-panel" role="listbox"
             class="absolute right-0 z-30 mt-2 hidden w-full overflow-hidden rounded-lg border border-white/15 bg-zinc-900/95 shadow-2xl backdrop-blur">
            <div class="border-b border-white/10 p-2">
                <input id="srv-search" type="search" placeholder="Search servers or maps…" autocomplete="off"
                       class="w-full rounded bg-zinc-800 px-3 py-1.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div id="srv-list" class="nice-scroll max-h-[65vh] overflow-y-auto py-1"></div>
        </div>
    </div>
</div>

<!-- Selected server's running game -->
<?= $this->element('live_match') ?>

<?php endif; ?>
</div>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const picker = document.getElementById('srv-picker');
    if (!picker) return;
    const btn = document.getElementById('srv-btn');
    const panel = document.getElementById('srv-panel');
    const list = document.getElementById('srv-list');
    const search = document.getElementById('srv-search');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    // own servers are known up front (offline ones too); clan / elsewhere once polled
    const own = <?= json_encode(array_map(fn($k, $c) => [
        'key' => $k, 'name' => $c['name'] ?? $k, 'online' => null, 'numplayers' => 0,
        'host' => $c['host'], 'port' => (int)$c['port'],
    ], array_keys($servers), $servers)) ?>;
    let groups = [{title: 'Our servers', servers: own}];
    const byKey = () => new Map(groups.flatMap(g => g.servers).map(s => [s.key, s]));

    // countdown to the next refresh
    const POLL_MS = 5000;
    let nextPollAt = Date.now() + POLL_MS;
    setInterval(() => {
        const sec = Math.max(0, Math.ceil((nextPollAt - Date.now()) / 1000));
        document.getElementById('live-updated').textContent = 'next update in ' + sec + ' sec';
    }, 250);

    // --- rendering ---------------------------------------------------
    const dotClass = (s) => s.online === false ? 'bg-red-500' : (s.numplayers > 0 ? 'bg-green-500 lg-pulse' : 'bg-zinc-600');
    const subText = (s) => s.online === false ? 'offline'
        : (s.numplayers > 0 ? (s.map || '—') + ' · ' + (s.mode_name || '?') : (s.online ? 'empty' : s.host + ':' + s.port));
    const countText = (s) => s.online ? s.numplayers + '/' + (s.maxclients ?? '?') : '';
    const thumb = (s) => (s.numplayers > 0 && s.map_thumb) ? s.map_thumb : '/img/bullet-thumb.jpg';

    function renderButton() {
        const s = byKey().get(current);
        if (!s) return;
        document.getElementById('srv-btn-thumb').src = thumb(s);
        document.getElementById('srv-btn-dot').className = 'absolute -right-1 -top-1 h-3 w-3 rounded-full ring-2 ring-black ' + dotClass(s);
        document.getElementById('srv-btn-name').textContent = s.name;
        document.getElementById('srv-btn-sub').textContent = subText(s);
        document.getElementById('srv-btn-count').textContent = countText(s);
    }

    function renderList() {
        list.innerHTML = groups.filter(g => g.servers.length).map(g =>
            '<div class="px-3 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-zinc-500">' + esc(g.title) + '</div>' +
            g.servers.map(s =>
                '<button type="button" role="option" data-server="' + esc(s.key) + '" data-q="' + esc((s.name + ' ' + (s.map || '')).toLowerCase()) + '"' +
                    ' aria-selected="' + (s.key === current) + '"' +
                    ' class="flex w-full items-center gap-3 px-2 py-1.5 text-left hover:bg-white/10 ' + (s.key === current ? 'bg-blue-600/30' : '') + '">' +
                    '<span class="relative h-9 w-16 shrink-0">' +
                        '<img src="' + esc(thumb(s)) + '" alt="" loading="lazy" class="h-9 w-16 rounded object-cover bg-zinc-800">' +
                        '<span class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full ring-2 ring-zinc-900 ' + dotClass(s) + '"></span>' +
                    '</span>' +
                    '<span class="min-w-0 flex-1">' +
                        '<span class="block truncate text-sm">' + esc(s.name) + '</span>' +
                        '<span class="block truncate text-[11px] text-zinc-500">' + esc(subText(s)) + '</span>' +
                    '</span>' +
                    '<span class="shrink-0 font-mono text-xs text-zinc-400">' + esc(countText(s)) + '</span>' +
                '</button>'
            ).join('')
        ).join('');
        filter();
    }

    function filter() {
        const q = search.value.trim().toLowerCase();
        list.querySelectorAll('[data-server]').forEach(b => b.classList.toggle('hidden', q !== '' && !b.dataset.q.includes(q)));
    }

    // --- picker open / close -----------------------------------------
    function open(show) {
        panel.classList.toggle('hidden', !show);
        btn.setAttribute('aria-expanded', show ? 'true' : 'false');
        if (show) {
            search.value = '';
            filter();
            search.focus();
            list.querySelector('[aria-selected="true"]')?.scrollIntoView({block: 'nearest'});
        }
    }
    btn.addEventListener('click', () => open(panel.classList.contains('hidden')));
    search.addEventListener('input', filter);
    search.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') list.querySelector('[data-server]:not(.hidden)')?.click();
    });
    document.addEventListener('click', (e) => { if (!picker.contains(e.target)) open(false); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') open(false); });
    list.addEventListener('click', (e) => {
        const b = e.target.closest('[data-server]');
        if (b) { select(b.dataset.server); open(false); }
    });

    // --- selection -----------------------------------------------------
    let current = null;
    let match = null;
    function select(key, pushHash = true) {
        if (!byKey().has(key) || key === current) return;
        current = key;
        if (pushHash && location.hash !== '#' + key) history.replaceState(null, '', '#' + key);
        renderButton();
        renderList();
        if (match) match.setKey(key);
        else match = window.initLiveMatch(document.querySelector('[data-live-match]'), key);
    }
    window.addEventListener('hashchange', () => select(decodeURIComponent(location.hash.slice(1)), false));

    // --- status polling -----------------------------------------------
    async function poll() {
        try {
            const res = await fetch('/live/status', { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!res.ok) return null;
            const data = await res.json();
            nextPollAt = Date.now() + POLL_MS;
            const ownPolled = new Map(data.servers.map(s => [s.key, s]));
            groups = [
                {title: 'Our servers', servers: own.map(s => ({...s, ...(ownPolled.get(s.key) || {})}))},
                {title: 'Clan match / inter', servers: ((data.clan && data.clan.servers) || []).filter(s => s.online)},
                {title: 'Elsewhere in AssaultCube', servers: (data.elsewhere && data.elsewhere.servers) || []},
            ];
            const ew = data.elsewhere;
            document.getElementById('live-elsewhere-sub').textContent = ew && ew.polled
                ? ew.players + ' player' + (ew.players === 1 ? '' : 's') + ' on ' + ew.online + ' other public server' + (ew.online === 1 ? '' : 's')
                : '';
            renderButton();
            renderList();
            return data;
        } catch (e) { return null; }
    }

    // --- boot: hash > busiest server > first ---------------------------
    (async () => {
        const hashKey = decodeURIComponent(location.hash.slice(1));
        const data = await poll();
        const all = byKey();
        let key = all.has(hashKey) ? hashKey : null;
        if (!key && data) {
            // busiest server anywhere - own list first, so it wins ties
            const busiest = [...all.values()].filter(s => s.online).sort((a, b) => b.numplayers - a.numplayers)[0];
            if (busiest && busiest.numplayers > 0) key = busiest.key;
        }
        select(key || own[0].key, !!hashKey);
        renderList();
        setInterval(poll, POLL_MS);
    })();
})();
</script>
<?php $this->end(); ?>
