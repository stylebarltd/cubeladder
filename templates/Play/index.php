<?php
/**
 * Aim trainer: 40-second rounds on a map screenshot, the weekly and the
 * all-time board. Rules and checks: App\Service\AimTrainer (the page only
 * reports the round as events; the server scores it).
 *
 * @var \App\View\AppView $this
 * @var array $week
 * @var array $allTime
 * @var array $maps [{name, url}]
 * @var \App\Model\Entity\Player|null $authPlayer
 */
use App\Service\AimTrainer;

$this->assign('title', 'Aim trainer');
// Link preview: the aim trainer's card (PreviewsController::play), a new
// picture URL when the board or the week's map changes
$ogSite = rtrim((string)\Cake\Core\Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh', '/');
$ogText = sprintf('%d seconds of targets on AssaultCube maps, %d grenades, a weekly and an all-time board. Waiting for a server to fill up? Warm up your aim.',
    AimTrainer::ROUND_SECONDS, AimTrainer::GRENADES);
$this->start('meta'); ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="cubeLadder">
    <meta property="og:title" content="Aim trainer · cubeLadder">
    <meta property="og:description" content="<?= h($ogText) ?>">
    <meta property="og:url" content="<?= h($ogSite . '/play') ?>">
    <meta property="og:image" content="<?= h($ogSite . '/previews/play?v=' . substr(md5((string)json_encode((new AimTrainer())->card())), 0, 8)) ?>">
    <meta property="og:image:width" content="<?= \App\Service\HallOfFameImage::WIDTH ?>">
    <meta property="og:image:height" content="<?= \App\Service\HallOfFameImage::HEIGHT ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="description" content="<?= h($ogText) ?>">
<?php $this->end(); ?>
<style>
.at-wrap { position: relative; border-radius: .75rem; overflow: hidden; border: 1px solid rgb(255 255 255 / .15); background: #0b0b0d; }
.at-wrap canvas { display: block; width: 100%; aspect-ratio: 16 / 9; touch-action: none; cursor: none; }
.at-overlay { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .6rem;
  text-align: center; padding: 1.5rem; background: rgb(0 0 0 / .62); backdrop-filter: blur(2px); }
.at-overlay[hidden] { display: none; }
.at-overlay h2 { font-size: 2rem; font-weight: 800; color: #fff; letter-spacing: .03em; }
.at-overlay p { color: rgb(212 212 216); font-size: .9rem; max-width: 34rem; }
.at-btn { margin-top: .4rem; padding: .7rem 1.6rem; border-radius: .5rem; font-weight: 800; font-size: 1rem; letter-spacing: .05em;
  background: linear-gradient(90deg, #b91c1c, #1d4ed8); color: #fff; border: 1px solid rgb(255 255 255 / .25); cursor: pointer; }
.at-btn:hover { filter: brightness(1.15); }
.at-btn:disabled { opacity: .6; cursor: wait; }
.at-nade { position: absolute; right: .8rem; bottom: .8rem; width: 3.4rem; height: 3.4rem; border-radius: 999px; display: none;
  align-items: center; justify-content: center; background: rgb(0 0 0 / .55); border: 2px solid rgb(255 255 255 / .35); cursor: pointer; }
.at-nade img { width: 1.9rem; height: 1.9rem; }
.at-nade.armed { border-color: #f97316; background: rgb(249 115 22 / .45); }
.at-nade span { position: absolute; top: -.35rem; right: -.35rem; background: #f97316; color: #000; font-weight: 800; font-size: .7rem;
  border-radius: 999px; padding: 0 .35rem; }
.at-touch.at-playing .at-nade { display: flex; }
.at-result { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .5rem; margin: .4rem 0; }
.at-result div { background: rgb(255 255 255 / .07); border-radius: .5rem; padding: .45rem .8rem; }
.at-result b { display: block; font-size: 1.35rem; color: #fff; font-variant-numeric: tabular-nums; }
.at-result span { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: rgb(161 161 170); }
.at-score { font-size: 3.4rem; font-weight: 800; color: #facc15; line-height: 1; font-variant-numeric: tabular-nums; }
.at-points td.p { white-space: normal; max-width: none; }
.at-points td.p small { color: rgb(113 113 122); margin-left: .25rem; }
.at-note { font-size: .8rem; color: rgb(161 161 170); }
.at-note b { color: #fff; }
.at-sound { margin-top: .5rem; font-size: .75rem; color: rgb(161 161 170); background: none; border: 0; cursor: pointer; padding: .2rem 0; }
.at-sound:hover { color: #fff; }
.at-card { border-radius: .75rem; border: 1px solid rgb(255 255 255 / .15); background: rgb(0 0 0 / .6); backdrop-filter: blur(2px); padding: 1rem 1.1rem; }
.at-card h3 { font-weight: 800; color: #fff; display: flex; justify-content: space-between; align-items: baseline; margin-bottom: .5rem; }
.at-card h3 small { font-weight: 400; font-size: .7rem; color: rgb(161 161 170); }
.at-board { width: 100%; font-size: .85rem; border-collapse: collapse; font-variant-numeric: tabular-nums; }
.at-board td { padding: .32rem .25rem; border-top: 1px solid rgb(63 63 70 / .5); color: rgb(212 212 216); white-space: nowrap; }
.at-board td.n { color: rgb(113 113 122); width: 1.8rem; }
.at-board td.p { width: 100%; max-width: 0; overflow: hidden; text-overflow: ellipsis; }
.at-board td.s { text-align: right; font-weight: 800; color: #facc15; }
.at-board th { font-size: .62rem; letter-spacing: .05em; text-transform: uppercase; color: rgb(113 113 122); font-weight: 600;
  text-align: right; padding: 0 .25rem .3rem; white-space: nowrap; }
.at-board th.l { text-align: left; }
.at-board td.c { text-align: right; color: rgb(212 212 216); }
.at-result div.hs { background: rgb(250 204 21 / .15); }
.at-result div.hs b { color: #facc15; }
.at-result div.gib { background: rgb(249 115 22 / .15); }
.at-result div.gib b { color: #fb923c; }
.at-break { font-size: .78rem; color: rgb(212 212 216); }
.at-break b { color: #fff; }
.at-board tr.me td { background: rgb(250 204 21 / .1); }
.at-board a:hover { color: #60a5fa; }
.at-empty { font-size: .8rem; color: rgb(113 113 122); font-style: italic; }
@media (max-width: 640px) {
  .at-overlay h2 { font-size: 1.4rem; }
  .at-score { font-size: 2.4rem; }
  .at-result { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .at-overlay p { font-size: .8rem; }
}
</style>

<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center bg-fixed text-white"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.6), rgba(0,0,0,.4) 40%, rgba(0,0,0,.8)), url('/img/bullet.jpg');">
<div class="mx-auto w-full max-w-7xl px-4 py-6 md:px-16 md:py-8">

    <div class="mb-6 drop-shadow-lg">
        <h1 class="text-3xl md:text-5xl font-extrabold tracking-wide">Aim trainer</h1>
        <p class="mt-2 text-sm text-zinc-300">
            <?= AimTrainer::ROUND_SECONDS ?> seconds, as many targets as you can.
            Head <?= AimTrainer::POINTS_HEADSHOT ?>, body <?= AimTrainer::POINTS_HIT ?>, every <?= AimTrainer::STREAK ?> hits in a row +<?= AimTrainer::POINTS_STREAK ?>.
            <?= AimTrainer::GRENADES ?> grenades: <?= AimTrainer::POINTS_GIB ?> per gib, +<?= AimTrainer::POINTS_MULTI_GIB ?> for every further gib of the same grenade.
            Just for fun &ndash; not part of the ladder.
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- the game needs a mouse: on phones only this note, the boards and the points -->
        <div class="at-card md:hidden">
            <h3>On a bigger screen</h3>
            <p class="at-note">The aim trainer is played with a mouse &ndash; open <b>cubeladder.ovh/play</b> on a desktop or laptop. The boards and the points are below.</p>
        </div>
        <div class="hidden md:block lg:col-span-2">
            <div class="at-wrap" id="at">
                <canvas id="at-canvas" width="1280" height="720" aria-label="Aim trainer"></canvas>
                <div class="at-overlay" id="at-start">
                    <h2>READY?</h2>
                    <p>Targets pop up on the map and run away fast. Click or tap them &ndash; a headshot counts double.
                        Every shot that hits nothing ends your streak.</p>
                    <p><b>Grenades (<?= AimTrainer::GRENADES ?>):</b> right-click or press <b>G</b> &ndash; on a touch screen tap the grenade, then where it should land.
                        It explodes after half a second: wait until targets stand close together.</p>
                    <p class="at-note"><?= $authPlayer
                        ? 'Playing as <b>' . h($authPlayer->name) . '</b> &ndash; your best round goes on the board.'
                        : 'You are playing as a guest: your score counts only for you. The board knows you once you have played on a cubeLadder server from this connection.' ?></p>
                    <button type="button" class="at-btn" id="at-go">START</button>
                </div>
                <button type="button" class="at-nade" id="at-nade" aria-label="Grenade"><img src="/img/weapons/grenade.svg" alt=""><span id="at-nade-n"></span></button>
                <div class="at-overlay" id="at-end" hidden></div>
            </div>
            <button type="button" class="at-sound" id="at-sound"></button>
        </div>

        <div class="space-y-6">
            <div class="at-card">
                <h3>This week <small>resets Monday 00:00 UTC</small></h3>
                <div id="at-week"></div>
            </div>
            <div class="at-card">
                <h3>All time</h3>
                <div id="at-all"></div>
            </div>
            <!-- the points, as the game counts them (AimTrainer::score) -->
            <div class="at-card">
                <h3>Points <small>per hit</small></h3>
                <table class="at-board at-points">
                    <tr><td class="p">Body hit</td><td class="s"><?= AimTrainer::POINTS_HIT ?></td></tr>
                    <tr><td class="p">Headshot</td><td class="s"><?= AimTrainer::POINTS_HEADSHOT ?></td></tr>
                    <tr><td class="p">Every <?= AimTrainer::STREAK ?>th hit in a row <small>streak</small></td><td class="s">+<?= AimTrainer::POINTS_STREAK ?></td></tr>
                    <tr><td class="p">Gib by grenade</td><td class="s"><?= AimTrainer::POINTS_GIB ?></td></tr>
                    <tr><td class="p">Each further gib of the same grenade</td><td class="s">+<?= AimTrainer::POINTS_MULTI_GIB ?></td></tr>
                </table>
                <p class="at-note" style="margin-top:.5rem">A headshot as your <?= AimTrainer::STREAK ?>th hit in a row shows +<?= AimTrainer::POINTS_HEADSHOT + AimTrainer::POINTS_STREAK ?>.
                    A miss, an escaped target or a grenade that gibs nobody ends the streak.</p>
            </div>
        </div>
    </div>
</div>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
(function () {
    const RULES = <?= json_encode([
        'seconds' => AimTrainer::ROUND_SECONDS, 'hit' => AimTrainer::POINTS_HIT, 'headshot' => AimTrainer::POINTS_HEADSHOT,
        'streakBonus' => AimTrainer::POINTS_STREAK, 'streak' => AimTrainer::STREAK,
        'grenades' => AimTrainer::GRENADES, 'gib' => AimTrainer::POINTS_GIB, 'multiGib' => AimTrainer::POINTS_MULTI_GIB,
        'maxAlive' => AimTrainer::MAX_ALIVE,
    ]) ?>;
    const MAPS = <?= json_encode($maps) ?>;
    const ME = <?= json_encode($authPlayer?->id) ?>;
    const CSRF = document.querySelector('meta[name="csrfToken"]')?.content || '';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const flag = (iso) => {
        if (!iso || iso.length !== 2) return '';
        const A = 0x1F1E6 - 65;
        return String.fromCodePoint(iso.toUpperCase().charCodeAt(0) + A, iso.toUpperCase().charCodeAt(1) + A);
    };

    // --- boards --------------------------------------------------------
    function board(el, rows) {
        el.innerHTML = rows.length ? '<table class="at-board"><thead><tr>' +
                '<th class="l">#</th><th class="l">Player</th><th>Points</th><th>Headshots</th><th>Gibs</th><th>Accuracy</th>' +
            '</tr></thead><tbody>' + rows.map((r, i) =>
            '<tr class="' + (r.player_id === ME ? 'me' : '') + '"' + (r.avg_reaction_ms ? ' title="reaction ' + r.avg_reaction_ms + ' ms"' : '') + '>' +
                '<td class="n">' + (i + 1) + '</td>' +
                '<td class="p"><a href="/players/view/' + esc(r.player_id) + '">' + esc(r.name) + '</a> ' + flag(r.country) + '</td>' +
                '<td class="s">' + r.score + '</td>' +
                '<td class="c">' + r.headshots + '</td>' +
                '<td class="c">' + r.gibs + '</td>' +
                '<td class="c">' + r.accuracy + '%</td>' +
            '</tr>').join('') + '</tbody></table>'
            : '<p class="at-empty">Nobody yet &ndash; be the first.</p>';
    }
    board(document.getElementById('at-week'), <?= json_encode($week) ?>);
    board(document.getElementById('at-all'), <?= json_encode($allTime) ?>);

    // --- canvas ----------------------------------------------------------
    const canvas = document.getElementById('at-canvas');
    const ctx = canvas.getContext('2d');
    let W = 1280, H = 720, dpr = 1;
    function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        const r = canvas.getBoundingClientRect();
        W = r.width; H = r.width * 9 / 16;
        canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    window.addEventListener('resize', resize);
    resize();

    const img = (src) => { const i = new Image(); i.src = src; return i; };
    const ICONS = {hs: img('/img/achievements/headshot.svg'), gun: img('/img/weapons/sniper.svg'),
        gib: img('/img/achievements/gibbed.svg'), nade: img('/img/weapons/grenade.svg')};
    let bg = null, bgName = '';
    let mapIndex = Math.floor(Math.random() * Math.max(1, MAPS.length));
    function nextMap() {
        if (!MAPS.length) return;
        const m = MAPS[mapIndex++ % MAPS.length];
        bg = img(m.url); bgName = m.name;
    }
    nextMap();

    // --- sound: "Headshot!" is webroot/sounds/headshot.mp3 - made with TTSMaker
    // (free, public use allowed), trimmed and levelled with ffmpeg; not
    // AssaultCube's own, that needs the AC team's OK. Without the file the
    // browser says it. The boom is made here.
    let soundOn = true;
    try { soundOn = localStorage.getItem('aimSound') !== 'off'; } catch (e) {}
    const soundBtn = document.getElementById('at-sound');
    const showSound = () => { soundBtn.textContent = soundOn ? '🔊 Sound on' : '🔇 Sound off'; };
    soundBtn.addEventListener('click', () => {
        soundOn = !soundOn;
        try { localStorage.setItem('aimSound', soundOn ? 'on' : 'off'); } catch (e) {}
        if (!soundOn && window.speechSynthesis) speechSynthesis.cancel();
        showSound();
    });
    showSound();

    let voice = null;
    function pickVoice() {
        const all = window.speechSynthesis ? speechSynthesis.getVoices() : [];
        const en = all.filter(v => /^en/i.test(v.lang));
        voice = en.find(v => /male|daniel|david|george|guy/i.test(v.name) && !/female/i.test(v.name)) || en[0] || null;
    }
    if (window.speechSynthesis) { pickVoice(); speechSynthesis.addEventListener?.('voiceschanged', pickVoice); }
    let headshotBuf = null;
    async function loadSounds() {
        if (headshotBuf || !audio) return;
        try {
            const res = await fetch('/sounds/headshot.mp3');
            if (res.ok) headshotBuf = await audio.decodeAudioData(await res.arrayBuffer());
        } catch (e) { /* no file: the browser's voice */ }
    }
    function sayHeadshot() {
        if (!soundOn) return;
        if (headshotBuf && audio) {
            const src = audio.createBufferSource();
            src.buffer = headshotBuf;
            src.connect(audio.destination);
            src.start();
            return;
        }
        if (!window.speechSynthesis) return;
        speechSynthesis.cancel(); // two headshots fast: only the newest speaks
        const u = new SpeechSynthesisUtterance('Headshot!');
        if (voice) u.voice = voice;
        u.rate = 1.15; u.pitch = 0.55; u.volume = 1;
        speechSynthesis.speak(u);
    }

    let audio = null, echo = null;
    // noise buffers: white (crack) and brown (blast, rumble - deep, no pitch)
    function noise(seconds, brown) {
        const len = Math.floor(audio.sampleRate * seconds);
        const buf = audio.createBuffer(1, len, audio.sampleRate);
        const d = buf.getChannelData(0);
        let last = 0;
        for (let i = 0; i < len; i++) {
            const w = Math.random() * 2 - 1;
            d[i] = brown ? (last = (last + 0.02 * w) / 1.02) * 3.5 : w;
        }
        const src = audio.createBufferSource();
        src.buffer = buf;
        return src;
    }
    // a short outdoor echo: decaying noise as impulse response
    function echoNode() {
        const len = Math.floor(audio.sampleRate * 2.2);
        const ir = audio.createBuffer(2, len, audio.sampleRate);
        for (let c = 0; c < 2; c++) {
            const d = ir.getChannelData(c);
            for (let i = 0; i < len; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 3);
        }
        const conv = audio.createConvolver();
        conv.buffer = ir;
        const wet = audio.createGain();
        wet.gain.value = 0.35;
        conv.connect(wet).connect(audio.destination);
        return conv;
    }
    // soft clipping: makes the blast dirty instead of clean
    function distortion(amount) {
        const curve = new Float32Array(1024);
        for (let i = 0; i < 1024; i++) {
            const x = i / 512 - 1;
            curve[i] = (1 + amount) * x / (1 + amount * Math.abs(x));
        }
        const ws = audio.createWaveShaper();
        ws.curve = curve;
        return ws;
    }
    // pistol shot for a body hit: crack, a short dirty bang, a small punch
    function pistol() {
        if (!soundOn) return;
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            echo = echo || echoNode();
            const now = audio.currentTime;
            const out = audio.createGain();
            out.gain.value = 0.6;
            out.connect(audio.destination);
            out.connect(echo);
            const env = (node, peak, attack, decay) => {
                const g = audio.createGain();
                g.gain.setValueAtTime(0.0001, now);
                g.gain.exponentialRampToValueAtTime(peak, now + attack);
                g.gain.exponentialRampToValueAtTime(0.0001, now + attack + decay);
                node.connect(g);
                return g;
            };
            const crack = noise(0.08, false);
            const hp = audio.createBiquadFilter(); hp.type = 'highpass'; hp.frequency.value = 1800;
            crack.connect(hp);
            env(hp, 0.8, 0.001, 0.05).connect(out);
            const bang = noise(0.3, false);
            const lp = audio.createBiquadFilter(); lp.type = 'lowpass';
            lp.frequency.setValueAtTime(5000, now);
            lp.frequency.exponentialRampToValueAtTime(500, now + 0.15);
            const drive = distortion(20);
            bang.connect(lp).connect(drive);
            env(drive, 0.9, 0.001, 0.18).connect(out);
            const punch = noise(0.12, true);
            const lp2 = audio.createBiquadFilter(); lp2.type = 'lowpass'; lp2.frequency.value = 250;
            punch.connect(lp2);
            env(lp2, 1.2, 0.002, 0.08).connect(out);
            crack.start(now); bang.start(now); punch.start(now);
        } catch (e) { /* no Web Audio: silent */ }
    }

    function boom() {
        if (!soundOn) return;
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            echo = echo || echoNode();
            const now = audio.currentTime;
            const out = audio.createDynamicsCompressor();
            out.connect(audio.destination);
            out.connect(echo);
            const env = (node, peak, attack, decay) => {
                const g = audio.createGain();
                g.gain.setValueAtTime(0.0001, now);
                g.gain.exponentialRampToValueAtTime(peak, now + attack);
                g.gain.exponentialRampToValueAtTime(0.0001, now + attack + decay);
                node.connect(g);
                return g;
            };
            // 1. crack: very short and bright
            const crack = noise(0.12, false);
            const hp = audio.createBiquadFilter(); hp.type = 'highpass'; hp.frequency.value = 900;
            crack.connect(hp);
            env(hp, 0.9, 0.002, 0.09).connect(out);
            // 2. blast: brown noise, the low-pass closes as it rings out, driven into distortion
            const blast = noise(1.8, true);
            const lp = audio.createBiquadFilter(); lp.type = 'lowpass'; lp.Q.value = 0.7;
            lp.frequency.setValueAtTime(5000, now);
            lp.frequency.exponentialRampToValueAtTime(180, now + 1.4);
            const drive = distortion(8);
            blast.connect(lp).connect(drive);
            env(drive, 1.0, 0.008, 1.5).connect(out);
            // 3. rumble: the deep tail you feel more than hear
            const rumble = noise(2.4, true);
            const lp2 = audio.createBiquadFilter(); lp2.type = 'lowpass'; lp2.frequency.value = 110;
            rumble.connect(lp2);
            env(lp2, 1.4, 0.03, 2.2).connect(out);
            crack.start(now); blast.start(now); rumble.start(now);
        } catch (e) { /* no Web Audio: silent */ }
    }

    // same random numbers as every other browser with this seed
    function mulberry32(a) {
        return function () {
            a |= 0; a = a + 0x6D2B79F5 | 0;
            let t = Math.imul(a ^ a >>> 15, 1 | a);
            t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
            return ((t ^ t >>> 14) >>> 0) / 4294967296;
        };
    }

    // --- round state -------------------------------------------------------
    let state = 'idle', token = null, rnd = Math.random, t0 = 0, nextSpawn = 0;
    let targets = [], effects = [], feed = [];
    let events = '', reactions = [], score = 0, streak = 0, hits = 0, shots = 0, heads = 0;
    let nades = 0, flying = [], gibs = 0, gibPoints = 0, armed = false;
    let mouse = null, shake = 0;
    const MAX_ALIVE = RULES.maxAlive;
    const FUSE = 550;                 // ms from the throw to the bang
    const BLAST = () => H * 0.2;      // blast radius
    const ms = () => performance.now() - t0;
    const progress = () => Math.min(1, ms() / (RULES.seconds * 1000));

    function spawn(now) {
        const p = progress();
        const h = H * (0.24 - 0.09 * p);            // targets shrink over the round
        const x = W * 0.07 + rnd() * W * 0.86;
        const y = H * 0.42 + rnd() * (H * 0.56 - h); // feet on the lower part of the map
        targets.push({x, y, h, team: rnd() < 0.5 ? 'CLA' : 'RVSF', born: now, life: 1700 - 650 * p, dir: rnd() < 0.5 ? -1 : 1});
    }

    // body and head of a target at time t
    function shape(tg, t) {
        const age = t - tg.born;
        const pop = Math.min(1, age / 120);
        const h = tg.h * (0.6 + 0.4 * pop);
        const sway = Math.sin(age / 180) * tg.h * 0.08 * tg.dir;
        const cx = tg.x + sway, top = tg.y + tg.h - h;
        const r = h * 0.15;
        return {cx, head: {x: cx, y: top + r, r}, body: {x: cx - h * 0.21, y: top + 2 * r + h * 0.02, w: h * 0.42, h: h - 2 * r - h * 0.02}};
    }

    // a grenade flies from the bottom to (px, py) and explodes after FUSE
    function throwNade(px, py) {
        if (state !== 'play' || nades <= 0) return;
        nades--;
        armed = false;
        flying.push({x0: W / 2, y0: H, x: px, y: py, t: ms()});
        updateNadeButton();
    }

    function explode(n, now) {
        const R = BLAST();
        const gibbed = [];
        targets = targets.filter(tg => {
            const s = shape(tg, now);
            const cy = s.body.y + s.body.h / 2;
            if (Math.hypot(s.cx - n.x, cy - n.y) <= R + s.body.w / 2) { gibbed.push(tg); return false; }
            return true;
        });
        events += 'n' + 'g'.repeat(gibbed.length);
        let pts = 0, streakBonus = false;
        gibbed.forEach((tg, i) => {
            let p = RULES.gib + (i > 0 ? RULES.multiGib : 0);
            gibPoints += p;
            streak++;
            if (streak % RULES.streak === 0) { p += RULES.streakBonus; streakBonus = true; }
            pts += p;
            feed.unshift({t: now, gib: true, team: tg.team});
        });
        feed.length = Math.min(feed.length, 5);
        gibs += gibbed.length;
        score += pts;
        shake = 10;
        boom();
        const label = gibbed.length >= 3 ? 'MULTI GIB' : (gibbed.length === 2 ? 'DOUBLE GIB' : (gibbed.length ? 'GIBBED' : ''));
        effects.push({x: n.x, y: n.y, t: now, boom: true, r: R});
        if (pts) effects.push({x: n.x, y: n.y - R * 0.3, t: now, text: '+' + pts + ' ' + label + (streakBonus ? ' + STREAK' : ''), gib: true});
    }

    const nadeBtn = document.getElementById('at-nade');
    function updateNadeButton() {
        document.getElementById('at-nade-n').textContent = nades;
        nadeBtn.classList.toggle('armed', armed);
    }
    nadeBtn.addEventListener('pointerdown', (e) => { e.stopPropagation(); if (state === 'play' && nades > 0) { armed = !armed; updateNadeButton(); } });

    function shoot(px, py) {
        if (state !== 'play') return;
        const now = ms();
        shots++;
        // the newest target on top is hit first
        for (let i = targets.length - 1; i >= 0; i--) {
            const s = shape(targets[i], now);
            const dh = Math.hypot(px - s.head.x, py - s.head.y) <= s.head.r * 1.1;
            const db = px >= s.body.x && px <= s.body.x + s.body.w && py >= s.body.y && py <= s.body.y + s.body.h;
            if (!dh && !db) continue;
            const tg = targets.splice(i, 1)[0];
            hits++;
            if (dh) heads++;
            events += dh ? 's' : 'h';
            reactions.push(Math.round(now - tg.born));
            let pts = dh ? RULES.headshot : RULES.hit;
            streak++;
            if (streak % RULES.streak === 0) pts += RULES.streakBonus;
            score += pts;
            if (dh) { shake = 6; sayHeadshot(); } else pistol();
            // "+30 HEADSHOT + STREAK": the bonus is named, so a 30 is not taken for a special headshot
            effects.push({x: px, y: py, t: now, text: '+' + pts + (dh ? ' HEADSHOT' : '') + (streak % RULES.streak === 0 ? (dh ? ' + STREAK' : ' STREAK') : ''), hs: dh});
            feed.unshift({t: now, hs: dh, team: tg.team, streak});
            feed.length = Math.min(feed.length, 5);
            return;
        }
        events += 'm';
        streak = 0;
        effects.push({x: px, y: py, t: now, miss: true});
    }

    const pos = (e) => {
        const r = canvas.getBoundingClientRect();
        return [(e.clientX - r.left) * W / r.width, (e.clientY - r.top) * H / r.height];
    };
    canvas.addEventListener('pointermove', (e) => { if (e.pointerType === 'mouse') mouse = pos(e); });
    canvas.addEventListener('pointerleave', () => { mouse = null; });
    canvas.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        if (e.pointerType !== 'mouse') document.getElementById('at').classList.add('at-touch');
        if (e.button === 2 || armed) throwNade(...pos(e)); else shoot(...pos(e));
    });
    canvas.addEventListener('contextmenu', (e) => e.preventDefault());
    document.addEventListener('keydown', (e) => {
        if ((e.key === 'g' || e.key === 'G') && state === 'play' && mouse) throwNade(...mouse);
    });

    // --- drawing -----------------------------------------------------------
    function drawBg() {
        if (bg && bg.complete && bg.naturalWidth) {
            const s = Math.max(W / bg.naturalWidth, H / bg.naturalHeight);
            const w = bg.naturalWidth * s, h = bg.naturalHeight * s;
            ctx.drawImage(bg, (W - w) / 2, (H - h) / 2, w, h);
        } else {
            ctx.fillStyle = '#18181b'; ctx.fillRect(0, 0, W, H);
        }
        ctx.fillStyle = 'rgba(0,0,0,.35)'; ctx.fillRect(0, 0, W, H);
    }

    function drawTarget(tg, t) {
        const s = shape(tg, t);
        const left = 1 - (t - tg.born) / tg.life;
        const main = tg.team === 'CLA' ? '#dc2626' : '#2563eb', dark = tg.team === 'CLA' ? '#7f1d1d' : '#172554';
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,.6)'; ctx.shadowBlur = 8;
        ctx.fillStyle = main; ctx.strokeStyle = dark; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.roundRect(s.body.x, s.body.y, s.body.w, s.body.h, s.body.w * 0.25); ctx.fill(); ctx.stroke();
        ctx.beginPath(); ctx.arc(s.head.x, s.head.y, s.head.r, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
        ctx.restore();
        // eyes slit, so the head reads as a head
        ctx.fillStyle = 'rgba(0,0,0,.55)';
        ctx.fillRect(s.head.x - s.head.r * 0.6, s.head.y - s.head.r * 0.15, s.head.r * 1.2, s.head.r * 0.25);
        // time left
        ctx.fillStyle = 'rgba(0,0,0,.5)'; ctx.fillRect(s.body.x, s.body.y + s.body.h + 4, s.body.w, 4);
        ctx.fillStyle = left > 0.35 ? '#fde047' : '#f87171'; ctx.fillRect(s.body.x, s.body.y + s.body.h + 4, s.body.w * Math.max(0, left), 4);
    }

    function drawHud(t) {
        const left = Math.max(0, RULES.seconds - t / 1000);
        ctx.font = '800 ' + Math.round(H * 0.07) + 'px ui-monospace, monospace';
        ctx.fillStyle = left <= 10 ? '#f87171' : '#fff';
        ctx.textAlign = 'left'; ctx.textBaseline = 'top';
        ctx.fillText(left.toFixed(1), W * 0.025, H * 0.03);
        ctx.font = '700 ' + Math.round(H * 0.035) + 'px ui-monospace, monospace';
        ctx.fillStyle = '#facc15';
        ctx.fillText(score + ' pts', W * 0.025, H * 0.115);
        ctx.fillStyle = '#d4d4d8';
        const acc = shots ? Math.round(100 * hits / shots) : 100;
        ctx.fillText(acc + '% · ' + heads + ' hs' + (gibs ? ' · ' + gibs + (gibs === 1 ? ' gib' : ' gibs') : '') + (streak >= RULES.streak ? '  🔥' + streak : ''), W * 0.025, H * 0.165);
        // grenades left, bottom right (on touch screens the button shows them)
        const ns = H * 0.06;
        for (let i = 0; i < RULES.grenades; i++) {
            ctx.globalAlpha = i < nades ? 1 : 0.2;
            if (ICONS.nade.complete) ctx.drawImage(ICONS.nade, W * 0.975 - (i + 1) * ns * 1.1, H * 0.92 - ns, ns, ns);
        }
        ctx.globalAlpha = 1;
        // kill feed, top right
        ctx.textAlign = 'right';
        ctx.font = '600 ' + Math.round(H * 0.03) + 'px ui-monospace, monospace';
        feed.forEach((f, i) => {
            const a = Math.max(0, 1 - (t - f.t) / 4000);
            if (!a) return;
            ctx.globalAlpha = a;
            const y = H * 0.04 + i * H * 0.05, size = H * 0.035;
            ctx.fillStyle = f.team === 'CLA' ? '#f87171' : '#60a5fa';
            const victim = f.team + ' bot';
            ctx.fillText(victim, W * 0.975, y);
            const vw = ctx.measureText(victim).width;
            const icon = f.gib ? ICONS.gib : (f.hs ? ICONS.hs : ICONS.gun);
            if (icon.complete) ctx.drawImage(icon, W * 0.975 - vw - size * 1.25, y - size * 0.05, size, size);
            ctx.fillStyle = '#fff';
            ctx.fillText('you', W * 0.975 - vw - size * 1.5, y);
            ctx.globalAlpha = 1;
        });
        ctx.textAlign = 'left';
        ctx.font = '600 ' + Math.round(H * 0.025) + 'px ui-monospace, monospace';
        ctx.fillStyle = 'rgba(255,255,255,.55)';
        ctx.fillText(bgName, W * 0.025, H * 0.94);
    }

    function drawEffects(t) {
        effects = effects.filter(e => t - e.t < 700);
        // grenades in the air: an arc to where they land, then the bang
        flying = flying.filter(n => {
            const k = (t - n.t) / FUSE;
            if (k >= 1) { explode(n, t); return false; }
            const x = n.x0 + (n.x - n.x0) * k, y = n.y0 + (n.y - n.y0) * k - Math.sin(k * Math.PI) * H * 0.25;
            const ns = H * 0.05;
            if (ICONS.nade.complete) { ctx.save(); ctx.translate(x, y); ctx.rotate(k * 9); ctx.drawImage(ICONS.nade, -ns / 2, -ns / 2, ns, ns); ctx.restore(); }
            ctx.strokeStyle = 'rgba(249,115,22,.5)'; ctx.setLineDash([4, 4]); ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.arc(n.x, n.y, BLAST(), 0, Math.PI * 2); ctx.stroke(); ctx.setLineDash([]);
            return true;
        });
        effects.forEach(e => {
            const a = 1 - (t - e.t) / 700;
            ctx.globalAlpha = a;
            if (e.boom) {
                const g = ctx.createRadialGradient(e.x, e.y, 0, e.x, e.y, e.r * (0.6 + 0.4 * (1 - a)));
                g.addColorStop(0, 'rgba(255,240,180,' + a + ')');
                g.addColorStop(0.4, 'rgba(249,115,22,' + a * 0.8 + ')');
                g.addColorStop(1, 'rgba(120,30,0,0)');
                ctx.fillStyle = g;
                ctx.beginPath(); ctx.arc(e.x, e.y, e.r * (0.6 + 0.4 * (1 - a)), 0, Math.PI * 2); ctx.fill();
            } else if (e.miss) {
                ctx.strokeStyle = 'rgba(255,255,255,.6)'; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.arc(e.x, e.y, 4 + (1 - a) * 6, 0, Math.PI * 2); ctx.stroke();
            } else {
                // hit marker and the points
                ctx.strokeStyle = e.hs ? '#facc15' : '#fff'; ctx.lineWidth = 2.5;
                if (e.gib) ctx.strokeStyle = 'rgba(0,0,0,0)';
                const s = 7 + (1 - a) * 5, g = 4;
                [[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([dx, dy]) => {
                    ctx.beginPath(); ctx.moveTo(e.x + dx * g, e.y + dy * g); ctx.lineTo(e.x + dx * (g + s), e.y + dy * (g + s)); ctx.stroke();
                });
                ctx.font = '800 ' + Math.round(H * (e.hs || e.gib ? 0.04 : 0.032)) + 'px ui-monospace, monospace';
                ctx.textAlign = 'center';
                ctx.fillStyle = e.gib ? '#fb923c' : (e.hs ? '#facc15' : '#fff');
                ctx.fillText(e.text, e.x, e.y - 18 - (1 - a) * 30);
                ctx.textAlign = 'left';
            }
            ctx.globalAlpha = 1;
        });
    }

    function drawCrosshair() {
        if (!mouse) return;
        const [x, y] = mouse;
        ctx.strokeStyle = armed ? '#f97316' : '#4ade80'; ctx.lineWidth = 2;
        [[0, -1], [0, 1], [-1, 0], [1, 0]].forEach(([dx, dy]) => {
            ctx.beginPath(); ctx.moveTo(x + dx * 5, y + dy * 5); ctx.lineTo(x + dx * 14, y + dy * 14); ctx.stroke();
        });
    }

    function frame() {
        const t = ms();
        ctx.save();
        if (shake > 0) { ctx.translate((Math.random() - .5) * shake, (Math.random() - .5) * shake); shake *= 0.85; if (shake < .5) shake = 0; }
        drawBg();
        if (state === 'play') {
            // spawn on the seed's schedule: faster towards the end
            while (t >= nextSpawn && t < RULES.seconds * 1000) {
                if (targets.length < MAX_ALIVE) spawn(nextSpawn);
                nextSpawn += 800 - 400 * Math.min(1, nextSpawn / (RULES.seconds * 1000));
            }
            targets = targets.filter(tg => {
                if (t - tg.born < tg.life || t >= RULES.seconds * 1000) return true;
                events += 'e';
                streak = 0;
                return false;
            });
            targets.forEach(tg => drawTarget(tg, t));
            drawEffects(t);
            drawHud(t);
            if (t >= RULES.seconds * 1000 && !flying.length) end();
        }
        ctx.restore();
        if (state === 'play' || state === 'idle') drawCrosshair();
        requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);

    // --- start / end ---------------------------------------------------------
    const startBox = document.getElementById('at-start');
    const endBox = document.getElementById('at-end');
    const post = (url, body) => fetch(url, {
        method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': CSRF},
        body: JSON.stringify(body || {}),
    });

    async function begin(btn) {
        btn.disabled = true;
        try { audio = audio || new (window.AudioContext || window.webkitAudioContext)(); audio.resume(); loadSounds(); } catch (e) {}
        try {
            const res = await post('/play/start');
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            token = data.token;
            rnd = mulberry32(data.seed);
        } catch (e) {
            btn.disabled = false;
            btn.textContent = 'TRY AGAIN';
            return;
        }
        nextMap();
        targets = []; effects = []; feed = [];
        events = ''; reactions = []; score = streak = hits = shots = heads = 0;
        nades = RULES.grenades; flying = []; gibs = gibPoints = 0; armed = false;
        updateNadeButton();
        startBox.hidden = true; endBox.hidden = true;
        t0 = performance.now(); nextSpawn = 400;
        document.getElementById('at').classList.add('at-playing');
        state = 'play';
    }

    // where the points came from: "19 headshots × 20 = 380 · 8 body hits × 10 = 80 · streak bonus +110"
    function breakdown() {
        const body = hits - heads;
        const bonus = score - heads * RULES.headshot - body * RULES.hit - gibPoints;
        const parts = [];
        if (heads) parts.push('<b>' + heads + '</b> headshot' + (heads === 1 ? '' : 's') + ' × ' + RULES.headshot + ' = ' + heads * RULES.headshot);
        if (body) parts.push('<b>' + body + '</b> body hit' + (body === 1 ? '' : 's') + ' × ' + RULES.hit + ' = ' + body * RULES.hit);
        if (gibs) parts.push('<b>' + gibs + '</b> gib' + (gibs === 1 ? '' : 's') + ' = ' + gibPoints);
        if (bonus) parts.push('streak bonus <b>+' + bonus + '</b>');
        return parts.length ? '<p class="at-break">' + parts.join(' · ') + '</p>' : '';
    }

    async function end() {
        state = 'over';
        document.getElementById('at').classList.remove('at-playing');
        targets = [];
        const acc = shots ? Math.round(100 * hits / shots) : 0;
        const avg = reactions.length ? Math.round(reactions.reduce((a, b) => a + b, 0) / reactions.length) : null;
        endBox.innerHTML = '<h2>TIME!</h2><div class="at-score">' + score + '</div>' +
            '<div class="at-result">' +
                '<div class="hs"><b>' + heads + '</b><span>headshots</span></div>' +
                '<div class="gib"><b>' + gibs + '</b><span>gibs</span></div>' +
                '<div><b>' + hits + '</b><span>shots hit</span></div>' +
                '<div><b>' + acc + '%</b><span>accuracy</span></div>' +
                '<div><b>' + (avg ?? '–') + (avg ? ' ms' : '') + '</b><span>reaction</span></div>' +
            '</div>' + breakdown() + '<p class="at-note js-at-rank">saving&hellip;</p>' +
            '<button type="button" class="at-btn" id="at-again">PLAY AGAIN</button>';
        endBox.hidden = false;
        const again = document.getElementById('at-again');
        again.addEventListener('click', () => begin(again));
        const note = endBox.querySelector('.js-at-rank');
        try {
            const res = await post('/play/finish', {token, events, reactions});
            const data = await res.json();
            if (!res.ok) { note.textContent = data.error || 'Not saved.'; return; }
            if (data.player) {
                note.innerHTML = 'This week you are <b>#' + data.rank + '</b> · all time <b>#' + data.allTimeRank + '</b> · your best ever: <b>' + data.best + '</b>'
                    + (data.allTimeRank <= 10 ? ' · <a href="/players/hall_of_fame/aim-trainer">Hall of Fame</a>' : '');
            } else {
                note.textContent = 'Guest round: not on the board. Play on a cubeLadder server once to get on it.';
            }
            board(document.getElementById('at-week'), data.week || []);
        } catch (e) {
            note.textContent = 'Could not save this round.';
        }
    }

    const go = document.getElementById('at-go');
    go.addEventListener('click', () => begin(go));
    document.addEventListener('keydown', (e) => {
        if ((e.key === 'Enter' || e.key === ' ') && state !== 'play' && document.activeElement?.tagName !== 'INPUT') {
            e.preventDefault();
            const btn = state === 'idle' ? go : document.getElementById('at-again');
            if (btn && !btn.disabled) begin(btn);
        }
    });
})();
</script>
<?php $this->end(); ?>
