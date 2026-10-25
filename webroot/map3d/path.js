// Walkability grid, A-star and a distance field over the cube grid.
// Model: a player stands on a surface; a neighbour is reachable when it has
// head room and its surface is at most JUMP_STEP above ours (dropping down is
// always allowed, climbing a ladder too). This is "shortest walkable path",
// not the fastest route: strafe jumps and mapmodels are ignored.
//
// Two surfaces per cube: the cube floor (node i) and, where a CLIP / PLCLIP
// box covers the cube, the top of that box (node n + i) - the game's way of
// making platforms, crates and bridges solid. A box also blocks a fall through
// it and, when it sits at body height, walking at floor level.

import { CUBE, ENT } from "./cgz.js";

export const PLAYER_HEIGHT = 5.2; // standing: eyeheight 4.5 + aboveeye 0.7 (physics.cpp playerheight); crouching is not modelled
export const JUMP_STEP = 2.0;     // guess: walking climbs 1.26 (physics.cpp "rise thru stair"), a jump roughly 2 cubes
const STAIR_RISE = 1.26;          // physics.cpp: height a moving player steps up without jumping
const MINBORD = 2;                // outer two cubes are always solid (world.h)

/**
 * Floor / ceiling a player collides with inside cube i (physics.cpp collide):
 * heightfields use the mean of the four corner offsets, not a single corner.
 */
function physFloor(map, i) {
  const c = map.cubes;
  if (c.type[i] !== CUBE.FHF) return c.floor[i];
  return c.floor[i] - cornerDeltaSum(map, i) / 16;
}
function physCeil(map, i) {
  const c = map.cubes;
  if (c.type[i] !== CUBE.CHF) return c.ceil[i];
  return c.ceil[i] + cornerDeltaSum(map, i) / 16;
}
function cornerDeltaSum(map, i) {
  const { size, sfactor, cubes } = map;
  const x = i & (size - 1), y = i >> sfactor;
  const at = (cx, cy) => cubes.vdelta[(Math.min(cy, size - 1) << sfactor) + Math.min(cx, size - 1)];
  return at(x, y) + at(x + 1, y) + at(x, y + 1) + at(x + 1, y + 1);
}

/**
 * Clip boxes (physics.cpp mmcollide): box around (e.x, e.y) with half sizes
 * attr2/5, attr3/5, bottom at the floor of the entity's cube + attr1/10,
 * height attr4/5. attr7 & 3: 1 / 2 = tilted along x / y by attr6/40 per cube,
 * 3 = rotated 45 degrees. Yields [cellIndex, base, top] for each covered cube.
 */
function* clipCells(map, e) {
  const { size, sfactor, cubes } = map;
  const rx = e.attr2 / 5, ry = e.attr3 / 5, hgt = e.attr4 / 5;
  const kind = e.attr7 & 3;
  if (e.x < 0 || e.y < 0 || e.x >= size || e.y >= size) return;
  const base0 = cubes.floor[(e.y << sfactor) + e.x] + e.attr1 / 10;
  const reach = Math.ceil(Math.max(rx, ry) * (kind === 3 ? Math.SQRT2 : 1)) + 1;
  for (let y = Math.max(0, e.y - reach); y <= Math.min(size - 1, e.y + reach); y++) {
    for (let x = Math.max(0, e.x - reach); x <= Math.min(size - 1, e.x + reach); x++) {
      const px = x + 0.5, py = y + 0.5; // cube centre; 0.5 = half a cube stands in for the player's radius
      let inside;
      if (kind === 3) {
        const qx = (e.x - px) * Math.SQRT1_2, qy = (e.y - py) * Math.SQRT1_2;
        inside = Math.abs(qx - qy) - ry < 0.5 && Math.abs(qx + qy) - rx < 0.5;
      } else {
        inside = Math.abs(e.x - px) <= rx + 0.5 && Math.abs(e.y - py) <= ry + 0.5;
      }
      if (!inside) continue;
      let base = base0;
      if (e.attr6 && kind === 1) base += Math.floor(0.5 + Math.min(rx, Math.max(-rx, px - e.x))) * e.attr6 / 40;
      else if (e.attr6 && kind === 2) base += Math.floor(0.5 + Math.min(ry, Math.max(-ry, py - e.y))) * e.attr6 / 40;
      yield [(y << sfactor) + x, base, base + Math.max(0, hgt)];
    }
  }
}

