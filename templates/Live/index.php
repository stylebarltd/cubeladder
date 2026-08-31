<div class="w-full max-w-7xl px-4 sm:px-6 py-10 mx-auto">

<h1 class="text-4xl font-bold text-white mb-2 text-center">Live</h1>
<p class="text-center text-zinc-500 text-sm mb-8">
    Who is playing right now &middot; <span id="live-updated">loading&hellip;</span>
</p>

<?php if (empty($servers)): ?>
    <p class="text-center text-zinc-500">No servers configured (Ladder.servers).</p>
<?php else: ?>

<div id="live-servers" class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <?php foreach ($servers as $key => $cfg): ?>
        <div class="rounded-xl border border-zinc-700 bg-zinc-800 overflow-hidden" data-server="<?= h($key) ?>">
            <div class="p-4 md:p-5 border-b border-zinc-700/60">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-2xl font-bold text-white truncate">
                        <a href="/live/game/<?= h($key) ?>" class="hover:text-blue-400 transition" title="live match view"><?= h($cfg['name'] ?? $key) ?></a>
                    </h2>
                    <span class="js-status text-xs px-2 py-0.5 rounded-full bg-zinc-700 text-zinc-300">checking&hellip;</span>
                </div>
                <div class="js-desc text-xs text-zinc-500 truncate mt-0.5"><?= h($cfg['host']) ?>:<?= (int)$cfg['port'] ?></div>
                <div class="js-game flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-sm text-zinc-300"></div>
            </div>
            <div class="js-players"></div>
        </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<div id="live-elsewhere-wrap" class="mt-14" hidden>
    <h2 class="text-2xl font-bold text-white mb-1 text-center">Elsewhere in AssaultCube</h2>
    <p id="live-elsewhere-sub" class="text-center text-zinc-500 text-sm mb-6"></p>
    <div id="live-elsewhere" class="grid grid-cols-1 xl:grid-cols-2 gap-6"></div>
