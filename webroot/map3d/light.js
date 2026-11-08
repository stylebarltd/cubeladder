// Cave lighting for the big browser-only maps (cl_lost). AC lights every cube from its LIGHT entities and
// the map's ambient; the viewer lights everything evenly (a sun and an ambient light), which is right
// outdoors. A cave under lid models would be as bright as a meadow, so a map marks its dark zones in the
// map config:
//
//   darkzone <x0> <y0> <x1> <y1> <ambient 0..255> [<top>]
//
// Every SPACE cube inside a zone is dark (the ambient) up to the height <top> (the lids over a cave stay
// in daylight) and lit only by the zone's LIGHT entities:
//   attr1 radius (cubes), attr2 / attr3 / attr4 colour (AC: green = blue = 0 is white of brightness attr2),
//   attr5 kind: 0 steady, 1 flicker (a torch), 2 pulse (a slow glow), 3 failing (a lamp that drops out),
//   the entity's z: its height above the floor (cubes; 0 = 4).
// Light reaches a point when the straight line from the light's cube to the point's cube runs through dark
// cubes only (the rock between two corridors casts the shadow), and fades with the 3D distance.
//   lidzone <x0> <y0> <x1> <y1> <z>   a stone layer at height z (cl_cube's maze under the star chamber): light
//                                      does not pass it, from either side.
// The result multiplies the vertex colours of the cave's floors and walls and of the models in it, so the
// sun and the ambient light still shape the faces; flickering lights are updated every frame.

import * as THREE from "three";
import { CUBE, ENT, entityBase } from "./cgz.js";

const GAIN = 1.25;          // full light colour (255) -> tint; the scene's own lights add up to ~2-3
const MAX_TINT = 1.4;

export class CaveLight {
  constructor(map) {
    this.map = map;
    const zones = map.config?.darkzones ?? [];
    this.active = map.size >= 512 && zones.length > 0;
    if (!this.active) return;
    const { size, sfactor, cubes } = map;
    this.size = size; this.sf = sfactor;
    this.dark = new Uint8Array(size * size);
    this.amb = new Float32Array(size * size);
    this.top = new Float32Array(size * size);
    // lid layers (lidzone): a cube's lid height, NaN where there is none
    this.lid = new Float32Array(size * size).fill(NaN);
    for (const z of map.config?.lidzones ?? []) {
      for (let y = Math.max(0, z.y0); y <= Math.min(size - 1, z.y1); y++) for (let x = Math.max(0, z.x0); x <= Math.min(size - 1, z.x1); x++) this.lid[(y << sfactor) + x] = z.z;
    }
    for (const z of zones) {
      for (let y = Math.max(0, z.y0); y <= Math.min(size - 1, z.y1); y++) for (let x = Math.max(0, z.x0); x <= Math.min(size - 1, z.x1); x++) {
        const i = (y << sfactor) + x;
        if (cubes.type[i] === CUBE.SPACE) { this.dark[i] = 1; this.amb[i] = z.ambient / 255; this.top[i] = z.top ?? 127; }
      }
    }
    this.lights = [];
    for (const e of map.ents) {
      if (e.type !== ENT.LIGHT) continue;
      const i = (e.y << sfactor) + e.x;
      if (!this.dark[i]) continue;
      const [r, g, b] = e.attr3 || e.attr4 ? [e.attr2, e.attr3, e.attr4] : [e.attr2, e.attr2, e.attr2];
      this.lights.push({ x: e.x + 0.5, y: e.y + 0.5, z: entityBase(map, i) + (e.z || 4), cell: i, r: Math.max(1, e.attr1),
        col: [r / 255 * GAIN, g / 255 * GAIN, b / 255 * GAIN], kind: e.attr5 | 0, idx: this.lights.length, phase: (e.x * 13.7 + e.y * 7.3) % 6.283, f: 1 });
    }
    this.visCache = new Map();
    this.animated = [];      // { array: live colour attribute, orig: Float32Array (pre-light colours), verts: [{ k, base, anim }] , attr }
    this.modelAnim = [];     // same for model geometries
    console.log(`[light] ${this.lights.length} lights in ${zones.length} dark zone(s)`);
  }

  cellAt(x, y) {
    const cx = Math.floor(x), cy = Math.floor(y);
    if (cx < 0 || cy < 0 || cx >= this.size || cy >= this.size) return -1;
    return (cy << this.sf) + cx;
  }
  isDark(i) { return i >= 0 && this.dark[i] === 1; }

