<?php
/**
 * /run: AssaultCube in the browser, first person on the real maps with the
 * game's own movement (webroot/map3d: physics.js ports physics.cpp). Full
 * window, no site layout. Three tabs (FlagRun::TABS, each with its maps):
 * - Flag run: to the enemy flag and back, best time (App\Service\FlagRun)
 * - Run & Gun: the map's pickup course in order and players to shoot, points
 *   (App\Service\Aim3D)
 * - QR hunt: cl_angkorwat, cl_lost and cl_cube free to roam with the rifle (its recoil lifts you
 *   onto higher edges), no clock and no board; the codes level you up
 *   (App\Service\QrHunt).
 *
 * @var \App\View\AppView $this
 * @var array $tabs tab => [map => title]
 * @var string|null $linkCode
 * @var int $qrLevel
 * @var array $qrUnlocks QrHunt::UNLOCKS
 * @var list<array> $qrHunters QrHunt::hunters(): players with finds, most first
 * @var int $qrTotal codes hidden now
 * @var \App\Model\Entity\Player|null $authPlayer
 */
use App\Service\Aim3D;
use App\Service\FlagRun;
use App\Service\QrHunt;

$csrf = (string)$this->request->getAttribute('csrfToken');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrfToken" content="<?= h($csrf) ?>">
<title>Flag run, Run &amp; Gun, QR hunt · cubeLadder</title>
<meta name="description" content="AssaultCube in your browser: flag runs, Run & Gun and the QR hunt on the real maps, with the game's own movement and rifle. Boards per map.">
<meta property="og:title" content="Flag run, Run &amp; Gun, QR hunt · cubeLadder">
<meta property="og:description" content="AssaultCube in your browser: flag runs, Run & Gun and the QR hunt on the real maps. Boards per map.">
<style>
  html, body { margin: 0; height: 100%; overflow: hidden; background: #0d0f14; color: #e6e9ef; font: 15px/1.45 system-ui, sans-serif; }
  canvas#view { display: block; width: 100vw; height: 100vh; }
  .ov { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(8,10,14,.55); }
  .ov[hidden], [hidden] { display: none !important; }
  .card { background: rgba(22,25,33,.94); border: 1px solid #2c3242; border-radius: 12px; padding: 22px 26px; width: min(900px, calc(100vw - 32px)); max-height: calc(100vh - 32px); overflow: auto; box-sizing: border-box; }
  h1 { margin: 0 0 4px; font-size: 28px; letter-spacing: .02em; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
  a.home { flex: none; padding: 7px 12px; border-radius: 6px; border: 1px solid #2c3242; background: #1b1f2a; color: #f5c242; text-decoration: none; font: 700 14px system-ui, sans-serif; white-space: nowrap; }
  a.home:hover { border-color: #f5c242; background: #2a2618; }
  h1 small { font-size: 14px; color: #8a93a6; font-weight: 400; margin-left: 8px; }
  .muted { color: #8a93a6; font-size: 13px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 14px; }
  @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } }
  .maps { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
  .maps button { text-align: left; padding: 8px 10px; border-radius: 6px; border: 1px solid #2c3242; background: #1b1f2a; color: inherit; cursor: pointer; font: inherit; font-size: 14px; }
  .maps button.on { border-color: #f5c242; background: #2a2618; }
  .maps button small { display: block; color: #8a93a6; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .go { display: block; width: 100%; margin-top: 14px; padding: 16px; font-size: 20px; font-weight: 800; letter-spacing: .06em; border: 0; border-radius: 8px; background: #f5c242; color: #12151c; cursor: pointer; }
  .go:disabled { opacity: .55; cursor: wait; }
  .go:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }
  .sec { background: #2a3040; color: #e6e9ef; font-size: 14px; padding: 9px 14px; border: 0; border-radius: 6px; cursor: pointer; font-weight: 600; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; font-variant-numeric: tabular-nums; }
  th { font-size: 11px; letter-spacing: .05em; text-transform: uppercase; color: #6b7385; font-weight: 600; text-align: right; padding: 0 4px 4px; }
  th.l { text-align: left; }
  td { padding: 4px 4px; border-top: 1px solid #262c3a; }
  td.n { color: #6b7385; width: 1.6em; } td.t { text-align: right; font-weight: 800; color: #f5c242; } td.c { text-align: right; color: #c9cfdb; }
  tr.me td { background: rgba(245,194,66,.1); }
  td a { color: inherit; text-decoration: none; } td a:hover { color: #7fb0ff; }
  h3 { margin: 0 0 6px; font-size: 15px; display: flex; justify-content: space-between; gap: 8px; } h3 small { color: #8a93a6; font-weight: 400; font-size: 11px; }
  .keys { margin: 10px 0 0; font-size: 13px; color: #c9cfdb; }
  .keys kbd { display: inline-block; min-width: 1.4em; padding: 1px 5px; border: 1px solid #3a4152; border-bottom-width: 2px; border-radius: 4px; font: 600 12px ui-monospace, monospace; text-align: center; background: #1b1f2a; }
  .code { font: 800 26px ui-monospace, monospace; color: #f5c242; letter-spacing: .08em; user-select: all; }
  #hud { position: fixed; inset: 0; pointer-events: none; }
  #timer { position: absolute; top: 14px; left: 50%; transform: translateX(-50%); font: 800 34px ui-monospace, monospace; color: #fff; text-shadow: 0 2px 6px #000; font-variant-numeric: tabular-nums; }
  #phase { position: absolute; top: 60px; left: 50%; transform: translateX(-50%); font-size: 17px; font-weight: 700; text-shadow: 0 2px 4px #000; white-space: nowrap; }
  #phase.cla { color: #ff6b5e; } #phase.rvsf { color: #6aa6ff; } #phase.item { color: #f5c242; }
  #ammo { position: absolute; bottom: 18px; right: 24px; font: 800 30px ui-monospace, monospace; color: #fff; text-shadow: 0 2px 6px #000; }
  #crosshair { position: absolute; left: 50%; top: 50%; width: 32px; height: 32px; margin: -16px 0 0 -16px; }
  #count { position: absolute; top: 38%; left: 50%; transform: translate(-50%, -50%); font: 900 120px system-ui, sans-serif; color: #fff; text-shadow: 0 4px 16px #000; }
  .big { font: 900 54px ui-monospace, monospace; color: #f5c242; margin: 6px 0; font-variant-numeric: tabular-nums; }
  .err { color: #ff8a7a; font-weight: 600; }
  .row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
  .row .go { margin-top: 0; flex: 1; }
  .modes { display: flex; gap: 6px; margin: 12px 0 10px; }
  .modes button { flex: 1; padding: 9px; border-radius: 6px; border: 1px solid #2c3242; background: #1b1f2a; color: inherit; font: 700 15px system-ui, sans-serif; cursor: pointer; }
  .modes button.on { border-color: #f5c242; background: #2a2618; color: #f5c242; }
  #course { margin-top: 8px; font-size: 13px; color: #c9cfdb; min-height: 1.2em; }
  .unlocks { list-style: none; padding: 0; margin: 8px 0 0; font-size: 13px; }
  .unlocks li { display: grid; grid-template-columns: 2.2em 1fr; gap: 6px; padding: 5px 0; border-top: 1px solid #262c3a; }
  .unlocks li b { color: #e6e9ef; } .unlocks li .pt { font: 800 13px ui-monospace, monospace; color: #f5c242; text-align: right; }
  .unlocks li.got .pt::after { content: " ✓"; color: #4ade80; }
  .unlocks li.soon { opacity: .5; }
  .breakdown { font-size: 14px; color: #c9cfdb; } .breakdown b { color: #fff; }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pako/2.1.0/pako.min.js"></script>
<script type="importmap">
{ "imports": {
  "three": "https://unpkg.com/three@0.170.0/build/three.module.js",
  "three/addons/": "https://unpkg.com/three@0.170.0/examples/jsm/"
} }
</script>
</head>
<body>
<canvas id="view"></canvas>

<div id="hud" hidden>
  <div id="timer">0:00.00</div>
  <div id="phase"></div>
  <div id="ammo" hidden></div>
  <img id="crosshair" alt="">
  <div id="count" hidden></div>
</div>

<!-- menu: tab, map, keys, boards / QR levels -->
<div class="ov" id="menu"><div class="card">
  <div class="head"><h1>AssaultCube in your browser <small>cubeLadder</small></h1><a class="home" href="/">&#8592; Home</a></div>
  <div class="muted">The game's own maps and movement &ndash; jumps, crouch jumps and strafe jumps work as in AC. Desktop with a mouse.
    Just for fun &ndash; not part of the ladder.</div>
  <div class="modes" id="modes">
    <button type="button" data-mode="flag">Flag run</button>
    <button type="button" data-mode="rungun">Run &amp; Gun</button>
    <button type="button" data-mode="qr">QR hunt</button>
  </div>
  <div class="muted" id="modetext"></div>
  <div class="grid">
    <div>
      <div class="maps" id="maps"></div>
      <div id="course"></div>
      <button class="go" id="play" type="button" disabled>LOADING&hellip;</button>
      <p class="keys"><kbd>W</kbd><kbd>A</kbd><kbd>S</kbd><kbd>D</kbd> move &middot; mouse look &middot; <kbd>Space</kbd> jump &middot;
        <kbd>Shift</kbd> crouch &middot; jump, then <kbd>Shift</kbd> = crouch jump &middot; <kbd>Esc</kbd> pause
        <span id="riflekeys" hidden> &middot; left mouse button: shoot (hold for automatic) &middot; <kbd>R</kbd> reload</span></p>
      <p class="muted"><?= $authPlayer
        ? 'Playing as <b>' . h($authPlayer->name) . '</b> &ndash; your best per map goes on the board.'
        : 'Playing as a guest: your result counts only for you. To get on the board, say <span class="code" style="font-size:16px">' . h((string)$linkCode) . '</span> in a game on a cubeLadder server &ndash; this page recognises you by itself.' ?></p>
    </div>
    <div id="boards">
      <h3><span>This week</span> <small id="mapname"></small></h3>
      <div id="week"></div>
      <h3 style="margin-top:12px">All time</h3>
      <div id="all"></div>
    </div>
    <div id="qrpanel" hidden>
      <h3><span>The QR hunt</span> <small>your level: <?= (int)$qrLevel ?><?= QrHunt::levelName((int)$qrLevel) ? ' &middot; ' . h((string)QrHunt::levelName((int)$qrLevel)) : '' ?></small></h3>
      <p class="muted" style="margin:0">QR codes are hidden in our maps &ndash; three in cl_angkorwat, three on cl_lost, four inside cl_cube: 10 in total.
        Find one, scan it with your phone straight off the screen: it opens cubeLadder and you level up &ndash; every code is a point.
        Points unlock things on cubeLadder (they stay yours):</p>
      <ul class="unlocks">
        <?php foreach ($qrUnlocks as [$points, $title, $what, $built]): ?>
        <li class="<?= $qrLevel >= $points ? 'got' : '' ?> <?= $built ? '' : 'soon' ?>"><span class="pt"><?= (int)$points ?></span>
          <span><b><?= h($title) ?></b><?= $built ? '' : ' <span class="muted">(coming)</span>' ?> &ndash; <?= h($what) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <p class="muted">Hint: the rifle's recoil pushes you &ndash; shoot at the ground while you jump to get onto higher edges.
        <?= $authPlayer ? '' : 'Scanning works as a guest too: the code page tells you how to link your name.' ?></p>
      <h3 style="margin-top:14px"><span>QR hunters</span> <small><?= count($qrHunters) ?> <?= count($qrHunters) === 1 ? 'player' : 'players' ?> &middot; <?= (int)$qrTotal ?> codes hidden</small></h3>
      <?php if ($qrHunters): ?>
      <table><thead><tr><th class="l">#</th><th class="l">Player</th><th class="l">Level</th><th>Codes</th></tr></thead><tbody>
        <?php foreach ($qrHunters as $i => $h): ?>
        <tr class="<?= $authPlayer && $h['player_id'] === (string)$authPlayer->id ? 'me' : '' ?>">
          <td class="n"><?= $i + 1 ?></td>
          <td><a href="<?= h($this->Url->build(['controller' => 'Players', 'action' => 'view', $h['player_id']])) ?>" style="color:inherit"><?= h($h['name']) ?></a> <?= $this->Layout->flag($h['country']) ?></td>
          <td class="muted"><?= h((string)QrHunt::levelName($h['level'])) ?></td>
          <td class="t"><?= (int)$h['level'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table>
      <?php else: ?>
      <p class="muted">Nobody has found a code yet. Be the first.</p>
      <?php endif; ?>
    </div>
  </div>
</div></div>

<!-- pause -->
<div class="ov" id="pause" hidden><div class="card" style="width:min(460px,calc(100vw - 32px));text-align:center">
  <h1>Paused</h1>
  <div class="muted" id="pausetext">The clock keeps running.</div>
  <button class="go" id="resume" type="button">&#9654; Continue</button>
  <div class="muted" style="margin-top:6px">or click anywhere &middot; Enter</div>
  <div class="row" style="justify-content:center"><button class="sec" id="giveup" type="button">Give up</button></div>
  <div class="muted err" id="pausemsg"></div>
</div></div>

<!-- result -->
<div class="ov" id="result" hidden><div class="card" style="width:min(680px,calc(100vw - 32px))">
  <div id="resbody"></div>
  <div class="row"><button class="go" id="again" type="button">RUN AGAIN</button><button class="sec" id="tomenu" type="button">Other map</button><a class="home" href="/" style="margin-left:auto;align-self:center">&#8592; Home</a></div>
</div></div>

<script type="module">
import { loadMap, ENT, TEAM } from "/map3d/cgz.js";
import { Viewer } from "/map3d/viewer.js";
import { Walker } from "/map3d/walk.js";
import { GameKit, crosshairImage } from "/map3d/game.js";
import { buildGrid, astar, snap, cellOf } from "/map3d/path.js";
import { Radar } from "/map3d/radar.js";
import { Physics, Player } from "/map3d/physics.js";
import { Targets, schedule, spawnPoints, raycube, hitTarget, burstSpread, burstPitchvel, pitchStep, ROUND_MS, ALIVE, RESPAWN_MS, RIFLE, POINTS } from "/map3d/aim3d.js";

const ME = <?= json_encode($authPlayer?->id) ?>;
const CSRF = document.querySelector('meta[name="csrfToken"]').content;
const SAMPLE_MS = <?= FlagRun::SAMPLE_MS ?>, COUNTDOWN_MS = <?= FlagRun::COUNTDOWN_MS ?>;
const TABS = <?= json_encode($tabs) ?>;   // tab -> { map: title }
const RUNGUN = <?= json_encode(Aim3D::MODE) ?>;
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const fmt = (ms) => { const s = ms / 1000; return `${Math.floor(s / 60)}:${(s % 60).toFixed(2).padStart(5, "0")}`; };
const flagEmoji = (iso) => iso && iso.length === 2 ? String.fromCodePoint(...[...iso.toUpperCase()].map((c) => c.charCodeAt(0) + 0x1F1E6 - 65)) : "";
const post = async (path, data) => (await fetch(path, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-Token": CSRF }, body: JSON.stringify(data) })).json();

const viewer = new Viewer($("view"));
viewer.setGameView(true);
viewer.controls.enabled = false; // the map stands still behind the menu (a turning background is hard on the eyes)
const walker = new Walker(viewer);
const kit = new GameKit(viewer);
crosshairImage().then((src) => { $("crosshair").src = src; });
const radar = new Radar($("hud"));
const targets = new Targets(viewer.scene);

let mapName = null, map = null, flags = {}, run = null, loading = null, startAt = null;
let mode = "flag", courses = {}, spawns = [], order = [];
const coursesReady = fetch("/map3d/maps/flagrun.json", { cache: "no-store" }).then((r) => r.json()).then((j) => { courses = j; }).catch(() => {});
const course = () => courses[mapName]?.parkour?.items ?? [];
const ITEM_NAME = { clips: "pistol clips", ammo: "ammo box", grenade: "grenades", health: "health", helmet: "helmet", armour: "armour", akimbo: "akimbo" };
const ITEM_SOUND = { clips: "misc/pickup_ammo_clip", ammo: "misc/pickup_ammo_clip", grenade: "misc/pickup_ammo_clip", health: "misc/pickup_health",
  helmet: "misc/pickup_helmet", armour: "misc/pickup_armour", akimbo: "misc/tick9" }; // server.h: S_ITEMAMMO .. S_ITEMAKIMBO
const MODE_TEXT = {
  flag: "Run from your flag to the enemy flag, grab it and bring it home &ndash; as fast as you can. Best time per map.",
  rungun: `${ROUND_MS / 1000} seconds with the assault rifle. Collect the map's pickups in their order &ndash; the same course for everyone &ndash; and shoot the players standing at the spawn points (${ALIVE} at a time, the same order for everyone).
    Body ${POINTS.body}, head ${POINTS.head}, +${POINTS.streak} for every ${POINTS.every} hits in a row, ${POINTS.pickup} per pickup; done with the course before the time is up: +${POINTS.bonusPerS} per second left. Best score per map.`,
  qr: "Free to roam &ndash; no clock, no players to shoot. Take the rifle and explore: three QR codes are hidden in cl_angkorwat's temple, three more on cl_lost, the island (a big map: give it a moment to load), and the last four inside cl_cube, the giant cubeLadder logo (climb the ladder).",
};
const rifleMode = () => mode === "rungun" || mode === "qr";

// ---- boards -------------------------------------------------------------------
function board(el, rows) {
  const score = mode === "rungun";
  const head = score ? '<tr><th class="l">#</th><th class="l">Player</th><th>Accuracy</th><th>Pickups</th><th>Points</th></tr>'
    : '<tr><th class="l">#</th><th class="l">Player</th><th>Time</th></tr>';
  el.innerHTML = rows.length ? `<table><thead>${head}</thead><tbody>` + rows.map((r, i) => `<tr class="${r.player_id === ME ? "me" : ""}"><td class="n">${i + 1}</td>
    <td><a href="/players/view/${esc(r.player_id)}" target="_blank">${esc(r.name)}</a> ${flagEmoji(r.country)}</td>${score
      ? `<td class="c">${r.accuracy}%</td><td class="c">${r.pickups}</td><td class="t">${r.score}</td>` : `<td class="t">${fmt(r.time_ms)}</td>`}</tr>`).join("") + "</tbody></table>"
    : '<p class="muted"><i>Nobody yet &ndash; be the first.</i></p>';
}
async function loadBoards(name) {
  if (mode === "qr") return;
  $("mapname").textContent = `${name} · ${mode === "rungun" ? "Run & Gun" : "Flag run"}`;
  board($("week"), []); board($("all"), []);
  try {
    const r = await (await fetch(`/run/board?map=${encodeURIComponent(name)}&mode=${mode}`, { cache: "no-store" })).json();
    if (r.week) { board($("week"), r.week); board($("all"), r.allTime); }
  } catch { /* offline: boards stay as they are */ }
}

function showCourse() {
  const items = course();
  $("course").innerHTML = mode === "rungun" && items.length
    ? `<b>Course:</b> ${items.map((it, k) => `${k + 1}&nbsp;${esc(ITEM_NAME[it.type] ?? it.type)}`).join(" &rarr; ")}` : "";
}

// ---- tabs and maps -------------------------------------------------------------------
function selectMode(m) {
  if (!TABS[m]) m = "flag";
  mode = m;
  try { localStorage.setItem("flagrun.mode", m); } catch {}
  document.querySelectorAll("#modes button").forEach((b) => b.classList.toggle("on", b.dataset.mode === m));
  $("modetext").innerHTML = MODE_TEXT[m];
  $("riflekeys").hidden = !rifleMode();
  $("boards").hidden = m === "qr"; $("qrpanel").hidden = m !== "qr";
  $("maps").innerHTML = Object.entries(TABS[m] ?? {}).map(([name, title]) => `<button type="button" data-map="${esc(name)}">${esc(name)}<small>${esc(title)}</small></button>`).join("");
  document.querySelectorAll("#maps button").forEach((b) => b.addEventListener("click", () => selectMap(b.dataset.map)));
  const names = Object.keys(TABS[m] ?? {});
  let last = null;
  try { last = localStorage.getItem(`flagrun.map.${m}`); } catch {}
  const pick = names.includes(mapName) ? mapName : names.includes(last) ? last : names[0];
  mapName === pick ? (markMap(pick), loadBoards(pick), showCourse(), placeForMode()) : selectMap(pick);
}
document.querySelectorAll("#modes button").forEach((b) => b.addEventListener("click", () => selectMode(b.dataset.mode)));

function markMap(name) { document.querySelectorAll("#maps button").forEach((b) => b.classList.toggle("on", b.dataset.map === name)); }
function placeForMode() { if (map) kit.placeItems(mode === "rungun" ? course() : []); }

async function selectMap(name) {
  if (!name) return;
  markMap(name);
  if (name === mapName) return;
  mapName = name;
  try { localStorage.setItem(`flagrun.map.${mode}`, name); } catch {}
  $("play").disabled = true; $("play").textContent = "LOADING…";
  loadBoards(name);
  coursesReady.then(showCourse);
  const job = loading = (async () => {
    const m = await loadMap(`/map3d/maps/${name}.cgz`);
    if (job !== loading) return;
    map = m;
    viewer.setMap(map);
    flags = {};
    for (const e of map.ents) if (e.type === ENT.CTF_FLAG && (e.team === TEAM.CLA || e.team === TEAM.RVSF)) flags[e.team] = e;
    await kit.placeFlags(map);
    radar.setMap(map);
    spawns = spawnPoints(map, new Physics(map), Player);
    order = schedule(name, spawns);
    targets.clear();
    await coursesReady;
    placeForMode();
    startAt = startPoint();
    await Promise.all([viewer.texturesReady, viewer.modelsReady]);
    viewer.warmUp();
  })();
  try { await job; } catch (e) { console.error(e); $("play").textContent = "MAP FAILED TO LOAD"; return; }
  if (job !== loading) return;
  $("play").disabled = false; $("play").textContent = "START";
  $("play").focus();
}

/** Two cubes out from the own flag along the way to the enemy flag (inside the flag model the cloth fills the view). */
function startPoint() {
  const own = flags[TEAM.CLA], enemy = flags[TEAM.RVSF];
  if (!own || !enemy) { const s = courses[mapName]?.start; return s ? { x: s.x, y: s.y } : { x: map.size / 2, y: map.size / 2 }; }
  const g = buildGrid(map);
  const route = astar(g, snap(g, own.x, own.y), snap(g, enemy.x, enemy.y));
  // (route cells are nodes: a platform top is n + cell, cellOf() gives the cube)
  const at = (node) => { const c = cellOf(g, node); return { x: (c & (map.size - 1)) + 0.5, y: (c >> map.sfactor) + 0.5 }; };
  const dist = (q) => Math.hypot(q.x - own.x, q.y - own.y);
  const out = route?.cells.map(at).find((q) => dist(q) >= 2.5);
  return out && dist(out) <= 3.2 ? out : { x: own.x + 0.5, y: own.y + 0.5 }; // the server accepts a start within 3.5
}

// ---- a game ------------------------------------------------------------------
const flagPoint = (e) => ({ x: e.x, y: e.y, floor: map.cubes.floor[(e.y << map.sfactor) + e.x] });
/** AC's flag pickup (entities.cpp): the eye within 2.5 of the flag point at the floor + eye height */
const touches = (p, e) => { const f = flagPoint(e); return Math.hypot(p.o.x - f.x, p.o.y - f.y, p.o.z - (f.floor + p.eyeheight)) < 2.5; };
/** AC's item pickup (entities.cpp checkitems): the eye within 2.5 of the item point at floor + elevation + eye height */
const touchesItem = (p, it) => Math.hypot(p.o.x - it.x, p.o.y - it.y, p.o.z - (it.z + p.eyeheight)) < 2.5;

async function startRun() {
  if (!map || run) return;
  let token = null;
  if (mode !== "qr") { // the QR hunt has no clock and no board: nothing to tell the server
    $("play").disabled = true;
    let r;
    try { r = await post("/run/start", { map: mapName, mode }); } catch { r = { error: "No connection." }; }
    $("play").disabled = false;
    if (r.error) { $("play").textContent = r.error; return; }
    token = r.token;
  }
  run = { token, mode, items: mode === "rungun" ? course() : [], next: 0, phase: mode === "qr" ? "free" : "count", ms: 0, nextSample: 0, trace: [],
    countEnd: performance.now() + COUNTDOWN_MS,
    // Run & Gun: targets up (index -> time), the next index, score; the rifle (also in the QR hunt)
    up: new Map(), pending: [], nextTarget: ALIVE, shots: [], score: 0, streak: 0, hits: 0, heads: 0, picked: 0,
    ammo: RIFLE.magsize, reloadUntil: 0, lastShot: -1e9, burst: 0 };
  targets.clear();
  if (mode === "rungun") for (let k = 0; k < ALIVE; k++) run.pending.push({ k, at: 0 });
  kit.setFlagVisible(TEAM.CLA, true); kit.setFlagVisible(TEAM.RVSF, true);
  await kit.placeItems(run.items); // all pickups back
  $("menu").hidden = true; $("result").hidden = true; $("hud").hidden = false;
  $("timer").hidden = mode === "qr"; $("ammo").hidden = !rifleMode();
  $("pausetext").textContent = mode === "qr" ? "Take your time." : "The clock keeps running.";
  $("giveup").textContent = mode === "qr" ? "Leave" : "Give up";
  const enemy = flags[TEAM.RVSF];
  // the QR maps have no flags: face the course's "look" point (flagrun.json), e.g. cl_cube's cube
  const look = enemy ? { x: enemy.x, y: enemy.y } : courses[mapName]?.look;
  walker.start(map, { at: startAt, lookAt: look, allowFly: false });
  walker.player.pitchvel = 0;
  walker.frozen = mode !== "qr";
  kit.showGun(true, rifleMode() ? "assault" : "knife");
}

// ---- the rifle -------------------------------------------------------------------
let trigger = false;
document.addEventListener("mousedown", (e) => { if (e.button === 0 && walker.locked()) trigger = true; });
document.addEventListener("mouseup", (e) => { if (e.button === 0) trigger = false; });
window.addEventListener("keydown", (e) => { if (e.code === "KeyR" && run && rifleMode() && (run.phase === "get" || run.phase === "free")) reload(); });
function reload() {
  if (run.reloadUntil > run.ms || run.ammo === RIFLE.magsize) return;
  run.reloadUntil = run.ms + RIFLE.reloadtime;
  walker.sounds.play("weapon/auto_reload");
}
const _dir = new (viewer.camera.position.constructor)();
function shoot(p) {
  run.ammo--; run.lastShot = run.ms;
  run.burst++; // shots of this burst, from 1 (weapon.cpp gun::attack)
  walker.sounds.play("weapon/auto"); kit.kick();
  // from the eye along the view; three (x, up, y) -> AC (x, y, z)
  walker.camera.getWorldDirection(_dir);
  // AC's spread (weapon.cpp attackphysics): a random offset per axis, +-spread/2 per 1000 of distance, wider in a burst
  const sp = burstSpread(run.burst), rnd = () => (Math.floor(Math.random() * sp) - sp / 2) / 1000;
  const ray0 = { x: _dir.x + rnd(), y: _dir.z + rnd(), z: _dir.y + rnd() }, len = Math.hypot(ray0.x, ray0.y, ray0.z);
  const ray = { x: ray0.x / len, y: ray0.y / len, z: ray0.z / len }, o = { ...p.o };
  // recoil (weapon.cpp attackphysics): the view gets an upward push that grows with the burst, and the shot pushes you back
  p.pitchvel = burstPitchvel(run.burst);
  const push = (RIFLE.recoil + Math.floor(Math.random() * 8)) * -0.01 * (p.crouching ? 0.75 : 1);
  p.vel.x += _dir.x * push; p.vel.y += _dir.z * push; p.vel.z += _dir.y * push;
  if (run.mode !== "rungun") return;
  const wall = raycube(map, o, ray);
  let best = null;
  for (const [k] of run.up) { const h = hitTarget(o, ray, { ...spawns[order[k % order.length]] }); if (h && h.dist < wall && (!best || h.dist < best.dist)) best = { ...h, k }; }
  if (!best) { run.shots.push([Math.round(run.ms), -1, 0]); run.streak = 0; return; }
  run.shots.push([Math.round(run.ms), best.k, best.head ? 1 : 0]);
  run.up.delete(best.k); targets.hide(best.k);
  run.pending.push({ k: run.nextTarget++, at: run.ms + RESPAWN_MS });
  run.hits++; run.heads += best.head ? 1 : 0;
  run.score += best.head ? POINTS.head : POINTS.body;
  if (++run.streak % POINTS.every === 0) run.score += POINTS.streak;
  walker.sounds.play(best.head ? "misc/headshot" : "player/die1");
}
/** Per physics step: reload, fire, the view rising and settling (physics.cpp smooth pitch). */
function rifleStep(p, ms) {
  if (run.reloadUntil && run.ms >= run.reloadUntil) { run.ammo = RIFLE.magsize; run.reloadUntil = 0; }
  if (!trigger || run.reloadUntil || run.ammo === 0) run.burst = 0; // letting go ends the burst
  if (trigger && !run.reloadUntil && run.ammo > 0 && run.ms - run.lastShot >= RIFLE.attackdelay && (run.mode === "qr" || run.ms <= ROUND_MS)) shoot(p);
  const dp = pitchStep(p, ms);
  if (dp) {
    const e = new (walker.camera.rotation.constructor)(0, 0, 0, "YXZ").setFromQuaternion(walker.camera.quaternion);
    e.x = Math.max(-1.55, Math.min(1.55, e.x + dp * Math.PI / 180));
    walker.camera.quaternion.setFromEuler(e);
  }
  if (run.ammo === 0 && !run.reloadUntil) reload();
}

function sample(p) {
  const t = Math.round(run.ms);
  if (run.trace.length && run.trace[run.trace.length - 1][0] === t) run.trace.pop(); // the finish on a sample tick: one sample per moment
  run.trace.push([t, Math.round(p.o.x * 10), Math.round(p.o.y * 10), Math.round(p.feet * 10)]);
}

walker.onStep = (p, ms) => {
  if (!run) return;
  if (run.phase === "free") { run.ms += ms; rifleStep(p, ms); return; } // QR hunt
  if (run.phase !== "get" && run.phase !== "home") return;
  run.ms += ms;
  if (run.ms >= run.nextSample) { sample(p); run.nextSample += SAMPLE_MS; }
  if (run.mode === "rungun") {
    for (const t of run.pending.filter((t) => t.at <= run.ms)) { run.up.set(t.k, t.at); targets.show(t.k, spawns[order[t.k % order.length]]); }
    run.pending = run.pending.filter((t) => t.at > run.ms);
    rifleStep(p, ms);
    const it = run.items[run.next];
    if (it && touchesItem(p, it)) { // only the next pickup counts
      kit.setItemVisible(run.next, false);
      walker.sounds.play(ITEM_SOUND[it.type] ?? "misc/pickup_ammo_clip");
      run.next++; run.picked++;
    }
    if (run.next >= run.items.length || run.ms >= ROUND_MS) { // the course done, or the time up: the round ends
      sample(p);
      if (run.next >= run.items.length) walker.sounds.play("ctf/flagscore");
      run.phase = "done";
      finishRun();
    }
    return;
  }
  if (run.phase === "get" && touches(p, flags[TEAM.RVSF])) {
    run.phase = "home";
    kit.setFlagVisible(TEAM.RVSF, false);
    walker.sounds.play("ctf/flagpickup");
    walker.sounds.music("music/flaggrab", true); // AC plays its flag-grab track while you carry the flag
  } else if (run.phase === "home" && touches(p, flags[TEAM.CLA])) {
    sample(p);
    run.phase = "done";
    walker.sounds.play("ctf/flagscore");
    walker.sounds.music("music/flaggrab", false); // fades out, as in the game
    finishRun();
  }
};

viewer.onFrame = (dt) => {
  kit.update(dt, walker.active ? walker.player : null);
  if (!run) return;
  if (run.phase === "count") {
    const left = run.countEnd - performance.now();
    $("count").hidden = false;
    $("count").textContent = left > 0 ? Math.ceil(left / 1000) : "GO!";
    if (left <= 0) { run.phase = "get"; walker.frozen = false; sample(walker.player); run.nextSample = SAMPLE_MS; setTimeout(() => { $("count").hidden = true; }, 700); }
  }
  if (rifleMode()) $("ammo").textContent = run.reloadUntil ? "reloading" : `${run.ammo} / ${RIFLE.magsize}`;
  const markers = [], own = flags[TEAM.CLA], enemy = flags[TEAM.RVSF];
  if (run.mode === "rungun") {
    targets.face(walker.player.o);
    $("timer").textContent = fmt(Math.max(0, ROUND_MS - run.ms));
    const it = run.items[run.next];
    $("phase").textContent = `${run.score + run.picked * POINTS.pickup} points · ${it ? `pickup ${run.next + 1} / ${run.items.length}: ${ITEM_NAME[it.type] ?? it.type}` : "course done"}`;
    $("phase").className = "item";
    for (const [k] of run.up) { const t = spawns[order[k % order.length]]; markers.push({ x: t.x, y: t.y, color: k % 2 ? "#3a7be0" : "#e0443a", ring: true }); }
    run.items.forEach((it, k) => { if (k >= run.next) markers.push({ x: it.x, y: it.y, color: k === run.next ? "#f5c242" : "#b9a77a", label: String(k + 1), ring: false, dim: k !== run.next }); });
  } else if (run.mode === "qr") {
    $("phase").textContent = "QR hunt · find the codes, scan them off the screen";
    $("phase").className = "item";
  } else {
    $("timer").textContent = fmt(run.ms);
    const home = run.phase === "home";
    $("phase").textContent = home ? "You have the flag – bring it home to the red flag" : run.phase === "get" || run.phase === "count" ? "Get the blue RVSF flag" : "";
    $("phase").className = home ? "cla" : "rvsf";
    markers.push({ x: own.x, y: own.y, color: "#e0443a", label: home ? "home" : "", ring: home });
    if (!home) markers.push({ x: enemy.x, y: enemy.y, color: "#3a7be0", ring: true });
  }
  radar.draw(walker.player, markers);
};

async function finishRun() {
  const done = run;
  $("again").textContent = done.mode === "rungun" ? "ANOTHER ROUND" : "RUN AGAIN";
  walker.stop();
  kit.showGun(false);
  $("hud").hidden = true;
  $("resbody").innerHTML = `<h1>${done.mode === "rungun" ? "Round over" : fmt(done.ms)}</h1><p class="muted">Checking&hellip;</p>`;
  $("result").hidden = false;
  let r;
  try {
    r = await post("/run/finish", done.mode === "rungun"
      ? { mode: RUNGUN, token: done.token, end_ms: Math.round(done.ms), shots: done.shots, trace: done.trace }
      : { token: done.token, time_ms: Math.round(done.ms), trace: done.trace });
  } catch { r = { error: "No connection – your result could not be saved." }; }
  run = null;
  targets.clear(); trigger = false;
  const ranks = (r) => `${r.player ? ` &middot; #${r.rank} this week, #${r.allTimeRank} all time` : ' <span class="muted">(guest: not on the board)</span>'}`;
  const boards = `<div class="grid"><div><h3><span>This week</span> <small>${esc(mapName)}</small></h3><div id="rweek"></div></div><div><h3>All time</h3><div id="rall"></div></div></div>`;
  if (r.error) {
    $("resbody").innerHTML = `<h1>${done.mode === "rungun" ? "Round over" : fmt(done.ms)}</h1><p class="err">${esc(r.error)}</p>`;
  } else if (done.mode === "rungun") {
    const acc = r.shots ? Math.round(100 * r.hits / r.shots) : 0;
    const pb = r.best && r.best === r.score ? '<b style="color:#f5c242">New personal best!</b>' : r.best ? `Your best: <b>${r.best}</b>` : "";
    $("resbody").innerHTML = `<div class="muted">${esc(mapName)} &middot; Run &amp; Gun</div><div class="big">${r.score} points</div>
      <p class="breakdown">Shooting <b>${r.shooting}</b> (${r.hits} hits, ${r.headshots} headshots, ${acc}% accuracy)
        &middot; pickups <b>${r.pickups} / ${r.course}</b> &times; ${POINTS.pickup}${r.bonus ? ` &middot; time bonus <b>${r.bonus}</b> (done in ${fmt(r.end_ms)})` : ""}</p>
      <p>${pb}${ranks(r)}</p>${boards}`;
    board($("rweek"), r.week); board($("rall"), r.allTime);
    board($("week"), r.week); board($("all"), r.allTime);
  } else {
    const pb = r.best && r.best === r.time_ms ? '<b style="color:#f5c242">New personal best!</b>' : r.best ? `Your best: <b>${fmt(r.best)}</b>` : "";
    $("resbody").innerHTML = `<div class="muted">${esc(mapName)} &middot; flag home in</div><div class="big">${fmt(r.time_ms)}</div><p>${pb}${ranks(r)}</p>${boards}`;
    board($("rweek"), r.week); board($("rall"), r.allTime);
    board($("week"), r.week); board($("all"), r.allTime);
  }
  $("again").focus();
}

function giveUp() {
  run = null; targets.clear(); trigger = false;
  walker.sounds.music("music/flaggrab", false, 0.3);
  walker.stop(); kit.showGun(false);
  $("hud").hidden = true; $("pause").hidden = true; $("result").hidden = true; $("menu").hidden = false;
}

walker.onChange = ({ active, locked, error, waiting }) => {
  $("pause").hidden = !(active && !locked);
  $("pausemsg").textContent = error || (waiting ? "Capturing the mouse…" : "");
  if (active && !locked) $("resume").focus();
};
$("pause").addEventListener("click", (e) => { if (e.target.id !== "giveup") walker.resume(); });
$("giveup").addEventListener("click", (e) => { e.stopPropagation(); giveUp(); });
$("play").addEventListener("click", startRun);
$("again").addEventListener("click", () => { $("result").hidden = true; startRun(); });
$("tomenu").addEventListener("click", () => { $("result").hidden = true; $("menu").hidden = false; });

// ---- guest: wait for the chat code (as on /play) ---------------------------------
<?php if (!$authPlayer && $linkCode): ?>
(function poll() {
  setTimeout(async () => {
    try {
      const r = await (await fetch("/play/link", { headers: { Accept: "application/json" }, cache: "no-store" })).json();
      if (r.linked) { const reload = () => (run ? setTimeout(reload, 2000) : location.reload()); reload(); return; }
    } catch {}
    poll();
  }, 20000);
})();
<?php endif; ?>

window.flagrun = { viewer, walker, kit, targets, get map() { return map; }, get run() { return run; }, get spawns() { return spawns; }, get order() { return order; }, trigger(on) { trigger = on; } }; // for tests and the curious

let firstMode = null;
try { firstMode = localStorage.getItem("flagrun.mode"); } catch {}
selectMode(TABS[firstMode] ? firstMode : "flag");
</script>
</body>
</html>
