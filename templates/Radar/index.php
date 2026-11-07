<?php
/**
 * Radar: which map is this? A piece of a map's floor plan grows by itself,
 * the player picks one of four maps. Rules and checks: App\Service\Radar
 * (the questions stay on the server; the page gets choices and pieces and
 * sends an answer, the server keeps the time and the score).
 *
 * @var \App\View\AppView $this
 * @var array $week
 * @var array $allTime
 * @var array $pool [{answer, maps, plays, place, factor}]
 * @var bool $ready
 * @var string|null $linkCode
 * @var \App\Model\Entity\Player|null $authPlayer
 */
use App\Service\Radar;

$this->assign('title', 'Radar');
$ogSite = rtrim((string)\Cake\Core\Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh', '/');
$ogText = sprintf('Which map is this? %d maps, a piece of each one\'s floor plan that grows every %d seconds - the faster you know it, the more points. A weekly and an all-time board.',
    Radar::QUESTIONS, Radar::STEP_SECONDS);
$this->start('meta'); ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="cubeLadder">
    <meta property="og:title" content="Radar · cubeLadder">
    <meta property="og:description" content="<?= h($ogText) ?>">
    <meta property="og:url" content="<?= h($ogSite . '/radar') ?>">
    <meta name="description" content="<?= h($ogText) ?>">
<?php $this->end(); ?>
<style>
.rd-game { position: relative; width: 100%; max-width: <?= Radar::PIECE_PX ?>px; margin: 0 auto; }
.rd-view { position: relative; aspect-ratio: 1; border-radius: .75rem; overflow: hidden; outline: 1px solid rgb(255 255 255 / .15); background: #1e1a16; }
.rd-view img { position: absolute; inset: 0; width: 100%; height: 100%; image-rendering: auto; transition: opacity .15s; }
.rd-view img:not([src]) { visibility: hidden; }
.rd-view img.full { opacity: 0; }
.rd-view.reveal img.full { opacity: 1; }
.rd-top { display: flex; justify-content: space-between; align-items: baseline; font-size: .8rem; color: rgb(161 161 170); margin-bottom: .4rem;
  font-variant-numeric: tabular-nums; }
.rd-top b { color: #fff; font-size: 1rem; }
.rd-top .sc b { color: #facc15; font-size: 1.2rem; }
.rd-time { position: relative; height: .45rem; border-radius: 999px; background: rgb(255 255 255 / .1); overflow: hidden; margin: .5rem 0 .6rem; }
.rd-time i { position: absolute; inset: 0 auto 0 0; width: 100%; background: linear-gradient(90deg, #b45309, #facc15); transform-origin: left; }
.rd-time span { position: absolute; top: 0; bottom: 0; width: 2px; background: rgb(0 0 0 / .55); }
.rd-choices { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
.rd-choice { padding: .85rem .5rem; border-radius: .5rem; font-weight: 800; font-size: .95rem; color: #fff; cursor: pointer;
  background: rgb(255 255 255 / .08); border: 1px solid rgb(255 255 255 / .2); transition: background .1s; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rd-choice:hover:not(:disabled) { background: rgb(250 204 21 / .2); border-color: rgb(250 204 21 / .6); }
.rd-choice:disabled { cursor: default; }
.rd-choice.right { background: rgb(34 197 94 / .35); border-color: #22c55e; }
.rd-choice.wrong { background: rgb(239 68 68 / .35); border-color: #ef4444; }
.rd-choice.dim { opacity: .45; }
.rd-choice kbd { font: 600 .65rem ui-monospace, monospace; color: rgb(161 161 170); margin-right: .35rem; }
.rd-hint { min-height: 2.6rem; text-align: center; font-size: .85rem; color: rgb(212 212 216); margin-top: .5rem; }
.rd-hint b { color: #fff; }
.rd-hint .pts { color: #facc15; font-weight: 800; }
.rd-hint .no { color: #f87171; font-weight: 800; }
.rd-next { text-align: center; min-height: 3.2rem; }
.rd-next .rd-btn { margin-top: .2rem; padding: .55rem 1.4rem; font-size: .9rem; }
.rd-next span { font-weight: 600; opacity: .7; }
.rd-overlay { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .6rem;
  text-align: center; padding: 1.5rem; background: rgb(0 0 0 / .72); backdrop-filter: blur(2px); border-radius: .75rem; z-index: 2; }
.rd-overlay[hidden] { display: none; }
.rd-overlay h2 { font-size: 1.8rem; font-weight: 800; color: #fff; letter-spacing: .03em; }
.rd-overlay p { color: rgb(212 212 216); font-size: .9rem; max-width: 26rem; }
.rd-btn { margin-top: .4rem; padding: .7rem 1.6rem; border-radius: .5rem; font-weight: 800; font-size: 1rem; letter-spacing: .05em;
  background: linear-gradient(90deg, #b45309, #facc15); color: #1c1917; border: 1px solid rgb(255 255 255 / .25); cursor: pointer; }
.rd-btn:hover { filter: brightness(1.1); }
.rd-btn:disabled { opacity: .6; cursor: wait; }
.rd-score { font-size: 3.2rem; font-weight: 800; color: #facc15; line-height: 1; font-variant-numeric: tabular-nums; }
.rd-result { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; margin: .4rem 0; width: 100%; max-width: 22rem; }
.rd-result div { background: rgb(255 255 255 / .07); border-radius: .5rem; padding: .45rem .6rem; }
.rd-result b { display: block; font-size: 1.25rem; color: #fff; font-variant-numeric: tabular-nums; }
.rd-result span { font-size: .65rem; text-transform: uppercase; letter-spacing: .05em; color: rgb(161 161 170); }
.rd-dots { display: flex; gap: .3rem; justify-content: center; margin-top: .6rem; }
.rd-dots i { width: .55rem; height: .55rem; border-radius: 50%; background: rgb(255 255 255 / .15); }
.rd-dots i.right { background: #22c55e; }
.rd-dots i.wrong { background: #ef4444; }
.rd-dots i.now { outline: 2px solid #facc15; outline-offset: 1px; }
.at-card { border-radius: .75rem; border: 1px solid rgb(255 255 255 / .15); background: rgb(0 0 0 / .6); backdrop-filter: blur(2px); padding: 1rem 1.1rem; }
.at-card h3 { font-weight: 800; color: #fff; display: flex; justify-content: space-between; align-items: baseline; margin-bottom: .5rem; }
.at-card h3 small { font-weight: 400; font-size: .7rem; color: rgb(161 161 170); }
.at-board { width: 100%; font-size: .85rem; border-collapse: collapse; font-variant-numeric: tabular-nums; }
.at-board td { padding: .32rem .25rem; border-top: 1px solid rgb(63 63 70 / .5); color: rgb(212 212 216); white-space: nowrap; }
.at-board td.n { color: rgb(113 113 122); width: 1.8rem; }
.at-board td.p { width: 100%; max-width: 0; overflow: hidden; text-overflow: ellipsis; }
.at-board td.p small { color: rgb(113 113 122); margin-left: .25rem; }
.at-board td.s { text-align: right; font-weight: 800; color: #facc15; }
.at-board td.c { text-align: right; color: rgb(212 212 216); }
.at-board th { font-size: .62rem; letter-spacing: .05em; text-transform: uppercase; color: rgb(113 113 122); font-weight: 600;
  text-align: right; padding: 0 .25rem .3rem; white-space: nowrap; }
.at-board th.l { text-align: left; }
.at-board tr.me td { background: rgb(250 204 21 / .1); }
.at-board a:hover { color: #60a5fa; }
.at-points td.p { white-space: normal; max-width: none; }
.at-note { font-size: .8rem; color: rgb(161 161 170); }
.at-note b { color: #fff; }
.at-note a { color: #facc15; }
.at-link { border-color: rgb(250 204 21 / .5); }
.at-code { font: 800 2rem/1.2 ui-monospace, monospace; letter-spacing: .08em; color: #facc15; text-align: center; padding: .35rem 0; user-select: all; }
.at-link .at-note + .at-note { margin-top: .4rem; }
.at-dots { display: inline-block; width: .5rem; height: .5rem; border-radius: 50%; background: #facc15; animation: at-pulse 1.6s ease-in-out infinite; vertical-align: middle; }
@keyframes at-pulse { 0%, 100% { opacity: .2; } 50% { opacity: 1; } }
.at-empty { font-size: .8rem; color: rgb(113 113 122); font-style: italic; }
@media (max-width: 640px) {
  .rd-overlay h2 { font-size: 1.4rem; }
  .rd-score { font-size: 2.4rem; }
  .rd-choice { font-size: .85rem; padding: .8rem .4rem; }
  .rd-choice kbd { display: none; }
}
</style>

<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center bg-fixed text-white"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.65), rgba(0,0,0,.5) 40%, rgba(0,0,0,.85)), url('/img/bullet.jpg');">
<div class="mx-auto w-full max-w-7xl px-4 py-6 md:px-16 md:py-8">

    <div class="mb-6 drop-shadow-lg">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-wide">Radar</h1>
        <p class="mt-2 text-sm text-zinc-300">
            Which map is this? <?= Radar::QUESTIONS ?> maps from the ones played most on cubeLadder. You see a piece of the floor plan &ndash;
            it grows every <?= Radar::STEP_SECONDS ?> seconds. The sooner you know it, the more points; a wrong answer scores nothing.
            Works on your phone too. Just for fun &ndash; not part of the ladder.
            Rather shoot something? <a href="/play" class="text-yellow-300 hover:underline">Aim trainer</a>.
        </p>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,30rem)_1fr]">
        <div class="rd-game" id="rd">
            <div class="rd-top">
                <span>Map <b id="rd-n">1</b> / <?= Radar::QUESTIONS ?></span>
                <span class="sc">Score <b id="rd-score">0</b></span>
            </div>
            <div class="rd-view" id="rd-view">
                <img id="rd-img" alt="A piece of a map's floor plan">
                <img id="rd-full" class="full" alt="">
                <div class="rd-overlay" id="rd-start">
                    <h2>WHICH MAP?</h2>
                    <?php if ($ready): ?>
                    <p>A piece of a map's floor plan, seen from above: dark is low ground, sand is high.
                        It grows every <?= Radar::STEP_SECONDS ?> seconds until you see the whole map &ndash; pick it before the time runs out.</p>
                    <p class="at-note"><?= $authPlayer
                        ? 'Playing as <b>' . h($authPlayer->name) . '</b> &ndash; your best round goes on the board.'
                        : 'You are playing as a guest: your score counts only for you. To get on the board, say <b>' . h((string)$linkCode) . '</b> in a game on a cubeLadder server.' ?></p>
                    <button type="button" class="rd-btn" id="rd-go">START</button>
                    <?php else: ?>
                    <p>Radar is being set up &ndash; come back soon.</p>
                    <?php endif; ?>
                </div>
                <div class="rd-overlay" id="rd-end" hidden></div>
            </div>
            <div class="rd-time"><i id="rd-bar"></i>
                <?php for ($s = 1; $s < count(Radar::STEPS); $s++): ?>
                <span style="left: <?= round(100 * $s * Radar::STEP_SECONDS / Radar::QUESTION_SECONDS, 2) ?>%"></span>
                <?php endfor; ?>
            </div>
            <div class="rd-choices" id="rd-choices">
                <?php for ($i = 0; $i < Radar::CHOICES; $i++): ?>
                <button type="button" class="rd-choice" disabled><kbd><?= $i + 1 ?></kbd><span>&nbsp;</span></button>
                <?php endfor; ?>
            </div>
            <div class="rd-hint" id="rd-hint"></div>
            <div class="rd-next"><button type="button" class="rd-btn" id="rd-next" hidden>NEXT MAP <span id="rd-next-s"></span></button></div>
            <div class="rd-dots" id="rd-dots"><?= str_repeat('<i></i>', Radar::QUESTIONS) ?></div>
        </div>

        <div class="grid items-start gap-6 md:grid-cols-2">
            <?php if (!$authPlayer && $linkCode): ?>
            <!-- not recognised by IP: prove who you are with a code in the game chat (PlayerLink, the same code as on /play) -->
            <div class="at-card at-link" id="at-link">
                <h3>Get on the board <small>playing as guest</small></h3>
                <p class="at-note">Say this in the chat of any game on a cubeLadder server:</p>
                <div class="at-code"><?= h($linkCode) ?></div>
                <p class="at-note">The servers' logs are read every 10 minutes. This page waits and recognises you by itself &ndash;
                    on this browser for a year. The code is valid for <?= \App\Service\PlayerLink::VALID_HOURS ?> hours.</p>
                <p class="at-note js-link-state"><span class="at-dots"></span> waiting for your code&hellip;</p>
            </div>
            <?php endif; ?>
            <div class="at-card">
                <h3>This week <small>resets Monday 00:00 UTC</small></h3>
                <div id="rd-week"></div>
            </div>
            <div class="at-card">
                <h3>All time</h3>
                <div id="rd-all"></div>
            </div>
            <!-- the points, as the server counts them (Radar::points / score) -->
            <div class="at-card">
                <h3>Points <small>per right answer</small></h3>
                <table class="at-board at-points">
                    <?php foreach (Radar::POINTS as $s => [$from, $to]): ?>
                    <tr><td class="p"><?= ['Half of the map', 'Three quarters of the map', 'The whole map'][$s] ?? 'Step ' . ($s + 1) ?> shown
                        <small><?= $s * Radar::STEP_SECONDS ?>&ndash;<?= ($s + 1) * Radar::STEP_SECONDS ?> s</small></td>
                        <td class="s"><?= $from ?>&ndash;<?= $to ?></td></tr>
                    <?php endforeach; ?>
                    <tr><td class="p">Every <?= Radar::STREAK ?>rd right answer in a row</td><td class="s">+<?= Radar::POINTS_STREAK ?></td></tr>
                </table>
                <p class="at-note" style="margin-top:.5rem">Points fall the longer you look. Maps played less are worth more:
                    &times;<?= implode(', &times;', array_map(fn($f) => rtrim(rtrim(number_format($f, 2), '0'), '.'), array_values(Radar::RARITY))) ?>
                    by their place below. Wrong or too late: 0, and the streak ends.</p>
            </div>
            <div class="at-card">
                <h3>The maps <small>most played, last 90 days</small></h3>
                <?php if ($pool): ?>
                <table class="at-board">
                    <?php foreach ($pool as $p): ?>
                    <tr><td class="n"><?= (int)$p['place'] ?></td>
                        <td class="p"><a href="/maps?map=<?= h(rawurlencode($p['answer'])) ?>" target="_blank" rel="noopener"><?= h($p['answer']) ?></a><?php
                            $also = array_values(array_diff($p['maps'], [$p['answer']]));
                            if ($also): ?><small>also <?= h(implode(', ', $also)) ?></small><?php endif; ?></td>
                        <td class="c">&times;<?= rtrim(rtrim(number_format((float)$p['factor'], 2), '0'), '.') ?></td></tr>
                    <?php endforeach; ?>
                </table>
                <p class="at-note" style="margin-top:.5rem">Maps with the same layout count as one answer.</p>
                <?php else: ?>
                <p class="at-empty">Coming soon.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const RULES = <?= json_encode([
        'questions' => Radar::QUESTIONS, 'stepSeconds' => Radar::STEP_SECONDS, 'questionSeconds' => Radar::QUESTION_SECONDS,
        'steps' => count(Radar::STEPS), 'points' => Radar::POINTS,
    ]) ?>;
    const ME = <?= json_encode($authPlayer?->id) ?>;
    const CSRF = document.querySelector('meta[name="csrfToken"]')?.content || '';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const flag = (iso) => {
        if (!iso || iso.length !== 2) return '';
        const A = 0x1F1E6 - 65;
        return String.fromCodePoint(iso.toUpperCase().charCodeAt(0) + A, iso.toUpperCase().charCodeAt(1) + A);
    };
    let playing = false;

    // guest with a link code: the same check as on /play (every 20 s; reload once recognised, not during a round)
    (function () {
        const box = document.getElementById('at-link');
        if (!box) return;
        const state = box.querySelector('.js-link-state');
        const started = Date.now();
        const tick = async () => {
            if (Date.now() - started > <?= \App\Service\PlayerLink::VALID_HOURS ?> * 3600e3) { state.textContent = 'Code expired - reload the page for a new one.'; return; }
            try {
                const r = await (await fetch('/play/link', {headers: {'Accept': 'application/json'}, cache: 'no-store'})).json();
                if (r.linked) {
                    state.innerHTML = 'Got it: you are <b></b>. Reloading&hellip;';
                    state.querySelector('b').textContent = r.name;
                    const reload = () => playing ? setTimeout(reload, 2000) : location.reload();
                    setTimeout(reload, 1500);
                    return;
                }
            } catch (e) { /* offline for a moment: try again */ }
            setTimeout(tick, 20000);
        };
        setTimeout(tick, 5000);
    })();

    // --- boards --------------------------------------------------------
    function board(el, rows) {
        el.innerHTML = rows.length ? '<table class="at-board"><thead><tr>' +
                '<th class="l">#</th><th class="l">Player</th><th>Points</th><th>Right</th><th>Avg time</th>' +
            '</tr></thead><tbody>' + rows.map((r, i) =>
            '<tr class="' + (r.player_id === ME ? 'me' : '') + '">' +
                '<td class="n">' + (i + 1) + '</td>' +
                '<td class="p"><a href="/players/view/' + esc(r.player_id) + '">' + esc(r.name) + '</a> ' + flag(r.country) + '</td>' +
                '<td class="s">' + r.score + '</td>' +
                '<td class="c">' + r.correct + '/' + RULES.questions + '</td>' +
                '<td class="c">' + (r.avg_ms ? (r.avg_ms / 1000).toFixed(1) + ' s' : '-') + '</td>' +
            '</tr>').join('') + '</tbody></table>'
            : '<p class="at-empty">Nobody yet &ndash; be the first.</p>';
    }
    board(document.getElementById('rd-week'), <?= json_encode($week) ?>);
    board(document.getElementById('rd-all'), <?= json_encode($allTime) ?>);

    // --- the game ------------------------------------------------------
    const $ = (id) => document.getElementById(id);
    const view = $('rd-view'), img = $('rd-img'), full = $('rd-full'), bar = $('rd-bar'), hint = $('rd-hint');
    const buttons = [...$('rd-choices').querySelectorAll('.rd-choice')];
    const dots = [...$('rd-dots').children];
    const go = $('rd-go');
    let token = null, total = 0, q = 0, choices = [], t0 = 0, step = 0, open = false, timers = [], raf = 0;
    // after an answer the whole map stays until NEXT (or NEXT_SECONDS), so there is time to look at it
    const NEXT_SECONDS = 10;
    const nextBtn = $('rd-next'), nextS = $('rd-next-s');
    let waiting = null;
    function wait(then, last) {
        let left = NEXT_SECONDS;
        nextBtn.firstChild.textContent = last ? 'SEE RESULT ' : 'NEXT MAP ';
        nextS.textContent = '(' + left + ')';
        nextBtn.hidden = false;
        const go = () => { if (!waiting) return; clearInterval(waiting.iv); waiting = null; nextBtn.hidden = true; then(); };
        waiting = {go, iv: setInterval(() => { if (--left <= 0) go(); else nextS.textContent = '(' + left + ')'; }, 1000)};
    }

    async function post(path, data) {
        const r = await fetch('/radar/' + path, {
            method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': CSRF},
            body: JSON.stringify(data || {}), cache: 'no-store',
        });
        const body = await r.json().catch(() => ({error: 'The server did not answer properly.'}));
        if (!r.ok && !body.error) body.error = 'Something went wrong (' + r.status + ').';
        return body;
    }
    const pieceUrl = (s) => '/radar/piece/' + token + '/' + q + '/' + s + '?' + Date.now();

    // a picture only replaces the shown one once it has loaded (no blank flash)
    function show(el, url) {
        return new Promise((resolve) => {
            const pre = new Image();
            pre.onload = () => { el.src = pre.src; resolve(true); };
            pre.onerror = () => resolve(false);
            pre.src = url;
        });
    }
    // points if answered right now (the server's rule, Radar::points, without the map's factor)
    function worth(ms) {
        const s = Math.min(RULES.steps - 1, Math.floor(ms / (RULES.stepSeconds * 1000)));
        const [from, to] = RULES.points[s];
        const into = Math.min(1, (ms - s * RULES.stepSeconds * 1000) / (RULES.stepSeconds * 1000));
        return Math.round(from - (from - to) * into);
    }
    function tick() {
        if (!open) return;
        const ms = performance.now() - t0;
        bar.style.transform = 'scaleX(' + Math.max(0, 1 - ms / (RULES.questionSeconds * 1000)) + ')';
        hint.innerHTML = 'Right now worth up to <span class="pts">' + worth(ms) + '</span> points';
        raf = requestAnimationFrame(tick);
    }

    async function start() {
        go.disabled = true;
        const r = await post('start');
        go.disabled = false;
        if (r.error) { $('rd-start').querySelector('p').textContent = r.error; return; }
        token = r.token; total = r.questions; q = 0;
        $('rd-score').textContent = '0';
        dots.forEach((d, i) => { d.className = ''; d.hidden = i >= total; });
        $('rd-start').hidden = true; $('rd-end').hidden = true;
        playing = true;
        // on a phone the intro pushes the buttons below the screen: bring the whole game into view
        const box = $('rd').getBoundingClientRect();
        if (box.top < 0 || box.bottom > window.innerHeight) $('rd').scrollIntoView({behavior: 'smooth', block: box.height > window.innerHeight ? 'start' : 'center'});
        next();
    }

    async function next() {
        view.classList.remove('reveal');
        img.removeAttribute('src'); full.removeAttribute('src');
        $('rd-n').textContent = q + 1;
        dots.forEach((d, i) => d.classList.toggle('now', i === q));
        buttons.forEach(b => { b.className = 'rd-choice'; b.disabled = true; b.querySelector('span').innerHTML = '&nbsp;'; });
        hint.textContent = '';
        const r = await post('question', {token, q});
        if (r.error) return fail(r.error);
        choices = r.choices;
        if (!await show(img, pieceUrl(0))) return fail('The map piece did not load.');
        // the server's clock started when it handed out the question: start ours that much later
        t0 = performance.now() - Math.max(0, (r.elapsed || 0) * 1000);
        step = 0; open = true;
        buttons.forEach((b, i) => { b.querySelector('span').textContent = choices[i]; b.disabled = false; });
        for (let s = 1; s < RULES.steps; s++) {
            timers.push(setTimeout(() => { if (open) { step = s; show(img, pieceUrl(s)); } },
                s * RULES.stepSeconds * 1000 - (performance.now() - t0)));
        }
        timers.push(setTimeout(() => answer(null), RULES.questionSeconds * 1000 + 300 - (performance.now() - t0)));
        raf = requestAnimationFrame(tick);
    }

    async function answer(i) {
        if (!open) return;
        open = false;
        timers.forEach(clearTimeout); timers = [];
        cancelAnimationFrame(raf);
        buttons.forEach(b => b.disabled = true);
        const choice = i === null ? null : choices[i];
        const r = await post('answer', {token, q, choice});
        if (r.error) return fail(r.error);
        buttons.forEach((b, k) => {
            if (choices[k] === r.map) b.classList.add('right');
            else if (k === i) b.classList.add('wrong');
            else b.classList.add('dim');
        });
        dots[q].classList.add(r.right ? 'right' : 'wrong');
        $('rd-score').textContent = r.score;
        const who = r.author ? ' <span class="text-zinc-400">by ' + esc(r.author) + '</span>' : '';
        hint.innerHTML = (r.right
            ? '<span class="pts">+' + r.points + '</span>' + (r.streakBonus ? ' <span class="pts">+' + r.streakBonus + ' streak</span>' : '') + ' in ' + (r.ms / 1000).toFixed(1) + ' s'
            : (i === null ? '<span class="no">Time is up</span>' : '<span class="no">Wrong</span>')) +
            '<br><b>' + esc(r.map) + '</b>' + who;
        // the whole plan, so you learn the map
        show(full, pieceUrl('full')).then(ok => ok && view.classList.add('reveal'));
        wait(() => { if (r.last) finish(); else { q++; next(); } }, r.last);
    }

    async function finish() {
        const r = await post('finish', {token});
        playing = false;
        const end = $('rd-end');
        if (r.error) return fail(r.error);
        const avg = r.avg_ms ? (r.avg_ms / 1000).toFixed(1) + ' s' : '-';
        let place = '';
        if (r.player) {
            place = r.best === r.score && r.score > 0 ? '<p><b>New personal best!</b></p>' : '<p class="at-note">Your best: <b>' + r.best + '</b></p>';
            place += '<p class="at-note">This week you are <b>#' + r.rank + '</b>, all time <b>#' + r.allTimeRank + '</b>.</p>';
        } else {
            place = '<p class="at-note">As a guest your score is not on the board &ndash; say your code in a game to get on it.</p>';
        }
        end.innerHTML = '<h2>ROUND OVER</h2><div class="rd-score">' + r.score + '</div>' +
            '<div class="rd-result"><div><b>' + r.correct + '/' + r.questions + '</b><span>right</span></div>' +
            '<div><b>' + avg + '</b><span>avg time</span></div>' +
            '<div><b>' + Math.round(100 * r.correct / Math.max(1, r.questions)) + '%</b><span>known</span></div></div>' +
            place + '<button type="button" class="rd-btn" id="rd-again">PLAY AGAIN</button>';
        end.hidden = false;
        $('rd-again').addEventListener('click', start);
        if (r.week) board($('rd-week'), r.week);
        if (r.allTime) board($('rd-all'), r.allTime);
        hint.textContent = '';
    }

    function fail(text) {
        open = false; playing = false;
        if (waiting) { clearInterval(waiting.iv); waiting = null; nextBtn.hidden = true; }
        timers.forEach(clearTimeout); timers = [];
        cancelAnimationFrame(raf);
        const end = $('rd-end');
        end.innerHTML = '<h2>OOPS</h2><p></p><button type="button" class="rd-btn" id="rd-again">TRY AGAIN</button>';
        end.querySelector('p').textContent = text;
        end.hidden = false;
        $('rd-again').addEventListener('click', start);
    }

    if (go) go.addEventListener('click', start);
    nextBtn.addEventListener('click', () => waiting && waiting.go());
    buttons.forEach((b, i) => b.addEventListener('click', () => answer(i)));
    // keys 1-4 on a keyboard
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        if (waiting && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); waiting.go(); return; }
        if (!open) return;
        const i = '1234'.indexOf(e.key);
        if (i >= 0 && i < buttons.length) { e.preventDefault(); answer(i); }
    });
})();
</script>
<?php $this->end(); ?>