  /** Whether light `l` reaches cube i: every cube on the line between them is dark (open cave). */
  visible(l, i) {
    if (i === l.cell) return true;
    const key = i * 1024 + l.idx;
    let v = this.visCache.get(key);
    if (v !== undefined) return v;
    const x1 = (i & (this.size - 1)) + 0.5, y1 = (i >> this.sf) + 0.5;
    const dx = x1 - l.x, dy = y1 - l.y, n = Math.ceil(Math.hypot(dx, dy) * 3);
    v = true;
    for (let s = 1; s < n; s++) {
      const c = this.cellAt(l.x + dx * s / n, l.y + dy * s / n);
      if (!this.isDark(c)) { v = false; break; }
    }
    this.visCache.set(key, v);
    return v;
  }

  /** Light at point (x, y, z) (AC coordinates) seen from dark cube i: { base: [r, g, b], anim: [[light, r, g, b]] }. */
  sample(x, y, z, i) {
    const a = this.amb[i], base = [a, a, a], anim = [];
    const lid = this.lid[i];
    for (const l of this.lights) {
      const d = Math.hypot(x - l.x, y - l.y, (z - l.z) * 0.8);
      if (d >= l.r || !this.visible(l, i)) continue;
      const ll = Number.isNaN(lid) ? this.lid[l.cell] : lid;          // a lid between them (either end's cube)
      if (!Number.isNaN(ll) && (l.z - ll) * (z - ll) < 0) continue;
      const f = Math.pow(1 - d / l.r, 1.5);
      if (l.kind === 0) { base[0] += l.col[0] * f; base[1] += l.col[1] * f; base[2] += l.col[2] * f; }
      else anim.push([l, l.col[0] * f, l.col[1] * f, l.col[2] * f]);
    }
    return { base, anim };
  }

  /** The light colour now (animated lights at their current strength), clamped. */
  static total(s, out = [0, 0, 0]) {
    out[0] = s.base[0]; out[1] = s.base[1]; out[2] = s.base[2];
    for (const [l, r, g, b] of s.anim) { out[0] += r * l.f; out[1] += g * l.f; out[2] += b * l.f; }
    out[0] = Math.min(MAX_TINT, out[0]); out[1] = Math.min(MAX_TINT, out[1]); out[2] = Math.min(MAX_TINT, out[2]);
    return out;
  }

  /**
   * Lights the cave's faces of a merged floor / wall geometry (three coordinates: x, height, y): a triangle
   * belongs to the cube just in front of it (its centre moved along its normal), and only dark cubes change.
   * Multiplies geo.userData.texTint and shadeColor in place; animated vertices are remembered.
   */
  lightGeometry(geo) {
    if (!this.active) return;
    const pos = geo.attributes.position.array, nor = geo.attributes.normal?.array, n = pos.length / 9;
    const tint = geo.userData.texTint, shade = geo.userData.shadeColor;
    const verts = [];
    for (let t = 0; t < n; t++) {
      const o = t * 9;
      const cx = (pos[o] + pos[o + 3] + pos[o + 6]) / 3, cz = (pos[o + 2] + pos[o + 5] + pos[o + 8]) / 3;
      const nx = nor ? nor[o] : 0, nz = nor ? nor[o + 2] : 0;
      const i = this.cellAt(cx + nx * 0.4, cz + nz * 0.4);
      if (!this.isDark(i)) continue;
      for (let v = 0; v < 3; v++) {
        const k = t * 3 + v, p = k * 3;
        if (pos[p + 1] > this.top[i]) continue;
        const s = this.sample(pos[p], pos[p + 2], pos[p + 1], i);
        const c = CaveLight.total(s);
        if (s.anim.length) verts.push({ k, s, tex: [tint[p], tint[p + 1], tint[p + 2]], sh: [shade[p], shade[p + 1], shade[p + 2]] });
        tint[p] *= c[0]; tint[p + 1] *= c[1]; tint[p + 2] *= c[2];
        shade[p] *= c[0]; shade[p + 1] *= c[1]; shade[p + 2] *= c[2];
      }
    }
    // upload only the range the animated lights reach, not the whole geometry, every frame
    if (verts.length) this.animated.push({ geo, verts, lo: verts[0].k, hi: verts[verts.length - 1].k });
  }

