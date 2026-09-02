<?php
/**
 * Live page: our servers as a full-width bar on top, the selected server's
 * running game (shared live_match element) at full width below it, the
 * Elsewhere / Clan lists at the bottom. Selection is kept in the URL hash
 * (/live#acka-assault).
 *
 * @var \App\View\AppView $this
 * @var array $servers  Ladder.servers minus hidden ones
 */
?>
<div class="w-full px-4 sm:px-6 py-10 mx-auto">

<h1 class="text-4xl font-bold text-white mb-2 text-center">Live</h1>
<p class="text-center text-zinc-500 text-sm mb-8">
    Who is playing right now &middot; <span id="live-updated">loading&hellip;</span>
</p>

<?php if (empty($servers)): ?>
    <p class="text-center text-zinc-500">No servers configured (Ladder.servers).</p>
<?php else: ?>

<style>
.live-layout { display: flex; flex-direction: column; gap: 1.5rem; }
#live-sidebar { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: .75rem; }
#live-sidebar [data-server] { border: 1px solid rgb(63 63 70); border-radius: .75rem; background: rgb(39 39 42); }
#live-sidebar [data-server]:hover { background: rgb(63 63 70 / .6); }
#live-sidebar [data-server].sel { background: rgb(63 63 70); border-color: rgb(113 113 122); }
#live-clan [data-server]:hover { background: rgb(63 63 70 / .6); }
#live-clan [data-server].sel { background: rgb(63 63 70); }
.live-extra { display: grid; grid-template-columns: 1fr; gap: 1.5rem; align-items: start; }
@media (min-width: 1024px) { .live-extra { grid-template-columns: 1fr 1fr; } }
</style>
<div class="live-layout">

    <!-- Our servers: full-width bar on top -->
    <div id="live-sidebar">
        <?php foreach ($servers as $key => $cfg): ?>
        <button type="button" data-server="<?= h($key) ?>"
                class="text-left p-3 transition flex items-center gap-3">
            <span class="js-dot w-2.5 h-2.5 rounded-full bg-zinc-600 shrink-0"></span>
            <span class="flex-1 min-w-0">
                <span class="block font-semibold text-white truncate"><?= h($cfg['name'] ?? $key) ?></span>
                <span class="js-sub block text-xs text-zinc-500 truncate"><?= h($cfg['host']) ?>:<?= (int)$cfg['port'] ?></span>
            </span>
            <span class="js-count text-xs font-mono text-zinc-400"></span>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Selected server's running game: full width -->
    <div class="live-main">
        <?= $this->element('live_match') ?>
    </div>

    <!-- Elsewhere + Clan / Inter below the game -->
    <div class="live-extra">
        <div id="live-elsewhere-wrap" hidden>
            <h2 class="text-sm font-bold text-zinc-400 mb-1 px-1">Elsewhere in AssaultCube</h2>
            <p id="live-elsewhere-sub" class="text-xs text-zinc-600 mb-2 px-1"></p>
            <div id="live-elsewhere" class="rounded-xl border border-zinc-700 bg-zinc-800 overflow-hidden divide-y divide-zinc-700/60"></div>
        </div>

        <div id="live-clan-wrap" hidden>
            <h2 class="text-sm font-bold text-zinc-400 mb-2 px-1">Clan Match / Inter Clan Servers</h2>
            <div id="live-clan" class="rounded-xl border border-zinc-700 bg-zinc-800 overflow-hidden divide-y divide-zinc-700/60"></div>
        </div>
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
    // clan/inter servers are added to the selectable set once polled
    const keys = new Set([...sidebar.querySelectorAll('[data-server]')].map(b => b.dataset.server));

    // countdown to the next sidebar refresh
    const POLL_MS = 10000;
    let nextPollAt = Date.now() + POLL_MS;
    setInterval(() => {
        const sec = Math.max(0, Math.ceil((nextPollAt - Date.now()) / 1000));
        document.getElementById('live-updated').textContent = 'next update in ' + sec + ' sec';
    }, 250);

    // --- selection --------------------------------------------------
    let current = null;
    let match = null;

    function markSelected() {
        document.querySelectorAll('#live-sidebar [data-server], #live-clan [data-server]').forEach(b => {
            b.classList.toggle('sel', b.dataset.server === current);
        });
    }

    function select(key, pushHash = true) {
        if (!keys.has(key) || key === current) return;
        current = key;
        markSelected();
        if (pushHash && location.hash !== '#' + key) {
            history.replaceState(null, '', '#' + key);
        }
        if (match) match.setKey(key);
        else match = window.initLiveMatch(document.querySelector('[data-live-match]'), key);
    }

    const onServerClick = (e) => {
        const btn = e.target.closest('[data-server]');
        if (btn) select(btn.dataset.server);
    };
    sidebar.addEventListener('click', onServerClick);
    document.getElementById('live-clan').addEventListener('click', onServerClick);
    window.addEventListener('hashchange', () => {
        const key = decodeURIComponent(location.hash.slice(1));
        if (keys.has(key)) select(key, false);
    });

    // --- sidebar status polling ------------------------------------
    function fillSidebar(data) {
        nextPollAt = Date.now() + POLL_MS;

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
                dot.className = 'js-dot w-2.5 h-2.5 rounded-full shrink-0 ' + (srv.numplayers > 0 ? 'bg-green-500 lg-pulse' : 'bg-zinc-600');
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
            keys.add(srv.key);
            const dot = srv.numplayers > 0 ? 'bg-green-500 lg-pulse' : 'bg-zinc-600';
            const sub = srv.numplayers > 0 ? (srv.map || '—') + ' · ' + srv.mode_name : 'empty';
            return '<button type="button" data-server="' + esc(srv.key) + '" class="w-full text-left p-3 transition flex items-center gap-3">' +
                '<span class="w-2.5 h-2.5 rounded-full shrink-0 ' + dot + '"></span>' +
                '<span class="flex-1 min-w-0">' +
                    '<span class="block text-sm font-semibold text-white truncate">' + esc(srv.name) + '</span>' +
                    '<span class="block text-xs text-zinc-500 truncate">' + esc(sub) + '</span>' +
                '</span>' +
                '<span class="text-xs font-mono text-zinc-400">' + srv.numplayers + '/' + (srv.maxclients ?? '?') + '</span>' +
            '</button>';
        }).join('');
        markSelected();

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
                    '<span class="w-2.5 h-2.5 rounded-full bg-green-500 lg-pulse shrink-0"></span>' +
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
        let key = keys.has(hashKey) ? hashKey : null;
        if (!key && data) {
            const busiest = [...data.servers].filter(s => s.online).sort((a, b) => b.numplayers - a.numplayers)[0];
            if (busiest && busiest.numplayers > 0) key = busiest.key;
        }
        select(key || keys.values().next().value, !!hashKey);
        setInterval(poll, POLL_MS);
    })();
})();
</script>
<?php $this->end(); ?>
