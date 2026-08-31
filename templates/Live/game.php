<?php
/**
 * Live match view for one server: in-game style scoreboard, CLA vs RVSF
 * in two columns with team score bars. Polls /live/game_status/<key>.
 *
 * @var \App\View\AppView $this
 * @var array $server
 * @var string $key
 */
?>
<style>
.lg-wrap { max-width: 72rem; }
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
.lg-decider { font-weight: 800; color: #fff; }
.lg-cla  .lg-decider-h { color: #fca5a5; } .lg-rvsf .lg-decider-h { color: #93c5fd; }
.lg-dead { opacity: .55; }
.lg-pulse { animation: lgpulse 2s ease-in-out infinite; }
#lg-teams { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
@media (min-width: 880px) { #lg-teams { grid-template-columns: 1fr 1fr; } }
.lg-table th:nth-child(n+3), .lg-table td:nth-child(n+3) { width: 4.5rem; }
@keyframes lgpulse { 50% { opacity: .45; } }
</style>

<div class="lg-wrap w-full px-4 sm:px-6 py-10 mx-auto">

    <p class="text-sm mb-4">
        <a href="/live" class="text-zinc-500 hover:text-zinc-300">&larr; all live servers</a>
    </p>

    <div class="lg-hero mb-6">
        <div class="lg-hero-bg" id="lg-mapbg"></div>
        <div class="lg-hero-in">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h1 class="text-3xl font-bold text-white"><?= h($server['name'] ?? $key) ?></h1>
                <span id="lg-status" class="lg-pulse text-xs px-2 py-0.5 rounded-full bg-zinc-700 text-zinc-300">connecting&hellip;</span>
            </div>
            <div id="lg-game" class="text-zinc-300 text-sm"></div>
            <div class="text-xs text-zinc-400">
                <code class="bg-zinc-900/70 px-1.5 py-0.5 rounded">/connect <?= h($server['host']) ?> <?= (int)$server['port'] ?></code>
                <span id="lg-gamelink"></span>
            </div>
        </div>
    </div>

    <div id="lg-teams" hidden>
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

    <div id="lg-ffa" class="lg-team" hidden>
        <table class="lg-table">
            <thead><tr>
                <th>#</th><th>Player</th><th>Frags</th><th>Deaths</th><th>Flags</th>
            </tr></thead>
            <tbody class="js-rows"></tbody>
        </table>
    </div>

    <p id="lg-specs" class="text-sm text-zinc-500 mt-4" hidden></p>
    <p class="text-xs text-zinc-600 mt-6 text-center">updates every 5 seconds &middot; <span id="lg-updated"></span></p>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const KEY = <?= json_encode($key) ?>;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const flag = (iso) => {
        if (!iso || iso.length !== 2) return '';
        const A = 0x1F1E6 - 65;
        return String.fromCodePoint(iso.toUpperCase().charCodeAt(0) + A, iso.toUpperCase().charCodeAt(1) + A);
    };

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
        document.getElementById('lg-updated').textContent = new Date(data.fetched_at).toLocaleTimeString();
        const st = document.getElementById('lg-status');
        st.classList.remove('lg-pulse');

        if (!s.online) {
            st.textContent = 'offline';
            st.className = 'text-xs px-2 py-0.5 rounded-full bg-red-500/20 text-red-300';
            document.getElementById('lg-game').textContent = s.error || 'no reply';
            document.getElementById('lg-teams').hidden = true;
            document.getElementById('lg-ffa').hidden = true;
            return;
        }
        st.textContent = s.numplayers + ' / ' + (s.maxclients ?? '?');
        st.className = 'text-xs px-2 py-0.5 rounded-full ' + (s.numplayers > 0 ? 'bg-green-500/20 text-green-300' : 'bg-zinc-700 text-zinc-300');

        document.getElementById('lg-mapbg').style.backgroundImage = 'url(' + esc(s.map_image) + ')';
        document.getElementById('lg-game').innerHTML =
            '<b class="text-white">' + esc(s.map || '—') + '</b> · ' + esc(s.mode_name || '?') +
            (s.minremain != null ? ' · ' + esc(s.minremain) + ' min left' : '');
        document.getElementById('lg-gamelink').innerHTML = s.game_id
            ? ' &middot; <a class="text-blue-400 hover:underline" href="/games/view/' + esc(s.game_id) + '">this game on the ladder</a>' : '';

        const players = (s.players || []).filter(p => !p.is_spectator);
        const specs = (s.players || []).filter(p => p.is_spectator);
        const dec = s.by_flags ? 'flags' : 'frags';
        const sort = (a, b) => (b[dec] - a[dec]) || (b.frags - a.frags) || (a.deaths - b.deaths);

        const specsEl = document.getElementById('lg-specs');
        specsEl.hidden = specs.length === 0;
        specsEl.innerHTML = specs.length ? '👁 Spectating: <b class="text-zinc-300">' + specs.map(p => esc(p.name)).join(', ') + '</b>' : '';

        if (s.team_mode) {
            document.getElementById('lg-teams').hidden = false;
            document.getElementById('lg-ffa').hidden = true;
            document.querySelectorAll('#lg-teams [data-team]').forEach(panel => {
                const team = panel.dataset.team;
                const members = players.filter(p => (p.team || '').toUpperCase().startsWith(team)).sort(sort);
                panel.querySelector('.js-score').textContent = members.reduce((n, p) => n + p[dec], 0);
                panel.querySelector('.js-decider-h').textContent = s.by_flags ? 'Flags' : 'Frags';
                panel.querySelector('.js-decider-h').className = 'js-decider-h lg-decider-h';
                // deciding column stays "Flags"; swap header order visually via bold only
                panel.querySelector('.js-rows').innerHTML =
                    members.map((p, i) => rowHtml(p, i, s.by_flags)).join('') ||
                    '<tr><td></td><td class="text-zinc-500">nobody</td><td></td><td></td><td></td></tr>';
            });
        } else {
            document.getElementById('lg-teams').hidden = true;
            const ffa = document.getElementById('lg-ffa');
            ffa.hidden = false;
            players.sort(sort);
            ffa.querySelector('.js-rows').innerHTML = players.map((p, i) => rowHtml(p, i, s.by_flags)).join('') ||
                '<tr><td></td><td class="text-zinc-500">nobody playing</td><td></td><td></td><td></td></tr>';
        }
    }

    async function tick() {
        try {
            const res = await fetch('/live/game_status/' + encodeURIComponent(KEY), {headers: {Accept: 'application/json'}});
            if (res.ok) render(await res.json());
        } catch (e) { /* next tick */ }
    }
    tick();
    setInterval(tick, 5000);
})();
</script>
<?php $this->end(); ?>