export function buildGrid(map, opts = {}) {
  const playerHeight = opts.playerHeight ?? PLAYER_HEIGHT;
  const jumpStep = opts.jumpStep ?? JUMP_STEP;
  const { size, cubes } = map;
  const n = size * size;
  const walk = new Uint8Array(n);        // floor level usable
  const h = new Float32Array(n);         // floor height
  const ceil = new Float32Array(n);
  const deckWalk = new Uint8Array(n);    // a clip top usable as a surface
  const deckH = new Float32Array(n);     // its height
  const deckBase = new Float32Array(n);  // its underside
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const i = (y << map.sfactor) + x;
      h[i] = physFloor(map, i);
      ceil[i] = physCeil(map, i);
      if (x < MINBORD || y < MINBORD || x >= size - MINBORD || y >= size - MINBORD) continue;
      if (cubes.type[i] === CUBE.SOLID) continue;
      if (ceil[i] - h[i] < playerHeight) continue;
      if (cubes.tag[i] & 0xC0) continue; // TAGCLIP / TAGPLCLIP (world.h): cube clips players
      walk[i] = 1;
    }
  }
  // Clip boxes: a box at body height blocks the floor level; a box top with
  // head room above it is a second surface (the highest one per cube wins).
  for (const e of map.ents) {
    if (e.type !== ENT.CLIP && e.type !== ENT.PLCLIP) continue;
    for (const [i, base, top] of clipCells(map, e)) {
      if (cubes.type[i] === CUBE.SOLID) continue;
      if (walk[i] && base < h[i] + playerHeight && top >= h[i] + 0.5) walk[i] = 0;
      if (top > h[i] + 0.5 && ceil[i] - top >= playerHeight && (!deckWalk[i] || top > deckH[i])) {
        deckWalk[i] = 1; deckH[i] = top; deckBase[i] = base;
      }
    }
  }
  // Ladders (entities.cpp checkitems): a player whose centre is within 1.5
  // units of the ladder entity's (x, y) and less than attr1 above its cube floor
  // is "on the ladder" and can rise without gravity. A cube counts when some
  // point of it is that close (centre within 2.0): ladders usually stand in a
  // clip box, so the player climbs from the cube next to it.
  // ladderTop = highest height reachable from that cube.
  const ladderTop = new Float32Array(n).fill(-Infinity);
  for (const e of map.ents) {
    if (e.type !== ENT.LADDER || e.x < 1 || e.y < 1 || e.x >= size || e.y >= size) continue;
    const top = cubes.floor[(e.y << map.sfactor) + e.x] + e.attr1;
    for (let y = e.y - 2; y <= e.y + 1; y++) for (let x = e.x - 2; x <= e.x + 1; x++) {
      if (x < 0 || y < 0 || x >= size || y >= size || Math.hypot(x + 0.5 - e.x, y + 0.5 - e.y) >= 2.0) continue;
      const i = (y << map.sfactor) + x;
      if (walk[i] && top > ladderTop[i]) ladderTop[i] = top;
    }
  }
  return { size, sfactor: map.sfactor, n, walk, h, ceil, deckWalk, deckH, deckBase, jumpStep, playerHeight, ladderTop };
}

// ---- nodes: cell i = floor surface, n + i = deck (clip top) surface ----------

export const cellOf = (g, node) => node % g.n;
export const heightOf = (g, node) => (node < g.n ? g.h[node] : g.deckH[node - g.n]);
const passable = (g, cell) => g.walk[cell] || g.deckWalk[cell];

const DIRS = [
  [1, 0, 1], [-1, 0, 1], [0, 1, 1], [0, -1, 1],
  [1, 1, Math.SQRT2], [1, -1, Math.SQRT2], [-1, 1, Math.SQRT2], [-1, -1, Math.SQRT2],
];

/** Can a player on node `from` move onto node `to` (adjacent cells)? */
function canStep(g, from, to) {
  const ha = heightOf(g, from), cb = cellOf(g, to);
  // the body must fit under the neighbour's ceiling at the height we come from
  // (no jumping down into a low passage from above its ceiling)
  if (g.ceil[cb] - ha < g.playerHeight) return false;
  const ladderReach = from < g.n ? g.ladderTop[from] + STAIR_RISE : -Infinity;
  if (to < g.n) { // onto the floor of cb
    if (!g.walk[cb]) return false;
    const hb = g.h[cb];
    if (hb - ha > g.jumpStep && ladderReach < hb) return false;
    // falling onto the floor is blocked by a clip box in between: you land on the box instead
    if (g.deckWalk[cb] && g.deckBase[cb] < ha - 0.01 && g.deckH[cb] > hb + 0.01) return false;
    return true;
  }
  if (!g.deckWalk[cb]) return false; // onto the deck of cb (a ladder in its clip box lifts you onto it)
  return g.deckH[cb] - ha <= g.jumpStep || ladderReach >= g.deckH[cb];
}

