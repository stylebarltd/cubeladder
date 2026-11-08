// Player movement as AssaultCube 1.3 does it: a port of physics.cpp
// (collide, mmcollide, moveplayer, resizephysent) and the ladder check from
// entities.cpp (checkitems), for one local player on a map from cgz.js.
//
// Coordinates are AC's: x, y on the map grid, z up; `o` is the eye position
// (feet = o.z - eyeheight). Physics runs in fixed 5 ms steps (PHYSFPS 200)
// with 10 collision mini-steps each (moveplayer(player1, 10, ...)), like the
// client. Left out: other players, water physics beyond the basics, corner
// cubes in mip levels above 0 (treated per cube), editing and spectating.
//
// Input per step: move (1 forward, -1 back), strafe (1 left, -1 right),
// jumpnext (a fresh press of jump), trycrouch (crouch held), and the facing
// as two unit vectors on the map plane: fwd and left.

import { CUBE, ENT, entityBase } from "./cgz.js";

export const PHYSFRAMETIME = 5;            // ms (PHYSFPS 200)
const MOVERES = 10;
const MINBORD = 2;
const TAGANYCLIP = 0x40 | 0x80;            // TAGCLIP | TAGPLCLIP (world.h)
const SQRT2HALF = Math.SQRT2 / 2;
const BUCKET_SHIFT = 5;                     // 32-cube buckets for clips and models (Physics.buckets)
const NONE = [];

export class Player {
  constructor() {
    this.o = { x: 0, y: 0, z: 0 };
    this.vel = { x: 0, y: 0, z: 0 };
    this.maxeyeheight = 4.5; this.eyeheight = 4.5; this.aboveeye = 0.7; this.radius = 1.1; this.maxspeed = 16;
    this.onfloor = false; this.onladder = false; this.inwater = false;
    this.crouching = false; this.trycrouch = false; this.crouchedinair = false; this.eyeheightvel = 0;
    this.jumpnext = false; this.timeinair = 0; this.lastjump = 0; this.lastjumpheight = 0;
    this.move = 0; this.strafe = 0; this.kUp = false; this.kDown = false;
    this.fwd = { x: 0, y: 1 }; this.left = { x: 1, y: 0 };
    this.cornersurface = 0;
  }
  get feet() { return this.o.z - this.eyeheight; }
}

export class Physics {
  /** map: from cgz.js loadMap; events: { jump(), land(hard), crouch(on) } for sounds (optional) */
  constructor(map, events = {}) {
    this.map = map;
    this.events = events;
    this.size = map.size;
    this.sf = map.sfactor;
    this.c = map.cubes;
    this.waterlevel = (map.waterlevel ?? -100000) / 10; // stored in tenths of a cube (cgz.js)
    this.clips = map.ents.filter((e) => e.type === ENT.CLIP || e.type === ENT.PLCLIP);
    const slots = map.config?.mapmodels ?? [];
    this.models = map.ents.filter((e) => e.type === ENT.MAPMODEL && slots[e.attr2]?.h).map((e) => ({ e, mm: slots[e.attr2] }));
    this.ladders = map.ents.filter((e) => e.type === ENT.LADDER);
    // A bucket grid over clips and colliding models (BUCKET cubes square): a jungle map has thousands, and
    // mmcollide runs every mini-step. Each one sits in every bucket its box reaches into.
    this.buckets = new Map();
    const add = (kind, item, x, y, r) => {
      for (let by = (y - r) >> BUCKET_SHIFT; by <= (y + r) >> BUCKET_SHIFT; by++)
        for (let bx = (x - r) >> BUCKET_SHIFT; bx <= (x + r) >> BUCKET_SHIFT; bx++) {
          const k = by * 4096 + bx;
          let b = this.buckets.get(k);
          if (!b) this.buckets.set(k, b = { clips: [], models: [] });
          b[kind].push(item);
        }
    };
    for (const e of this.clips) add("clips", e, e.x, e.y, Math.ceil(Math.max(e.attr2, e.attr3) / 5 * ((e.attr7 & 3) === 3 ? Math.SQRT2 : 1)) + 2);
    for (const m of this.models) add("models", m, m.e.x, m.e.y, Math.ceil(m.mm.rad) + 2);
    this.millis = 0; // game time, for the double-jump and crouch timings
  }

