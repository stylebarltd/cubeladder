<?php
/**
 * Live page: server list in the left sidebar, the selected server's
 * running game (shared live_match element) on the right. Selection is
 * kept in the URL hash (/live#acka-assault).
 *
 * @var \App\View\AppView $this
 * @var array $servers  Ladder.servers minus hidden ones
 */
?>
<div class="w-full max-w-7xl px-4 sm:px-6 py-10 mx-auto">

<h1 class="text-4xl font-bold text-white mb-2 text-center">Live</h1>
<p class="text-center text-zinc-500 text-sm mb-8">
    Who is playing right now &middot; <span id="live-updated">loading&hellip;</span>
</p>

<?php if (empty($servers)): ?>
    <p class="text-center text-zinc-500">No servers configured (Ladder.servers).</p>
<?php else: ?>

<style>
.live-layout { display: flex; flex-direction: column; gap: 1.5rem; align-items: flex-start; }
.live-side { width: 100%; }
.live-main { flex: 1 1 0%; min-width: 0; width: 100%; }
@media (min-width: 1024px) {
    .live-layout { flex-direction: row; }
    .live-side { width: 18rem; flex-shrink: 0; }
}
</style>
<div class="live-layout">

    <!-- Sidebar: our servers + elsewhere -->
    <aside class="live-side">
        <div class="rounded-xl border border-zinc-700 bg-zinc-800 overflow-hidden divide-y divide-zinc-700/60" id="live-sidebar">
            <?php foreach ($servers as $key => $cfg): ?>
            <button type="button" data-server="<?= h($key) ?>"
                    class="w-full text-left p-3 hover:bg-zinc-700/60 transition flex items-center gap-3">
                <span class="js-dot w-2.5 h-2.5 rounded-full bg-zinc-600 shrink-0"></span>
                <span class="flex-1 min-w-0">
                    <span class="block font-semibold text-white truncate"><?= h($cfg['name'] ?? $key) ?></span>
                    <span class="js-sub block text-xs text-zinc-500 truncate"><?= h($cfg['host']) ?>:<?= (int)$cfg['port'] ?></span>
                </span>
                <span class="js-count text-xs font-mono text-zinc-400"></span>
            </button>
            <?php endforeach; ?>
        </div>

        <div id="live-elsewhere-wrap" class="mt-6" hidden>
            <h2 class="text-sm font-bold text-zinc-400 mb-1 px-1">Elsewhere in AssaultCube</h2>
            <p id="live-elsewhere-sub" class="text-xs text-zinc-600 mb-2 px-1"></p>
            <div id="live-elsewhere" class="rounded-xl border border-zinc-700 bg-zinc-800 overflow-hidden divide-y divide-zinc-700/60"></div>
        </div>

        <div id="live-clan-wrap" class="mt-6" hidden>
            <h2 class="text-sm font-bold text-zinc-400 mb-2 px-1">Clan Match / Inter Clan Servers</h2>
            <div id="live-clan" class="rounded-xl border border-zinc-700 bg-zinc-800 overflow-hidden divide-y divide-zinc-700/60"></div>
        </div>
    </aside>

    <!-- Selected server's running game -->
    <div class="live-main">
        <?= $this->element('live_match') ?>
    </div>

</div>