// Yields [neighbourNode, cost] for every allowed move out of `node` (or into it, reverse).
function* neighbours(g, node, reverse = false) {
  const size = g.size, cell = cellOf(g, node);
  const x = cell & (size - 1), y = cell >> g.sfactor;
  for (const [dx, dy, cost] of DIRS) {
    const nx = x + dx, ny = y + dy;
    if (nx < 0 || ny < 0 || nx >= size || ny >= size) continue;
    const j = (ny << g.sfactor) + nx;
    if (!passable(g, j)) continue;
    if (dx && dy) { // no cutting solid corners
      const a = (y << g.sfactor) + nx, b = (ny << g.sfactor) + x;
      if (!passable(g, a) || !passable(g, b)) continue;
    }
    for (const other of [j, g.n + j]) {
      const ok = reverse ? canStep(g, other, node) : canStep(g, node, other);
      if (ok) yield [other, cost];
    }
  }
}

class Heap {
  constructor() { this.a = []; }
  push(k, v) { const a = this.a; a.push([k, v]); let i = a.length - 1; while (i > 0) { const p = (i - 1) >> 1; if (a[p][0] <= a[i][0]) break; [a[p], a[i]] = [a[i], a[p]]; i = p; } }
  pop() { const a = this.a; const top = a[0]; const last = a.pop(); if (a.length) { a[0] = last; let i = 0; for (;;) { const l = 2 * i + 1, r = l + 1; let m = i; if (l < a.length && a[l][0] < a[m][0]) m = l; if (r < a.length && a[r][0] < a[m][0]) m = r; if (m === i) break; [a[m], a[i]] = [a[i], a[m]]; i = m; } } return top; }
  get length() { return this.a.length; }
}

/** Nearest usable node to cube (x, y), searching outward a few cubes; floor level preferred. */
export function snap(g, x, y) {
  const size = g.size;
  for (let r = 0; r <= 3; r++) {
    for (let dy = -r; dy <= r; dy++) for (let dx = -r; dx <= r; dx++) {
      if (Math.max(Math.abs(dx), Math.abs(dy)) !== r) continue;
      const nx = x + dx, ny = y + dy;
      if (nx < 0 || ny < 0 || nx >= size || ny >= size) continue;
      const i = (ny << g.sfactor) + nx;
      if (g.walk[i]) return i;
      if (g.deckWalk[i]) return g.n + i;
    }
  }
  return -1;
}

/**
 * A-star from node `start` to node `goal` (any surface of the goal's cube counts).
 * Returns { nodes, cells, heights, length } or null.
 */
export function astar(g, start, goal) {
  if (start < 0 || goal < 0) return null;
  const size = g.size, N = 2 * g.n;
  const goalCell = cellOf(g, goal), gx = goalCell & (size - 1), gy = goalCell >> g.sfactor;
  const heur = (node) => { const c = cellOf(g, node); const dx = Math.abs((c & (size - 1)) - gx), dy = Math.abs((c >> g.sfactor) - gy); return Math.max(dx, dy) + (Math.SQRT2 - 1) * Math.min(dx, dy); };
  const dist = new Float64Array(N).fill(Infinity); // Float64: a Float32 store rounds, which breaks the "stale entry" test below
  const prev = new Int32Array(N).fill(-1);
  const closed = new Uint8Array(N);
  const open = new Heap();
  dist[start] = 0;
  open.push(heur(start), start);
  let found = -1;
  while (open.length) {
    const [, i] = open.pop();
    if (closed[i]) continue;
    closed[i] = 1;
    if (cellOf(g, i) === goalCell) { found = i; break; }
    for (const [j, cost] of neighbours(g, i)) {
      const d = dist[i] + cost;
      if (d < dist[j]) { dist[j] = d; prev[j] = i; open.push(d + heur(j), j); }
    }
  }
  if (found < 0) return null;
  const nodes = [];
  for (let i = found; i >= 0; i = prev[i]) nodes.push(i);
  nodes.reverse();
  return { nodes, cells: nodes.map((v) => cellOf(g, v)), heights: nodes.map((v) => heightOf(g, v)), length: dist[found] };
}

/** Dijkstra distance-to-goal per cube (best of its surfaces; reverse edges so jumps count the right way). */
export function distanceField(g, goal) {
  const N = 2 * g.n;
  const dist = new Float64Array(N).fill(Infinity);
  const perCell = new Float64Array(g.n).fill(Infinity);
  if (goal < 0) return perCell;
  const open = new Heap();
  for (const node of [cellOf(g, goal), g.n + cellOf(g, goal)]) {
    if (passable(g, cellOf(g, node)) && (node < g.n ? g.walk[node] : g.deckWalk[node - g.n])) { dist[node] = 0; open.push(0, node); }
  }
  while (open.length) {
    const [d, i] = open.pop();
    if (d > dist[i]) continue;
    for (const [j, cost] of neighbours(g, i, true)) {
      const nd = d + cost;
      if (nd < dist[j]) { dist[j] = nd; open.push(nd, j); }
    }
  }
  for (let i = 0; i < g.n; i++) perCell[i] = Math.min(dist[i], dist[g.n + i]);
  return perCell;
}