  S(x, y) { return (y << this.sf) + x; }
  /**
   * The height clips and mapmodels stand on: the cube's floor (as in AC). On big browser-only maps (1024,
   * cl_lost) the terrain is one heightfield whose cubes all share the same floor value, so there it is the
   * heightfield's height at the cube (cgz.js entityBase, the viewer uses the same).
   */
  base(i) { return entityBase(this.map, i); }
  outbord(x, y) { return x < MINBORD || y < MINBORD || x >= this.size - MINBORD || y >= this.size - MINBORD; }
  solid(i) { return this.c.type[i] === CUBE.SOLID; }

  /**
   * Puts the player on the lowest surface it can stand on at (x, y): the cube
   * floor, or the top of a box standing on it (collide(d, true) drops the feet
   * onto the highest surface under them, so start at the floor and go up
   * until there is room - starting high would land on a roof above the spot).
   * Returns false when there is no room at all.
   */
  spawn(p, x, y) {
    p.o.x = x; p.o.y = y;
    p.vel.x = p.vel.y = p.vel.z = 0;
    const i = this.S(Math.floor(x), Math.floor(y));
    // a heightfield cube's own floor value is its top; the ground can lie up to 64 below (its vertices)
    let start = this.c.floor[i];
    if (this.c.type[i] === CUBE.FHF) start -= Math.max(this.c.vdelta[i], this.c.vdelta[i + 1], this.c.vdelta[i + this.size], this.c.vdelta[i + this.size + 1]) / 4;
    for (let feet = start; feet < this.c.ceil[i]; feet += 0.5) {
      p.o.z = feet + p.eyeheight;
      if (!this.collide(p, true, 0, 0)) return true;
    }
    p.o.z = this.c.floor[i] + p.eyeheight;
    return false;
  }

  // ---- collide (physics.cpp) ------------------------------------------------

  /**
   * Corner cube at mip 0 (cornertest): which quarter is solid, and the
   * companion cube that gives floor and ceiling for a corner between
   * non-solids. Returns { q, h } or null when the corner is malformed.
   */
  cornertest(x, y) {
    const z = this.S(x - 1, y), t = this.S(x + 1, y), w = this.S(x, y - 1), v = this.S(x, y + 1);
    const c = this.c;
    if (this.solid(z)) {
      if (this.solid(w)) return { q: 2, h: -1 };
      if (this.solid(v)) return { q: 3, h: -1 };
    } else if (this.solid(t)) {
      if (this.solid(w)) return { q: 1, h: -1 };
      if (this.solid(v)) return { q: 0, h: -1 };
    } else {
      const wv = c.ceil[w] - c.floor[w] < c.ceil[v] - c.floor[v];
      const h = wv ? v : w;
      const q = c.ceil[z] - c.floor[z] < c.ceil[t] - c.floor[t] ? (wv ? 2 : 3) : (wv ? 1 : 0);
      return { q, h };
    }
    return null;
  }

