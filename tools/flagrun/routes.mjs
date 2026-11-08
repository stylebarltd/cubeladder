// Flag run and parkour: per-map flag positions, the shortest walkable route
// flag to flag, and a fixed parkour course (the same items in the same order
// for every player, so times compare), for the page and the server
// (fair-play checks, FlagRun.php).
//
// Parkour course: the PARKOUR_ITEMS best reachable pickups, scored by height
// above the map's lowest pickup plus a bonus for the valuable ones, visited as
// a walking tour from the own flag (always the nearest remaining item by the
// walkable route) and back home. A course with "fixed": true in the JSON is
// kept as it is (curated by hand).
// Uses the viewer's own parser and walk grid (webroot/map3d/cgz.js, path.js).
//
//   node tools/flagrun/routes.mjs [map ...]   (default: every .cgz in webroot/map3d/maps)
//
// Writes webroot/map3d/maps/flagrun.json. Re-run after adding a map there.
import fs from "node:fs";
import path from "node:path";
import zlib from "node:zlib";
import { fileURLToPath, pathToFileURL } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const dir = path.join(root, "webroot/map3d");
globalThis.window = { pako: { inflate: (b) => new Uint8Array(zlib.gunzipSync(b)) } };
globalThis.fetch = async (u) => {
  const p = path.join(dir, u);
  const ok = fs.existsSync(p);
  return { ok, status: ok ? 200 : 404, arrayBuffer: async () => fs.readFileSync(p), text: async () => fs.readFileSync(p, "utf8") };
};
const { loadMap, ENT, TEAM } = await import(pathToFileURL(path.join(dir, "cgz.js")).href);
const { buildGrid, snap, astar, heightOf, cellOf } = await import(pathToFileURL(path.join(dir, "path.js")).href);
const { ENT_NAMES } = await import(pathToFileURL(path.join(dir, "cgz.js")).href);
const PARKOUR_ITEMS = 8;
const BONUS = { I_ARMOUR: 6, I_AKIMBO: 6, I_HELMET: 4, I_HEALTH: 2, I_GRENADE: 1 };
const outFile = path.join(dir, "maps/flagrun.json");
const previous = fs.existsSync(outFile) ? JSON.parse(fs.readFileSync(outFile, "utf8")) : {};

const names = process.argv.slice(2).length ? process.argv.slice(2)
  : fs.readdirSync(path.join(dir, "maps")).filter((f) => f.endsWith(".cgz")).map((f) => f.slice(0, -4)).sort();
const out = { ...previous };
for (const name of names) {
  const map = await loadMap(`maps/${name}.cgz`);
  const grid = buildGrid(map);
  const flag = (team) => map.ents.find((e) => e.type === ENT.CTF_FLAG && e.team === team);
  const cla = flag(TEAM.CLA), rvsf = flag(TEAM.RVSF);
  const at = (e, node) => ({ x: e.x + 0.5, y: e.y + 0.5, z: Math.round(heightOf(grid, node) * 100) / 100 });
  const entry = { title: map.title };
  // the start: the CLA flag, or on maps without flags (ac_village) a CLA spawn
  const spawn = map.ents.find((e) => e.type === ENT.PLAYERSTART && e.team === TEAM.CLA) ?? map.ents.find((e) => e.type === ENT.PLAYERSTART);
  const startEnt = cla ?? spawn;
  if (!startEnt) { console.log(`${name}: no flags and no spawns, skipped`); continue; }
  const home = snap(grid, startEnt.x, startEnt.y);
  entry.start = at(startEnt, home);
  if (cla && rvsf) {
    const a = home, b = snap(grid, rvsf.x, rvsf.y);
    const there = astar(grid, a, b), back = astar(grid, b, a);
    if (there && back) {
      entry.flags = { cla: at(cla, a), rvsf: at(rvsf, b) };
      entry.route = Math.round((there.length + back.length) * 10) / 10; // cubes, there and back
    }
  }
  entry.parkour = previous[name]?.parkour?.fixed ? previous[name].parkour : parkour(map, grid, home);
  out[name] = entry;
  const pk = entry.parkour;
  console.log(`${name}: ${entry.route ? `flag run ${entry.route} cubes` : "no flag run"}; course ${pk ? `${pk.items.length} items, ${pk.route} cubes` : "none"}`);
}
fs.writeFileSync(outFile, JSON.stringify(out, null, 1) + "\n");

