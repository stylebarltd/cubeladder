// Run & Gun on /run: targets - AC player models - stand at the map's spawn
// points, two at a time, in a fixed order per map (the same for every
// player, so scores compare), while you collect the map's pickup course with
// the assault rifle in hand. Weapon numbers are AC's (server.h guns[]: assault attackdelay 120 ms,
// magazine 20, reload 2000 ms, spread 18, isauto); hits are tested as AC does
// (weapon.cpp intersect: a head sphere of HEADSIZE 0.4, a body cylinder of
// the player radius 1.1 up to eye height), walls stop shots (physics.cpp
// raycube), models and clips do not (as in the game).

import * as THREE from "three";
import { loadMd2 } from "./md2.js";
import { ENT, CUBE } from "./cgz.js";

const BASE = new URL("models/game/playermodels/", import.meta.url).href;
export const ROUND_MS = 90000;     // Run & Gun (src/Service/Aim3D.php keeps the same numbers)
export const ALIVE = 5;              // targets up at once (src/Service/Aim3D.php the same)
export const RESPAWN_MS = 300;       // after a hit, the next one comes
export const RIFLE = { attackdelay: 120, magsize: 20, reloadtime: 2000, spread: 18, recoil: 30,
  recoilincrease: 3, recoilbase: 25, maxrecoil: 50, recoilbackfade: 115 }; // server.h guns[GUN_ASSAULT]

/** weapon.cpp assaultrifle::dynspread: wider with every shot of a burst (shots counted from 1) */
export const burstSpread = (shots) => (shots > 2 ? 55 : RIFLE.spread + (shots > 0 ? (shots === 1 ? 5 : 15) : 0));
/** weapon.cpp attackphysics: the view's upward speed after a shot of a burst */
export const burstPitchvel = (shots) => Math.min((shots / RIFLE.recoilincrease) ** 2 + RIFLE.recoilbase / 10, RIFLE.maxrecoil / 10);

/**
 * physics.cpp moveplayer, "smooth pitch": the view rises with pitchvel and
 * slows down; once nearly still it slides back by recoilbackfade. One physics
 * step of curtime ms; returns the pitch change in degrees.
 */
export function pitchStep(p, curtime) {
  if (!p.pitchvel) return 0;
  const fric = 6 / curtime * 20;
  const d = p.pitchvel * (curtime / 1000) * p.maxspeed * (p.crouching ? 0.75 : 1);
  p.pitchvel *= (fric - 3) / fric;
  if (p.pitchvel < 0.05 && p.pitchvel > 0.001) p.pitchvel -= RIFLE.recoilbackfade / 100; // slide back
  if (Math.abs(p.pitchvel) < 0.0005) p.pitchvel = 0;
  return d;
}
export const POINTS = { body: 10, head: 20, streak: 10, every: 5, pickup: 25, bonusPerS: 5 };
const HEADSIZE = 0.4, RADIUS = 1.1, EYE = 4.5, HEAD_Z = 4.6;