  /** mmcollide: clip boxes and mapmodels with a height. Updates the hi/lo box; true = blocked. */
  mmcollide(p, box) {
    const playerheight = p.eyeheight + p.aboveeye;
    const c = this.c;
    const bx0 = Math.floor(p.o.x - p.radius) >> BUCKET_SHIFT, bx1 = Math.floor(p.o.x + p.radius) >> BUCKET_SHIFT;
    const by0 = Math.floor(p.o.y - p.radius) >> BUCKET_SHIFT, by1 = Math.floor(p.o.y + p.radius) >> BUCKET_SHIFT;
    const near = (kind) => {
      if (bx0 === bx1 && by0 === by1) return this.buckets.get(by0 * 4096 + bx0)?.[kind] ?? NONE;
      const out = new Set();
      for (let by = by0; by <= by1; by++) for (let bx = bx0; bx <= bx1; bx++) for (const it of this.buckets.get(by * 4096 + bx)?.[kind] ?? NONE) out.add(it);
      return out;
    };
    for (const e of near("clips")) {
      let hitarea = false;
      const rx = e.attr2 / 5, ry = e.attr3 / 5;
      if ((e.attr7 & 3) === 3) { // rotated 45 degrees
        const qx = (e.x - p.o.x) * SQRT2HALF, qy = (e.y - p.o.y) * SQRT2HALF, rr = p.radius * Math.SQRT2;
        const a1 = Math.abs(qx - qy) - ry - rr, a2 = Math.abs(qx + qy) - rx - rr;
        if (a1 < 0 && a2 < 0) {
          const a3 = a1 + a2 + rr;
          if (a3 < 0) hitarea = true;
          if (a3 < -1e-2 && (a3 < -rr || a1 * a2 < 0.42)) p.cornersurface = a1 > a2 ? 1 : 2;
        }
      } else {
        hitarea = Math.abs(e.x - p.o.x) - (rx + p.radius) < 1e-5 && Math.abs(e.y - p.o.y) - (ry + p.radius) < 1e-5;
      }
      if (!hitarea) continue;
      let cz = this.base(this.S(e.x, e.y)) + e.attr1 / 10;
      const ch = e.attr4 / 5;
      if (e.attr6) {
        const sgn = e.attr6 > 0 ? 1 : -1;
        if ((e.attr7 & 3) === 1) cz += Math.floor(0.5 + clamp(p.o.x - e.x + p.radius * sgn, -rx, rx)) * e.attr6 / 40;
        else if ((e.attr7 & 3) === 2) cz += Math.floor(0.5 + clamp(p.o.y - e.y + p.radius * sgn, -ry, ry)) * e.attr6 / 40;
      }
      const dz = p.o.z - p.eyeheight;
      if (dz < cz - 0.42) { if (cz < box.hi) box.hi = cz; }
      else if (cz + ch > box.lo) box.lo = cz + ch;
      if (box.hi - box.lo < playerheight) return true;
      if (dz + 1.26 > cz + ch || dz + playerheight < cz) p.cornersurface = 0;
    }
    for (const { e, mm } of near("models")) {
      const r = mm.rad + p.radius;
      if (Math.abs(e.x - p.o.x) < r && Math.abs(e.y - p.o.y) < r) {
        const mmz = this.base(this.S(e.x, e.y)) + mm.zoff + e.attr3 / 5;
        const dz = p.o.z - p.eyeheight;
        if (dz < mmz - 0.42) { if (mmz < box.hi) box.hi = mmz; }
        else if (mmz + mm.h > box.lo) box.lo = mmz + mm.h;
        if (box.hi - box.lo < playerheight) return true;
      }
    }
    return false;
  }

  /** collide (physics.cpp): true when the player cannot be where it is; otherwise applies gravity / step-up and sets onfloor. */
  collide(p, spawn, drop, rise) {
    p.cornersurface = 0;
    const c = this.c;
    const fx1 = p.o.x - p.radius, fy1 = p.o.y - p.radius, fx2 = p.o.x + p.radius, fy2 = p.o.y + p.radius;
    const x1 = Math.trunc(fx1), y1 = Math.trunc(fy1), x2 = Math.trunc(fx2), y2 = Math.trunc(fy2);
    const box = { hi: 127, lo: -128 };
    const playerheight = p.eyeheight + p.aboveeye;
    const z1 = p.o.z - p.eyeheight + 1.26, z2 = p.o.z - p.eyeheight + playerheight;

    for (let y = y1; y <= y2; y++) for (let x = x1; x <= x2; x++) {
      if (this.outbord(x, y)) return true;
      const i = this.S(x, y);
      let tagclipped = (c.tag[i] & TAGANYCLIP) !== 0;
      let ceil = c.ceil[i], floor = c.floor[i];
      switch (c.type[i]) {
        case CUBE.SOLID: return true;
        case CUBE.CORNER: {
          const ct = this.cornertest(x, y);
          if (!ct) return true; // malformed corner: solid, as in the game
          const { q, h } = ct, bx = x, by = y, bs = 1;
          let matter = false, match = false;
          if (q === 0) { match = x === x2 && y === y2; matter = fx2 - bx + fy2 - by >= bs; }
          else if (q === 1) { match = x === x2 && y === y1; matter = fx2 - bx >= fy1 - by; }
          else if (q === 2) { match = x === x1 && y === y1; matter = fx1 - bx + fy1 - by <= bs; }
          else { match = x === x1 && y === y2; matter = fx1 - bx <= fy2 - by; }
          p.cornersurface = q & 1 ? 5 : 6;
          const n = h >= 0 && !matter ? h : i;
          ceil = c.ceil[n]; floor = c.floor[n];
          if (match && matter) {
            if (h < 0) return true;
            if (z1 < c.floor[i] || z2 > c.ceil[i] || tagclipped) return true;
          }
          if (h >= 0) tagclipped = false;
          p.cornersurface = 0;
          break;
        }
        case CUBE.FHF:
          floor -= (c.vdelta[i] + c.vdelta[this.S(x + 1, y)] + c.vdelta[this.S(x, y + 1)] + c.vdelta[this.S(x + 1, y + 1)]) / 16;
          break;
        case CUBE.CHF:
          ceil += (c.vdelta[i] + c.vdelta[this.S(x + 1, y)] + c.vdelta[this.S(x, y + 1)] + c.vdelta[this.S(x + 1, y + 1)]) / 16;
          break;
      }
      if (tagclipped) return true;
      if (ceil < box.hi) box.hi = ceil;
      if (floor > box.lo) box.lo = floor;
    }
    if (box.hi - box.lo < playerheight) return true;

    const headspace = 10 - 0.01;
    if (this.mmcollide(p, box)) return true;

    if (spawn) {
      p.o.z = box.lo + p.eyeheight;
      p.onfloor = true;
    } else {
      const spacelo = p.o.z - p.eyeheight - box.lo;
      if (spacelo < 0) {
        if (spacelo > -0.01) p.o.z = box.lo + p.eyeheight;  // stick on step
        else if (spacelo > -1.26) p.o.z += rise;            // rise thru stair
        else return true;
      } else {
        p.o.z -= Math.min(drop, spacelo, headspace);         // gravity
      }
      const spacehi = box.hi - (p.o.z + p.aboveeye);
      if (spacehi < 0) {
        if (spacehi < -0.1) return true;
        if (spacelo > 0.1) p.o.z = box.hi - p.aboveeye;      // glue to ceiling in midair
        p.vel.z = 0;                                         // cancel the jump
      }
      const floorclamp = p.crouching && (!p.lastjump || this.millis - p.lastjump > 250) ? 0.1 : 0.01;
      p.onfloor = p.o.z - p.eyeheight - box.lo < floorclamp;
    }
    return false;
  }