</div>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const root = document.getElementById('live-servers');
    if (!root) return;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const flag = (iso) => {
        if (!iso || iso.length !== 2) return '';
        const A = 0x1F1E6 - 65;
        return String.fromCodePoint(iso.toUpperCase().charCodeAt(0) + A, iso.toUpperCase().charCodeAt(1) + A);
    };
    const teamClass = (t) => {
        t = (t || '').toUpperCase();
        if (t.startsWith('CLA')) return 'bg-red-500/20 text-red-300';
        if (t.startsWith('RVSF')) return 'bg-blue-500/20 text-blue-300';
        return 'bg-zinc-700 text-zinc-400';
    };
    const fmtUptime = (s) => {
        if (s == null) return '';
        const d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600);
        return d > 0 ? d + 'd ' + h + 'h' : h + 'h ' + Math.floor(s % 3600 / 60) + 'm';
    };

    // Card skeleton for servers we do not know up front (master server list)
    const cardHtml = (srv) =>
        '<div class="rounded-xl border border-zinc-700 bg-zinc-800 overflow-hidden" data-server="' + esc(srv.key) + '">' +
            '<div class="p-4 md:p-5 border-b border-zinc-700/60">' +
                '<div class="flex items-center justify-between gap-3">' +
                    '<h2 class="text-2xl font-bold text-white truncate">' + esc(srv.name) + '</h2>' +
                    '<span class="js-status text-xs px-2 py-0.5 rounded-full bg-zinc-700 text-zinc-300"></span>' +
                '</div>' +
                '<div class="js-desc text-xs text-zinc-500 truncate mt-0.5">' + esc(srv.host) + ':' + esc(srv.port) + '</div>' +
                '<div class="js-game flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-sm text-zinc-300"></div>' +
            '</div>' +
            '<div class="js-players"></div>' +
        '</div>';

    function render(data) {
        document.getElementById('live-updated').textContent =
            'updated ' + new Date(data.fetched_at).toLocaleTimeString();

        data.servers.forEach(srv => {
            const card = root.querySelector('[data-server="' + CSS.escape(srv.key) + '"]');
            if (card) fillCard(card, srv);
        });

        renderElsewhere(data.elsewhere);
    }

    function renderElsewhere(ew) {
        const wrap = document.getElementById('live-elsewhere-wrap');
        const grid = document.getElementById('live-elsewhere');
        if (!ew || !ew.polled) { wrap.hidden = true; return; }

        wrap.hidden = false;
        document.getElementById('live-elsewhere-sub').textContent =
            ew.players + ' player' + (ew.players === 1 ? '' : 's') + ' on ' + ew.online + ' other public server' + (ew.online === 1 ? '' : 's') +
            (ew.servers.length ? '' : ' \u2014 all empty right now');

        // Rebuild: the set of busy servers changes from poll to poll
        grid.innerHTML = ew.servers.map(cardHtml).join('');
        ew.servers.forEach(srv => {
            const card = grid.querySelector('[data-server="' + CSS.escape(srv.key) + '"]');
            if (card) fillCard(card, srv);
        });
    }

    function fillCard(card, srv) {
        {
            const status = card.querySelector('.js-status');
            const game = card.querySelector('.js-game');
            const list = card.querySelector('.js-players');

            if (!srv.online) {
                status.textContent = 'offline';
                status.className = 'js-status text-xs px-2 py-0.5 rounded-full bg-red-500/20 text-red-300';
                game.innerHTML = '';
                list.innerHTML = '<p class="p-4 text-sm text-zinc-500">' + esc(srv.error || 'no reply') + '</p>';
                return;
            }

            const playing = srv.players.filter(p => !p.is_spectator);
            const specs = srv.players.filter(p => p.is_spectator);
            const teamMode = /team|flag/.test(srv.mode_name || '');
            const flagMode = /flag/.test(srv.mode_name || '');

            status.textContent = srv.numplayers + ' / ' + srv.maxclients + ' online';
            status.className = 'js-status text-xs px-2 py-0.5 rounded-full ' +
                (srv.numplayers > 0 ? 'bg-green-500/20 text-green-300' : 'bg-zinc-700 text-zinc-400');

            if (srv.own && srv.description) card.querySelector('.js-desc').textContent = srv.description;

            game.innerHTML =
                '<span><i class="fas fa-map mr-1 text-zinc-500"></i>' + esc(srv.map || '—') + '</span>' +
                '<span><i class="fas fa-gamepad mr-1 text-zinc-500"></i>' + esc(srv.mode_name) + '</span>' +
                '<span><i class="fas fa-clock mr-1 text-zinc-500"></i>' + esc(srv.minremain) + ' min left</span>' +
                (srv.uptime != null ? '<span class="text-zinc-500">up ' + fmtUptime(srv.uptime) + '</span>' : '');

            if (!srv.players.length) {
                list.innerHTML = '<p class="p-4 text-sm text-zinc-500">Nobody is playing right now.</p>';
                return;
            }

            let html = '<div class="overflow-x-auto"><table class="w-full text-sm">' +
                '<thead class="text-[11px] uppercase text-zinc-500"><tr>' +
                '<th class="text-left px-4 py-2">Player</th>' +
                (teamMode ? '<th class="text-left px-2 py-2">Team</th>' : '') +
                '<th class="text-right px-2 py-2">Frags</th>' +
                (flagMode ? '<th class="text-right px-2 py-2">Flags</th>' : '') +
                '<th class="text-right px-2 py-2">Deaths</th>' +
                '<th class="text-right px-2 py-2">TK</th>' +
                '<th class="text-right px-2 py-2">Acc</th>' +
                '<th class="text-right px-2 py-2">Ping</th>' +
                '<th class="text-left px-2 py-2 hidden md:table-cell">Weapon</th>' +
                '<th class="text-left px-4 py-2 hidden md:table-cell">State</th>' +
                '</tr></thead><tbody class="divide-y divide-zinc-700/60">';

            const row = (p) => {
                const name = p.player_id
                    ? '<a href="/players/view/' + esc(p.player_id) + '" class="hover:text-blue-400">' + esc(p.name) + '</a>'
                    : esc(p.name);
                return '<tr class="' + (p.is_spectator ? 'text-zinc-500' : '') + '">' +
                    '<td class="px-4 py-2"><div class="flex items-center gap-2">' +
                        '<img src="' + esc(p.picture || '/img/acl.png') + '" class="w-7 h-7 rounded-full object-cover shrink-0" alt="">' +
                        '<span class="font-semibold truncate">' + name + '</span>' +
                        (p.country ? '<span>' + flag(p.country) + '</span>' : '') +
                        (p.is_admin ? '<span class="text-[10px] px-1 rounded bg-yellow-500/20 text-yellow-300">admin</span>' : '') +
                    '</div></td>' +
                    (teamMode ? '<td class="px-2 py-2"><span class="text-[11px] px-1.5 py-0.5 rounded ' + teamClass(p.team) + '">' + esc(p.team) + '</span></td>' : '') +
                    '<td class="px-2 py-2 text-right font-mono text-blue-400">' + p.frags + '</td>' +
                    (flagMode ? '<td class="px-2 py-2 text-right font-mono">' + p.flags + '</td>' : '') +
                    '<td class="px-2 py-2 text-right font-mono">' + p.deaths + '</td>' +
                    '<td class="px-2 py-2 text-right font-mono">' + p.teamkills + '</td>' +
                    '<td class="px-2 py-2 text-right font-mono">' + p.accuracy + '%</td>' +
                    '<td class="px-2 py-2 text-right font-mono">' + p.ping + '</td>' +
                    '<td class="px-2 py-2 hidden md:table-cell text-zinc-400">' + esc(p.gun_name || '') + '</td>' +
                    '<td class="px-4 py-2 hidden md:table-cell text-zinc-400">' + esc(p.state_name || '') + '</td>' +
                    '</tr>';
            };

            playing.forEach(p => html += row(p));
            specs.forEach(p => html += row(p));
            html += '</tbody></table></div>';
            list.innerHTML = html;
        }
    }

    async function poll() {
        try {
            const res = await fetch('/live/status', { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            if (res.ok) render(await res.json());
        } catch (e) {
            console.log('[live] poll failed', e);
        }
    }

    poll();
    setInterval(poll, 10000);
})();
</script>
<?php $this->end(); ?>