/** mulberry32: a small seeded random generator (the server replays the same one) */
export function rng(seed) {
  let a = seed >>> 0;
  return () => { a = (a + 0x6D2B79F5) >>> 0; let t = a; t = Math.imul(t ^ (t >>> 15), t | 1); t ^= t + Math.imul(t ^ (t >>> 7), t | 61); return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
}
export function seedOf(name) { let h = 2166136261; for (const c of name) h = Math.imul(h ^ c.charCodeAt(0), 16777619); return h >>> 0; } // FNV-1a

/**
 * The order of spawn points for a map: a fixed sequence. A spot comes back only
 * after 2 * ALIVE others, so two targets that are up together never share one.
 * spawns: [{x, y, feet}] - the map's player starts where a player can stand.
 */
export function schedule(name, spawns, n = 200) {
  const r = rng(seedOf(name)), out = [];
  const gap = Math.min(2 * ALIVE, spawns.length - 1);
  while (out.length < n && spawns.length > 1) {
    const k = Math.floor(r() * spawns.length);
    if (out.slice(-gap).includes(k)) continue;
    out.push(k);
  }
  return out;
}

/** Spawn points where a player stands: the map's player starts, dropped onto the floor with the game physics. */
export function spawnPoints(map, physics, Player) {
  const out = [];
  for (const e of map.ents) {
    if (e.type !== ENT.PLAYERSTART) continue;
    const p = new Player();
    if (physics.spawn(p, e.x + 0.5, e.y + 0.5)) out.push({ x: e.x + 0.5, y: e.y + 0.5, feet: p.feet });
  }
  return out;
}

/**
 * physics.cpp raycube: distance along a unit ray (AC coordinates) to the first
 * cube wall, floor or ceiling; Infinity when it leaves the map.
 */
export function raycube(map, o, ray) {
  const { size, sfactor, cubes } = map;
  let v = { ...o }, dist = 0;
  for (let nr = 0; nr < 512; nr++) {
    const x = Math.floor(v.x), y = Math.floor(v.y);
    if (x < 0 || y < 0 || x >= size || y >= size) return Infinity;
    const i = (y << sfactor) + x;
    let floor = cubes.floor[i], ceil = cubes.ceil[i];
    if (cubes.type[i] === CUBE.FHF) floor -= cubes.vdelta[i] / 4;
    if (cubes.type[i] === CUBE.CHF) ceil += cubes.vdelta[i] / 4;
    if (cubes.type[i] === CUBE.SOLID || v.z < floor || v.z > ceil) return Math.max(dist - 0.1, 0);
    const dx = ray.x ? (x + (ray.x > 0 ? 1 : 0) - v.x) / ray.x : 1e16;
    const dy = ray.y ? (y + (ray.y > 0 ? 1 : 0) - v.y) / ray.y : 1e16;
    const dz = ray.z ? ((ray.z > 0 ? ceil : floor) - v.z) / ray.z : 1e16;
    if (dz < dx && dz < dy) return dist + dz;
    const step = 0.1 + Math.min(dx, dy);
    v = { x: v.x + ray.x * step, y: v.y + ray.y * step, z: v.z + ray.z * step };
    dist += step;
  }
  return Infinity;
}

/** Distance along the ray to a target, and whether the head was hit; null when missed. AC coordinates. */
export function hitTarget(o, ray, t) {
  // head: sphere around (x, y, feet + HEAD_Z)
  const hc = { x: t.x - o.x, y: t.y - o.y, z: t.feet + HEAD_Z - o.z };
  const b = hc.x * ray.x + hc.y * ray.y + hc.z * ray.z;
  const c = hc.x * hc.x + hc.y * hc.y + hc.z * hc.z - HEADSIZE * HEADSIZE;
  const disc = b * b - c;
  if (disc >= 0 && b - Math.sqrt(disc) > 0) return { dist: b - Math.sqrt(disc), head: true };
  // body: upright cylinder, radius RADIUS, feet .. feet + EYE
  const a2 = ray.x * ray.x + ray.y * ray.y;
  if (a2 < 1e-9) return null;
  const px = o.x - t.x, py = o.y - t.y;
  const bb = px * ray.x + py * ray.y, cc = px * px + py * py - RADIUS * RADIUS;
  const d2 = bb * bb - a2 * cc;
  if (d2 < 0) return null;
  for (const s of [(-bb - Math.sqrt(d2)) / a2, (-bb + Math.sqrt(d2)) / a2]) {
    const z = o.z + ray.z * s;
    if (s > 0 && z >= t.feet && z <= t.feet + EYE) return { dist: s, head: false };
  }
  return null;
}

/** The target models: CLA and RVSF player models standing on their spawn spots, turned to the player. */
export class Targets {
  constructor(scene) {
    this.group = new THREE.Group();
    scene.add(this.group);
    this.meshes = new Map(); // k (schedule index) -> mesh
    this.loader = new THREE.TextureLoader();
    this.ready = Promise.all([
      loadMd2(BASE + "tris.md2", { scale: 1.55, trans: [2, 2, 24] }),
      ...["CLA", "RVSF"].map((team) => new Promise((res) => this.loader.load(`${BASE}${team}/01.jpg`, (t) => { t.colorSpace = THREE.SRGBColorSpace; res(t); }, undefined, () => res(null)))),
    ]).then(([model, cla, rvsf]) => { this.model = model; this.skins = [cla, rvsf]; });
  }

  async show(k, t) {
    await this.ready;
    const mesh = new THREE.Mesh(this.model.geometry, new THREE.MeshLambertMaterial({ map: this.skins[k % 2], color: 0xffffff }));
    mesh.position.set(t.x, t.feet, t.y);
    mesh.scale.set(1, 1, -1); // the viewer's AC -> three mapping mirrors; unmirror so the skin's lettering reads right
    this.meshes.set(k, mesh);
    this.group.add(mesh);
  }

  hide(k) { const m = this.meshes.get(k); if (m) { this.group.remove(m); m.material.dispose(); this.meshes.delete(k); } }
  clear() { for (const k of [...this.meshes.keys()]) this.hide(k); }

  /** Turn every target to face the player (AC coordinates of the player's eye). */
  face(o) { for (const m of this.meshes.values()) m.rotation.y = Math.atan2(o.x - m.position.x, o.y - m.position.z) + MODEL_YAW; }
}
// the MD2 model's front, measured once in a screenshot: added to the angle towards the player
const MODEL_YAW = Math.PI / 2;