  // ---- ladders (entities.cpp checkitems) -------------------------------------

  checkLadders(p) {
    p.onladder = false;
    if (p.crouching) return;
    for (const e of this.ladders) {
      if (this.outbord(e.x, e.y)) continue;
      const dist1 = Math.hypot(p.o.x - e.x, p.o.y - e.y);
      const dist2 = p.o.z - (this.c.floor[this.S(e.x, e.y)] + p.eyeheight);
      if (dist1 < 1.5 && dist2 < e.attr1) { p.onladder = true; return; }
    }
  }

  // ---- moveplayer (physics.cpp), one step of curtime ms ----------------------

  step(p, curtime = PHYSFRAMETIME) {
    this.millis += curtime;
    this.checkLadders(p);
    const timeinair = p.timeinair;
    const move = p.onladder && !p.onfloor && p.move === -1 ? 0 : p.move;
    const water = this.waterlevel > p.o.z - 0.5;
    const chspeed = p.onfloor || p.onladder || !p.crouchedinair ? 0.4 : 1.0;
    const crouching = p.crouching || (p.eyeheight < p.maxeyeheight && p.eyeheight > 1.1);
    const speed = curtime / (water ? 2000 : 1000) * p.maxspeed * (crouching ? chspeed : 1);
    const friction = water ? 20 : p.onfloor ? 6 : p.onladder ? 1.5 : 30;
    const fpsfric = Math.max(friction / curtime * 20, 1);

    // wanted direction: forward/back along the view, strafe along "left"
    const d = { x: move * p.fwd.x + p.strafe * p.left.x, y: move * p.fwd.y + p.strafe * p.left.y, z: 0 };
    const curfullspeed = Math.hypot(d.x, d.y);

    p.vel.x = (p.vel.x * (fpsfric - 1) + d.x) / fpsfric;
    p.vel.y = (p.vel.y * (fpsfric - 1) + d.y) / fpsfric;
    p.vel.z = (p.vel.z * (fpsfric - 1) + d.z) / fpsfric;
    d.x = p.vel.x * speed; d.y = p.vel.y * speed; d.z = p.vel.z * speed;

    if (p.onladder) {
      if (p.kUp && p.move > 0) p.vel.z = 1;
      else if (p.kDown && p.move < 0) p.vel.z = -1;
      p.timeinair = 0;
    } else if (p.onfloor || water) {
      if (p.jumpnext) {
        p.jumpnext = false;
        const doublejump = p.lastjump && this.millis - p.lastjump < 250 && p.strafe !== 0 && p.o.z - p.eyeheight - p.lastjumpheight > 0.2;
        p.lastjumpheight = p.o.z - p.eyeheight;
        p.vel.z = 2;
        if (doublejump && curfullspeed > 0.1) { // more speed on a strafe double jump
          const f = 1.25 / Math.max(Math.hypot(p.vel.x, p.vel.y) / curfullspeed, 1);
          p.vel.x *= f; p.vel.y *= f; p.vel.z *= f;
        }
        if (water) { p.vel.x /= 8; p.vel.y /= 8; }
        this.events.jump?.();
        p.lastjump = this.millis;
      }
      p.timeinair = 0;
      p.crouchedinair = false;
    } else {
      p.timeinair += curtime;
      if (p.trycrouch && !p.crouching && !p.crouchedinair) { // crouch jump: a little extra lift once per jump
        p.vel.z += 0.3;
        p.crouchedinair = true;
      }
    }
    if (timeinair > 200 && !p.timeinair && p.inwater === water) this.events.land?.(timeinair > 800);

    const gravity = 20;
    let dropf = gravity - 1 + p.timeinair / 15;
    if (water) { dropf = 5; p.timeinair = 0; }
    if (p.onladder) { dropf = 0; p.timeinair = 0; }
    const drop = dropf * curtime / gravity / 100 / MOVERES;
    const rise = speed / MOVERES / 1.2;

    for (let i = 0; i < MOVERES; i++) { // discrete steps collision detection & sliding
      const f = 1 / MOVERES;
      const oNull = { ...p.o };
      p.o.x += f * d.x; p.o.y += f * d.y; p.o.z += f * d.z;
      if (!this.collide(p, false, drop, rise)) continue;
      let cs1 = p.cornersurface;
      const oTrying = { ...p.o };
      if (!cs1) { // brute-force check if it is a corner hit after all
        let collx = 0, colly = 0;
        p.o.x = oNull.x;
        if (this.collide(p, false, drop, rise)) collx = p.cornersurface;
        Object.assign(p.o, oTrying);
        p.o.y = oNull.y;
        if (this.collide(p, false, drop, rise)) colly = p.cornersurface;
        cs1 = collx | colly;
        if ((cs1 & 3) === 3) cs1 = 0;
      }
      Object.assign(p.o, oTrying);
      // the wanted direction did not work
      p.o.x = oNull.x; p.o.y = oNull.y;
      const oNullxy = { ...p.o };
      if (cs1) { // slide along a corner wall
        const ct2f = cs1 & 2 ? -1 : 1;
        const diag = Math.abs(d.x + ct2f * d.y) * 0.5;
        const vd = { x: d.y * ct2f + d.x >= 0 ? diag : -diag, y: d.x * ct2f + d.y >= 0 ? diag : -diag };
        const ff = f / (cs1 & 4 ? 42 : 333);
        p.o.x += f * vd.x - ff * d.x;
        p.o.y += f * vd.y - ff * d.y;
        if (!this.collide(p, false, drop, rise)) { d.x = vd.x; d.y = vd.y; continue; }
        Object.assign(p.o, oNullxy);
      }
      p.o.y = oTrying.y; // slide along y
      if (!this.collide(p, false, drop, rise)) { d.x = 0; continue; }
      Object.assign(p.o, oNullxy);
      p.o.x = oTrying.x; // slide along x
      if (!this.collide(p, false, drop, rise)) { d.y = 0; continue; }
      Object.assign(p.o, oNullxy);
      if (!this.collide(p, false, drop, rise)) { d.x = d.y = 0; continue; } // just drop down
      Object.assign(p.o, oNull);
      break;
    }
    p.inwater = water;

    // crouching resizes the body (updatecrouch + resizephysent)
    if (p.crouching !== p.trycrouch) {
      p.crouching = p.trycrouch;
      p.eyeheightvel = p.crouching ? -0.6 : 0.6;
      this.events.crouch?.(p.crouching);
    }
    this.resize(p, curtime, p.maxeyeheight * 3 / 4, p.maxeyeheight);
  }

  resize(p, curtime, min, max) {
    if (p.eyeheightvel === 0) return;
    const water = this.waterlevel > p.o.z;
    const h = p.eyeheightvel * (curtime * p.maxspeed / (water ? 2000 : 1000)) / MOVERES;
    for (let i = 0; i < MOVERES; i++) {
      p.eyeheight += h; p.o.z += h;
      if (this.collide(p, false, 0, 0)) { p.eyeheight -= h; p.o.z -= h; break; } // no room: stay crouched
      if (p.eyeheight < min) { p.o.z += min - p.eyeheight; p.eyeheight = min; p.eyeheightvel = 0; break; }
      if (p.eyeheight > max) { p.o.z -= p.eyeheight - max; p.eyeheight = max; p.eyeheightvel = 0; break; }
    }
  }
}

function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