  /**
   * A mapmodel standing in a dark cube: its own copy of the geometry with per-vertex light (world position
   * from `matrix`), drawn with vertex colours. Returns the geometry, or null when the model is not in the dark.
   */
  lightModel(geometry, matrix, cell) {
    if (!this.active || !this.isDark(cell)) return null;
    const geo = geometry.clone();
    const pos = geo.attributes.position, nor = geo.attributes.normal;
    const col = new Float32Array(pos.count * 3);
    const p = new THREE.Vector3(), q = new THREE.Vector3(), nm = new THREE.Matrix3().getNormalMatrix(matrix);
    const verts = [];
    for (let k = 0; k < pos.count; k++) {
      p.fromBufferAttribute(pos, k).applyMatrix4(matrix);
      let i = cell;
      if (nor) {
        q.fromBufferAttribute(nor, k).applyMatrix3(nm).normalize();
        const j = this.cellAt(p.x + q.x * 0.3, p.z + q.z * 0.3);
        if (this.isDark(j)) i = j;
      }
      if (p.y > this.top[cell]) { col[k * 3] = col[k * 3 + 1] = col[k * 3 + 2] = 1; continue; }  // above the cave: daylight
      const s = this.sample(p.x, p.z, p.y, i);
      const c = CaveLight.total(s);
      col[k * 3] = c[0]; col[k * 3 + 1] = c[1]; col[k * 3 + 2] = c[2];
      if (s.anim.length) verts.push({ k, s });
    }
    geo.setAttribute("color", new THREE.BufferAttribute(col, 3));
    if (verts.length) this.modelAnim.push({ geo, verts });
    return geo;
  }

  /** The light at the camera (walk mode: the gun in the hand darkens in the cave), or null outdoors. */
  at(x, y, z) {
    if (!this.active) return null;
    const i = this.cellAt(x, y);
    if (!this.isDark(i) || z > this.top[i]) return null;
    return CaveLight.total(this.sample(x, y, z, i));
  }

  /** Per frame: the animated lights' strength now, and the vertices they reach. */
  update(t, textured) {
    if (!this.active || !(this.animated.length || this.modelAnim.length)) return;
    for (const l of this.lights) {
      if (l.kind === 1) l.f = 0.72 + 0.16 * Math.sin(t * 11 + l.phase) + 0.12 * Math.sin(t * 23.7 + l.phase * 2.1);
      else if (l.kind === 2) l.f = 0.55 + 0.45 * Math.sin(t * 1.3 + l.phase);
      else if (l.kind === 3) { const u = (t * 0.9 + l.phase) % 7; l.f = (u > 6.2 && u < 6.35) || (u > 6.5 && u < 6.9) ? 0.08 : 1; }
    }
    const c = [0, 0, 0];
    for (const { geo, verts, lo, hi } of this.animated) {
      const arr = geo.attributes.color.array;
      for (const v of verts) {
        CaveLight.total(v.s, c);
        const o = textured ? v.tex : v.sh, p = v.k * 3;
        arr[p] = o[0] * c[0]; arr[p + 1] = o[1] * c[1]; arr[p + 2] = o[2] * c[2];
        // keep the stored per-mode colours in step, so a mode switch shows the current light
        geo.userData.texTint[p] = v.tex[0] * c[0]; geo.userData.texTint[p + 1] = v.tex[1] * c[1]; geo.userData.texTint[p + 2] = v.tex[2] * c[2];
        geo.userData.shadeColor[p] = v.sh[0] * c[0]; geo.userData.shadeColor[p + 1] = v.sh[1] * c[1]; geo.userData.shadeColor[p + 2] = v.sh[2] * c[2];
      }
      const attr = geo.attributes.color;
      attr.clearUpdateRanges(); attr.addUpdateRange(lo * 3, (hi - lo + 1) * 3);
      attr.needsUpdate = true;
    }
    for (const { geo, verts } of this.modelAnim) {
      const arr = geo.attributes.color.array;
      for (const v of verts) {
        CaveLight.total(v.s, c);
        arr[v.k * 3] = c[0]; arr[v.k * 3 + 1] = c[1]; arr[v.k * 3 + 2] = c[2];
      }
      geo.attributes.color.needsUpdate = true;
    }
  }
}
