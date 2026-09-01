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
.lg-table td { padding: .45rem .75rem; text-align: right; color: rgb(212 212 216); border-top: 1px solid rgb(63 63 70 / .5); }
.lg-table th:first-child, .lg-table td:first-child { width: 2.2rem; text-align: center; }
.lg-table th:nth-child(2), .lg-table td:nth-child(2) { width: 2rem; text-align: left; }
.lg-table td:nth-child(2) { color: rgb(113 113 122); }
.lg-table th:nth-child(3), .lg-table td:nth-child(3) { text-align: left; }
.lg-table tr.top td { background: rgb(255 255 255 / .04); }
.lg-table th:nth-child(n+4), .lg-table td:nth-child(n+4) { width: 3.4rem; }
.lg-decider { font-weight: 800; color: #fff; }
.lg-cla  .lg-decider-h { color: #fca5a5; } .lg-rvsf .lg-decider-h { color: #93c5fd; }
.lg-dead { opacity: .55; }
.lg-pulse { animation: lgpulse 2s ease-in-out infinite; }
@keyframes lgpulse { 50% { opacity: .45; } }
.lg-ping { display: inline-block; width: .65rem; height: .65rem; border-radius: 50%; vertical-align: middle;
  animation: lgpingpulse 3s ease-in-out infinite; }
@keyframes lgpingpulse { 50% { opacity: .5; transform: scale(.8); } }
.lg-chat { padding: .5rem .9rem; font-size: .8rem; max-height: 9rem; overflow-y: auto; margin-bottom: 1.5rem; }
.lg-chat > div { padding: .12rem 0; color: rgb(212 212 216); }
.lg-chat .t { color: rgb(113 113 122); margin-right: .45rem; font-variant-numeric: tabular-nums; font-size: .72rem; }
.lg-chat .v-hs { color: #fbbf24; } .lg-chat .v-tk { color: #f87171; } .lg-chat .v-flag { color: #4ade80; }
.lg-chat .empty { color: rgb(113 113 122); font-style: italic; }
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

    <div class="lg-team lg-chat js-chatbox" hidden>
        <div class="empty">waiting for action&hellip;</div>
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
                    <th>Ping</th><th>#</th><th>Player</th><th class="js-h-flags">Flags</th>
                    <th class="js-h-frags">Frags</th><th>Deaths</th><th>TK</th><th>Damage</th><th>Acc</th>
                </tr></thead>
                <tbody class="js-rows"></tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="lg-team js-ffa" hidden>
        <table class="lg-table">
            <thead><tr>
                <th>Ping</th><th>#</th><th>Player</th><th class="js-h-flags">Flags</th>
                <th class="js-h-frags">Frags</th><th>Deaths</th><th>TK</th><th>Damage</th><th>Acc</th>
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

    const pingBall = (ping) => {
        if (ping == null) return '';
        const hue = Math.max(0, 120 - Math.max(0, ping - 30) * 120 / 220); // <=30ms green, >=250ms red
        return '<span class="lg-ping" style="background:hsl(' + hue.toFixed(0) + ' 80% 45%)" title="' + esc(ping) + ' ms"></span>';
    };

    // Kill feed: the server only reports per-player counters, so each update is
    // diffed against the previous one and frag/death deltas are paired up.
    // The verb is guessed from the killer's currently selected gun (extinfo has
    // no real kill events), using the same wording AcLogParser sees in the logs.
    // A frag delta of +2 is a gib kill in AC: sniper headshot, knife or grenade.
    const GUN_VERBS = {0: 'slashed', 1: 'busted', 2: 'picked off', 3: 'peppered', 4: 'sprayed',
        5: 'punctured', 6: 'shredded', 7: 'busted', 8: 'gibbed', 9: 'splattered'};
    const GIB_VERBS = {0: 'slashed', 5: 'headshot', 8: 'gibbed'};
    let prevStats = null;
    const chatLines = [];

    function feedEvents(players) {
        const cur = {};
        players.forEach(p => { cur[p.name] = {
            frags: p.frags ?? 0, deaths: p.deaths ?? 0, teamkills: p.teamkills ?? 0,
            flags: p.flags ?? 0, team: (p.team || '').toUpperCase(), gun: p.gun,
        }; });
        const ev = [];
        if (prevStats) {
            const victims = [], killers = [];
            for (const name in cur) {
                const o = prevStats[name], s = cur[name];
                if (!o) continue;
                for (let i = s.deaths - o.deaths; i > 0; i--) victims.push({name, team: s.team});
                if (s.frags > o.frags || s.teamkills > o.teamkills) {
                    killers.push({name, team: s.team, gun: s.gun,
                        f: Math.max(0, s.frags - o.frags), t: Math.max(0, s.teamkills - o.teamkills)});
                }
                if (s.flags > o.flags) ev.push('<b>' + esc(name) + '</b> <span class="v-flag">scored with the flag</span>');
            }
            const take = (pred) => {
                const i = victims.findIndex(pred);
                return i >= 0 ? victims.splice(i, 1)[0] : null;
            };
            for (const k of killers) {
                const enemy = (x) => x.name !== k.name && (!k.team || x.team !== k.team);
                for (let i = k.t; i > 0; i--) {
                    const v = take(x => x.name !== k.name && (!k.team || x.team === k.team));
                    ev.push('<b>' + esc(k.name) + '</b> <span class="v-tk">teamkilled</span> <b>' + esc(v ? v.name : 'a teammate') + '</b>');
                }
                for (let f = k.f; f > 0;) {
                    const v = take(enemy);
                    if (!v) break;
                    if (f >= 2 && !victims.some(enemy)) {
                        ev.push('<b>' + esc(k.name) + '</b> <span class="v-hs">' + (GIB_VERBS[k.gun] ?? 'gibbed') + '</span> <b>' + esc(v.name) + '</b>');
                        f -= 2;
                    } else {
                        ev.push('<b>' + esc(k.name) + '</b> ' + (GUN_VERBS[k.gun] ?? 'killed') + ' <b>' + esc(v.name) + '</b>');
                        f -= 1;
                    }
                }
            }
            victims.forEach(v => ev.push('<b>' + esc(v.name) + '</b> <span class="v-tk">suicided</span>'));
        }
        prevStats = cur;
        return ev;
    }

    function renderFeed(players) {
        const box = $('.js-chatbox');
        box.hidden = false;
        const ev = feedEvents(players);
        if (!ev.length) return;
        const t = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
        ev.forEach(html => chatLines.push('<div><span class="t">' + t + '</span>' + html + '</div>'));
        while (chatLines.length > 50) chatLines.shift();
        box.innerHTML = chatLines.join('');
        box.scrollTop = box.scrollHeight;
    }

    const rowHtml = (p, i, byFlags) => {
        const name = p.player_id
            ? '<a href="/players/view/' + esc(p.player_id) + '" class="hover:text-blue-400">' + esc(p.name) + '</a>'
            : esc(p.name);
        const pic = '<img src="' + esc(p.picture || '/img/acl.png') + '" class="w-6 h-6 rounded-full object-cover inline-block mr-2 align-middle" alt="">';
        return '<tr class="' + (i === 0 ? 'top' : '') + (p.state_name === 'dead' ? ' lg-dead' : '') + '">' +
            '<td>' + pingBall(p.ping) + '</td>' +
            '<td>' + (i + 1) + '</td>' +
            '<td>' + pic + name + ' ' + flag(p.country) +
                (p.is_admin ? ' <span class="text-[10px] px-1 rounded bg-yellow-500/20 text-yellow-300">admin</span>' : '') + '</td>' +
            '<td class="' + (byFlags ? 'lg-decider' : '') + '">' + p.flags + '</td>' +
            '<td class="' + (!byFlags ? 'lg-decider' : '') + '">' + p.frags + '</td>' +
            '<td>' + p.deaths + '</td>' +
            '<td>' + p.teamkills + '</td>' +
            '<td>' + (p.damage ?? '') + '</td>' +
            '<td>' + (p.accuracy ?? 0) + '%</td>' +
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
            $('.js-chatbox').hidden = true;
            prevStats = null;
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
        renderFeed(players);

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
                panel.querySelector('.js-h-frags').classList.toggle('lg-decider-h', !s.by_flags);
                panel.querySelector('.js-h-flags').classList.toggle('lg-decider-h', s.by_flags);
                panel.querySelector('.js-rows').innerHTML =
                    members.map((p, i) => rowHtml(p, i, s.by_flags)).join('') ||
                    '<tr><td></td><td></td><td class="text-zinc-500" colspan="7">nobody</td></tr>';
            });
        } else {
            $('.js-teams').hidden = true;
            const ffa = $('.js-ffa');
            ffa.hidden = false;
            ffa.querySelector('.js-h-frags').classList.toggle('lg-decider-h', !s.by_flags);
            ffa.querySelector('.js-h-flags').classList.toggle('lg-decider-h', s.by_flags);
            players.sort(sort);
            ffa.querySelector('.js-rows').innerHTML = players.map((p, i) => rowHtml(p, i, s.by_flags)).join('') ||
                '<tr><td></td><td></td><td class="text-zinc-500" colspan="7">nobody playing</td></tr>';
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
            prevStats = null;
            chatLines.length = 0;
            const box = $('.js-chatbox');
            box.hidden = true;
            box.innerHTML = '<div class="empty">waiting for action&hellip;</div>';
            const st = $('.js-status');
            st.textContent = 'connecting…';
            st.className = 'js-status lg-pulse text-xs px-2 py-0.5 rounded-full bg-zinc-700 text-zinc-300';
            start();
        },
    };
};
</script>