/** The surface node an item stands on: a clip deck at the item's height, else the cube floor. */
function itemNode(map, grid, e) {
  const i = (e.y << map.sfactor) + e.x, z = map.cubes.floor[i] + e.attr1 / 10;
  if (grid.deckWalk[i] && Math.abs(grid.deckH[i] - z) < 1.5) return grid.n + i;
  if (grid.walk[i]) return i;
  return snap(grid, e.x, e.y);
}

/** Does a route cell come within pickup reach of an item (2.5, plus the cell size)? */
function passes(map, grid, node, height, it) {
  const c = cellOf(grid, node), x = (c & (map.size - 1)) + 0.5, y = (c >> map.sfactor) + 0.5;
  const h = height ?? heightOf(grid, node);
  return Math.hypot(x - it.e.x, y - it.e.y) < 2.5 && Math.abs(h - it.z) < 1.5;
}

function parkour(map, grid, home) {
  const items = map.ents.filter((e) => ENT_NAMES[e.type]?.startsWith("I_")).map((e) => ({
    e, type: ENT_NAMES[e.type].slice(2).toLowerCase(), z: map.cubes.floor[(e.y << map.sfactor) + e.x] + e.attr1 / 10, node: itemNode(map, grid, e),
  })).filter((it) => it.node >= 0 && astar(grid, home, it.node) && astar(grid, it.node, home));
  if (items.length < 3) return null;
  const minZ = Math.min(...items.map((it) => it.z));
  const pick = items.map((it) => ({ ...it, score: it.z - minZ + (BONUS[`I_${it.type.toUpperCase()}`] ?? 0) }))
    .sort((p, q) => q.score - p.score || p.e.x - q.e.x || p.e.y - q.e.y).slice(0, PARKOUR_ITEMS);
  // walking tour: always the nearest remaining item (walkable route), then home.
  // path.js keeps one platform per cell (the highest); an item on a lower one (cl_angkorwat's ledges under
  // the prang roof) is routed to the cell's floor, so its leg also pays the climb: twice the rise, the drop once.
  const surface = (it) => (it ? heightOf(grid, it.node) : null);
  const modelled = (it) => !it || Math.abs(surface(it) - it.z) < 1.5;
  const legCost = (from, to, r) => {
    if (modelled(from) && modelled(to)) return r.length;
    const z0 = from ? from.z : heightOf(grid, home), dz = to.z - z0;
    return r.length + (dz > 0 ? 2 * dz : -dz);
  };
  const order = [];
  let cur = home, curIt = null, length = 0;
  const left = [...pick];
  while (left.length) {
    let best = -1, bestLen = Infinity, bestCost = Infinity;
    left.forEach((it, k) => { const r = astar(grid, cur, it.node); if (!r) return; const cost = legCost(curIt, it, r); if (cost < bestCost) { bestCost = cost; bestLen = r.length; best = k; } });
    if (best < 0) break;
    // an item the way passes (pickup reach) comes first: collect along the way, not back and forth
    const way = astar(grid, cur, left[best].node);
    const onWay = modelled(left[best]) ? way.cells.findIndex((c, j) => left.some((it, k) => k !== best && modelled(it) && passes(map, grid, c, way.heights?.[j], it))) : -1;
    if (onWay >= 0) {
      const c = way.cells[onWay], h = way.heights?.[onWay];
      best = left.findIndex((it, k) => k !== best && modelled(it) && passes(map, grid, c, h, it));
      bestLen = astar(grid, cur, left[best].node).length;
    }
    const [it] = left.splice(best, 1);
    order.push(it); length += bestLen; cur = it.node; curIt = it;
  }
  length += astar(grid, cur, home)?.length ?? 0;
  return {
    items: order.map((it) => ({ x: it.e.x, y: it.e.y, z: Math.round(it.z * 100) / 100, type: it.type })),
    route: Math.round(length * 10) / 10,
  };
}
console.log(`wrote ${Object.keys(out).length} maps to webroot/map3d/maps/flagrun.json`);