<?php endif; ?>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const sidebar = document.getElementById('live-sidebar');
    if (!sidebar) return;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const keys = [...sidebar.querySelectorAll('[data-server]')].map(b => b.dataset.server);

    // --- selection --------------------------------------------------
    let current = null;
    let match = null;

    function select(key, pushHash = true) {
        if (!keys.includes(key) || key === current) return;
        current = key;
        sidebar.querySelectorAll('[data-server]').forEach(b => {
            b.classList.toggle('bg-zinc-700', b.dataset.server === key);
        });
        if (pushHash && location.hash !== '#' + key) {
            history.replaceState(null, '', '#' + key);
        }
        if (match) match.setKey(key);
        else match = window.initLiveMatch(document.querySelector('[data-live-match]'), key);
    }

    sidebar.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-server]');
        if (btn) select(btn.dataset.server);
    });
    window.addEventListener('hashchange', () => {
        const key = decodeURIComponent(location.hash.slice(1));
        if (keys.includes(key)) select(key, false);
    });

    // --- sidebar status polling ------------------------------------
    function fillSidebar(data) {
        document.getElementById('live-updated').textContent =
            'updated ' + new Date(data.fetched_at).toLocaleTimeString();

        data.servers.forEach(srv => {
            const btn = sidebar.querySelector('[data-server="' + CSS.escape(srv.key) + '"]');
            if (!btn) return;
            const dot = btn.querySelector('.js-dot');
            const sub = btn.querySelector('.js-sub');
            const count = btn.querySelector('.js-count');
            if (!srv.online) {
                dot.className = 'js-dot w-2.5 h-2.5 rounded-full bg-red-500 shrink-0';
                sub.textContent = 'offline';
                count.textContent = '';
            } else {
                dot.className = 'js-dot w-2.5 h-2.5 rounded-full shrink-0 ' + (srv.numplayers > 0 ? 'bg-green-500' : 'bg-zinc-600');
                sub.textContent = srv.numplayers > 0
                    ? (srv.map || '—') + ' · ' + srv.mode_name
                    : 'empty';
                count.textContent = srv.numplayers + '/' + (srv.maxclients ?? '?');
            }
        });

        // clan match / inter servers: like elsewhere, but fixed list and no /connect
        const clanWrap = document.getElementById('live-clan-wrap');
        const clanList = document.getElementById('live-clan');
        const clan = ((data.clan && data.clan.servers) || []).filter(srv => srv.online);
        clanWrap.hidden = clan.length === 0;
        clanList.innerHTML = clan.map(srv => {
            const dot = srv.numplayers > 0 ? 'bg-green-500' : 'bg-zinc-600';
            const sub = srv.numplayers > 0 ? (srv.map || '—') + ' · ' + srv.mode_name : 'empty';
            return '<div class="p-3 flex items-center gap-3">' +
                '<span class="w-2.5 h-2.5 rounded-full shrink-0 ' + dot + '"></span>' +
                '<span class="flex-1 min-w-0">' +
                    '<span class="block text-sm font-semibold text-white truncate">' + esc(srv.name) + '</span>' +
                    '<span class="block text-xs text-zinc-500 truncate">' + esc(sub) + '</span>' +
                '</span>' +
                '<span class="text-xs font-mono text-zinc-400">' + srv.numplayers + '/' + (srv.maxclients ?? '?') + '</span>' +
            '</div>';
        }).join('');

        const wrap = document.getElementById('live-elsewhere-wrap');
        const listEl = document.getElementById('live-elsewhere');
        const ew = data.elsewhere;
        if (!ew || !ew.polled) { wrap.hidden = true; return; }
        wrap.hidden = false;
        document.getElementById('live-elsewhere-sub').textContent =
            ew.players + ' player' + (ew.players === 1 ? '' : 's') + ' on ' + ew.online + ' other public server' + (ew.online === 1 ? '' : 's');
        listEl.innerHTML = ew.servers.map(srv =>
            '<div class="p-3">' +
                '<div class="flex items-center gap-3">' +
                    '<span class="w-2.5 h-2.5 rounded-full bg-green-500 shrink-0"></span>' +
                    '<span class="flex-1 min-w-0">' +
                        '<span class="block text-sm font-semibold text-white truncate">' + esc(srv.name) + '</span>' +
                        '<span class="block text-xs text-zinc-500 truncate">' + esc(srv.map || '—') + ' · ' + esc(srv.mode_name) + '</span>' +
                    '</span>' +
                    '<span class="text-xs font-mono text-zinc-400">' + srv.numplayers + '</span>' +
                '</div>' +
                '<div class="text-[11px] text-zinc-600 mt-1 ml-5">/connect ' + esc(srv.host) + ' ' + esc(srv.port) + '</div>' +
            '</div>'
        ).join('') || '<p class="p-3 text-xs text-zinc-500">all quiet</p>';

        return data;
    }

    async function poll() {
        try {
            const res = await fetch('/live/status', { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!res.ok) return null;
            return fillSidebar(await res.json());
        } catch (e) { return null; }
    }

    // --- boot: hash > busiest server > first -----------------------
    (async () => {
        const hashKey = decodeURIComponent(location.hash.slice(1));
        const data = await poll();
        let key = keys.includes(hashKey) ? hashKey : null;
        if (!key && data) {
            const busiest = [...data.servers].filter(s => s.online).sort((a, b) => b.numplayers - a.numplayers)[0];
            if (busiest && busiest.numplayers > 0) key = busiest.key;
        }
        select(key || keys[0], !!hashKey);
        setInterval(poll, 10000);
    })();
})();
</script>
<?php $this->end(); ?>
