<?php
/**
 * Live match view skeleton + JS. Renders one server's running game in the
 * in-game scoreboard style (map hero, CLA | RVSF with team score bars).
 *
 * Include once per page, then boot it with:
 *   window.initLiveMatch(rootElement, serverKey)
 * The returned handle has .stop() and .setKey(key) - the /live page uses
 * setKey() when another server is selected in the sidebar.
 */
?>
<style>
.lg-hero { position: relative; border-radius: 0.75rem; overflow: hidden; border: 1px solid rgb(63 63 70); min-height: 11rem; }
.lg-hero-bg { position: absolute; inset: 0; background-size: cover; background-position: center; filter: brightness(.45) saturate(.9); }
.lg-hero-in { position: relative; padding: 1.5rem; display: flex; flex-direction: column; gap: .35rem; min-height: 11rem; justify-content: flex-end;
  background: linear-gradient(180deg, rgba(9,9,11,.15), rgba(9,9,11,.75)); }
.lg-team { border-radius: 0.75rem; overflow: hidden; border: 1px solid rgb(63 63 70); background: rgb(24 24 27 / .92); }
.lg-team-bar { display: flex; align-items: center; justify-content: space-between; padding: .7rem 1rem; }
.lg-team-bar .score { font-size: 2.4rem; font-weight: 800; line-height: 1; color: #fff; font-variant-numeric: tabular-nums; }
.lg-cla  .lg-team-bar { background: linear-gradient(90deg, #7f1d1d, #b91c1c); }
.lg-rvsf .lg-team-bar { background: linear-gradient(90deg, #172554, #1d4ed8); }
.lg-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
.lg-table th { font-size: .68rem; letter-spacing: .08em; text-transform: uppercase; color: rgb(161 161 170); text-align: right; padding: .5rem .75rem; }
.lg-table th:first-child, .lg-table th:nth-child(2) { text-align: left; }
.lg-table td { padding: .45rem .75rem; text-align: right; color: rgb(212 212 216); border-top: 1px solid rgb(63 63 70 / .5); }
.lg-table td:first-child { color: rgb(113 113 122); width: 2rem; text-align: left; }
.lg-table td:nth-child(2) { text-align: left; }
.lg-table tr.top td { background: rgb(255 255 255 / .04); }
.lg-table th:nth-child(n+3), .lg-table td:nth-child(n+3) { width: 4.5rem; }
.lg-decider { font-weight: 800; color: #fff; }
.lg-cla  .lg-decider-h { color: #fca5a5; } .lg-rvsf .lg-decider-h { color: #93c5fd; }
.lg-dead { opacity: .55; }
.lg-pulse { animation: lgpulse 2s ease-in-out infinite; }
@keyframes lgpulse { 50% { opacity: .45; } }
.lg-teams { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
@media (min-width: 1100px) { .lg-teams { grid-template-columns: 1fr 1fr; } }
</style>

<div data-live-match>
    <div class="lg-hero mb-6">
        <div class="lg-hero-bg js-mapbg"></div>
        <div class="lg-hero-in">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h1 class="text-3xl font-bold text-white js-title">&nbsp;</h1>
                <span class="js-status lg-pulse text-xs px-2 py-0.5 rounded-full bg-zinc-700 text-zinc-300">connecting&hellip;</span>
            </div>
            <div class="js-game text-zinc-300 text-sm"></div>
            <div class="text-xs text-zinc-400">
                <code class="bg-zinc-900/70 px-1.5 py-0.5 rounded js-connect"></code>
                <span class="js-gamelink"></span>
            </div>
        </div>
    </div>

    <div class="lg-teams js-teams" hidden>
        <?php foreach (['CLA' => 'lg-cla', 'RVSF' => 'lg-rvsf'] as $team => $cls): ?>
        <div class="lg-team <?= $cls ?>" data-team="<?= $team ?>">
            <div class="lg-team-bar">
                <span class="text-xl font-extrabold text-white tracking-wide"><?= $team ?></span>
                <span class="score js-score">0</span>
            </div>
            <table class="lg-table">
                <thead><tr>
                    <th>#</th><th>Player</th><th>Frags</th><th>Deaths</th><th class="js-decider-h">Flags</th>
                </tr></thead>
                <tbody class="js-rows"></tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="lg-team js-ffa" hidden>
        <table class="lg-table">
            <thead><tr>
                <th>#</th><th>Player</th><th>Frags</th><th>Deaths</th><th>Flags</th>
            </tr></thead>
            <tbody class="js-rows"></tbody>
        </table>
    </div>

    <p class="js-specs text-sm text-zinc-500 mt-4" hidden></p>
    <p class="text-xs text-zinc-600 mt-6 text-center">updates every 5 seconds &middot; <span class="js-updated"></span></p>
</div>

<script>
window.initLiveMatch = function (root, key) {
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const flag = (iso) => {
        if (!iso || iso.length !== 2) return '';
        const A = 0x1F1E6 - 65;
        return String.fromCodePoint(iso.toUpperCase().charCodeAt(0) + A, iso.toUpperCase().charCodeAt(1) + A);
    };
    const $ = (sel) => root.querySelector(sel);

    const rowHtml = (p, i, byFlags) => {
        const name = p.player_id
            ? '<a href="/players/view/' + esc(p.player_id) + '" class="hover:text-blue-400">' + esc(p.name) + '</a>'
            : esc(p.name);
        const pic = '<img src="' + esc(p.picture || '/img/acl.png') + '" class="w-6 h-6 rounded-full object-cover inline-block mr-2 align-middle" alt="">';
        return '<tr class="' + (i === 0 ? 'top' : '') + (p.state_name === 'dead' ? ' lg-dead' : '') + '">' +
            '<td>' + (i + 1) + '</td>' +
            '<td>' + pic + name + ' ' + flag(p.country) + '</td>' +
            '<td class="' + (!byFlags ? 'lg-decider' : '') + '">' + p.frags + '</td>' +
            '<td>' + p.deaths + '</td>' +
            '<td class="' + (byFlags ? 'lg-decider' : '') + '">' + p.flags + '</td>' +
        '</tr>';
    };

    function render(data) {
        const s = data.server;
        $('.js-updated').textContent = new Date(data.fetched_at).toLocaleTimeString();
        $('.js-title').textContent = s.name || key;
        $('.js-connect').textContent = '/connect ' + s.host + ' ' + s.port;
        const st = $('.js-status');
        st.classList.remove('lg-pulse');

        if (!s.online) {
            st.textContent = 'offline';
            st.className = 'js-status text-xs px-2 py-0.5 rounded-full bg-red-500/20 text-red-300';
            $('.js-game').textContent = s.error || 'no reply';
            $('.js-mapbg').style.backgroundImage = '';
            $('.js-gamelink').innerHTML = '';
            $('.js-teams').hidden = true;
            $('.js-ffa').hidden = true;
            $('.js-specs').hidden = true;
            return;
        }
        st.textContent = s.numplayers + ' / ' + (s.maxclients ?? '?');
        st.className = 'js-status text-xs px-2 py-0.5 rounded-full ' + (s.numplayers > 0 ? 'bg-green-500/20 text-green-300' : 'bg-zinc-700 text-zinc-300');

        $('.js-mapbg').style.backgroundImage = 'url(' + esc(s.map_image) + ')';
        $('.js-game').innerHTML =
            '<b class="text-white">' + esc(s.map || '—') + '</b> · ' + esc(s.mode_name || '?') +
            (s.minremain != null ? ' · ' + esc(s.minremain) + ' min left' : '');
        $('.js-gamelink').innerHTML = s.game_id
            ? ' &middot; <a class="text-blue-400 hover:underline" href="/games/view/' + esc(s.game_id) + '">this game on the ladder</a>' : '';

        const players = (s.players || []).filter(p => !p.is_spectator);
        const specs = (s.players || []).filter(p => p.is_spectator);
        const dec = s.by_flags ? 'flags' : 'frags';
        const sort = (a, b) => (b[dec] - a[dec]) || (b.frags - a.frags) || (a.deaths - b.deaths);

        const specsEl = $('.js-specs');
        specsEl.hidden = specs.length === 0;
        specsEl.innerHTML = specs.length ? '👁 Spectating: <b class="text-zinc-300">' + specs.map(p => esc(p.name)).join(', ') + '</b>' : '';

        if (s.team_mode) {
            $('.js-teams').hidden = false;
            $('.js-ffa').hidden = true;
            root.querySelectorAll('.js-teams [data-team]').forEach(panel => {
                const team = panel.dataset.team;
                const members = players.filter(p => (p.team || '').toUpperCase().startsWith(team)).sort(sort);
                panel.querySelector('.js-score').textContent = members.reduce((n, p) => n + p[dec], 0);
                panel.querySelector('.js-decider-h').textContent = s.by_flags ? 'Flags' : 'Frags';
                panel.querySelector('.js-decider-h').className = 'js-decider-h lg-decider-h';
                panel.querySelector('.js-rows').innerHTML =
                    members.map((p, i) => rowHtml(p, i, s.by_flags)).join('') ||
                    '<tr><td></td><td class="text-zinc-500">nobody</td><td></td><td></td><td></td></tr>';
            });
        } else {
            $('.js-teams').hidden = true;
            const ffa = $('.js-ffa');
            ffa.hidden = false;
            players.sort(sort);
            ffa.querySelector('.js-rows').innerHTML = players.map((p, i) => rowHtml(p, i, s.by_flags)).join('') ||
                '<tr><td></td><td class="text-zinc-500">nobody playing</td><td></td><td></td><td></td></tr>';
        }
    }

    let timer = null;
    async function tick() {
        try {
            const res = await fetch('/live/game_status/' + encodeURIComponent(key), {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (res.ok) render(await res.json());
        } catch (e) { /* next tick */ }
    }
    function start() {
        tick();
        timer = setInterval(tick, 5000);
    }
    start();

    return {
        stop: () => clearInterval(timer),
        setKey: (k) => {
            clearInterval(timer);
            key = k;
            const st = $('.js-status');
            st.textContent = 'connecting…';
            st.className = 'js-status lg-pulse text-xs px-2 py-0.5 rounded-full bg-zinc-700 text-zinc-300';
            start();
        },
    };
};
</script>
