// The star chamber over cl_cube's maze: a dome of cubeLadder's players. The map config says where:
//   stardome <x> <y> <z> <radius> <height>      a half ellipsoid standing on (x, y, z), AC coordinates
// and /run/stars (RunController::stars) says what: one star for every player who has played on our servers,
// [country, games, rating x 10], in a fixed order that tells nothing about the player; names for the ten
// highest ranked; the visitor's own star.
//   - each country is a constellation: its stars round one point of the dome (the biggest countries highest up),
//     its brightest few joined by faint lines, its name beside it
//   - the more games, the bigger and brighter the star; the higher the rating, the hotter it burns (orange ->
//     white -> blue); players without a rating are pale and dim
//   - the ten highest ranked carry their names; your own star is gold, pulses, and a beam of light falls from it
// Positions come from the order alone (a seeded random per star), so the sky stays the same between visits.

import * as THREE from "three";
import { getJSON } from "./displays.js";

const EL_TOP = 84 * Math.PI / 180, EL_LOW = 20 * Math.PI / 180;    // constellation centres between these elevations
const GOLD = new THREE.Color(1.0, 0.8, 0.32);

export class Stars {
  constructor(group) {
    this.group = group;
    this.mats = [];
    this.me = null;
  }

  build(map) {
    const domes = map.config?.stardomes ?? [];
    if (!domes.length) return;
    getJSON("/run/stars").then((d) => { if (d?.stars?.length) for (const dm of domes) this.dome(dm, d); });
  }

  /** Per frame: twinkle, the gold pulse, and the point size for this screen (pixels per cube at distance 1). */
  update(t, camera, height) {
    const scale = height / (2 * Math.tan((camera.fov * Math.PI / 180) / 2));
    for (const m of this.mats) { m.uniforms.uTime.value = t; m.uniforms.uScale.value = scale; }
    if (this.me) {
      const k = 0.75 + 0.25 * Math.sin(t * 2.2);
      this.me.beam.material.opacity = 0.07 + 0.05 * k;
      this.me.pool.material.opacity = 0.18 + 0.12 * k;
    }
  }

  dome(dm, d) {
    const { countries, stars, named, me } = d;
    // where each constellation sits: a spiral (golden angle) from the top down, the biggest countries first;
    // players of no known country are strewn over the whole sky
    const centre = countries.map((c, k) => {
      if (!c.code) return null;
      const u = (k + 0.5) / countries.length;
      const el = Math.asin(Math.sin(EL_TOP) - u * (Math.sin(EL_TOP) - Math.sin(EL_LOW)));
      const az = k * 2.399963 + 0.7;
      return dir(az, el);
    });
    const spread = countries.map((c) => 0.03 + 0.0062 * Math.sqrt(c.n));
    const at = (v) => {                                        // a direction to its point on the dome (three coords)
      const s = 1 / Math.sqrt((v.x * v.x + v.z * v.z) / (dm.r * dm.r) + (v.y * v.y) / (dm.h * dm.h));
      return new THREE.Vector3(dm.x + v.x * s, dm.z + v.y * s, dm.y + v.z * s);
    };
    const named_ = new Map(named.map((n) => [n.i, n]));
    const N = stars.length;
    const pos = new Float32Array(N * 3), col = new Float32Array(N * 3), size = new Float32Array(N), phase = new Float32Array(N);
    const byCountry = countries.map(() => []);
    const placed = [];
    stars.forEach(([ci, games, rating], i) => {
      const rnd = mulberry(i * 2654435761 + 17);
      let v;
      if (centre[ci]) v = jitter(centre[ci], spread[ci], rnd);
      else v = dir(rnd() * Math.PI * 2, Math.asin(Math.sin(0.25) + rnd() * (1 - Math.sin(0.25))));
      if (v.y < Math.sin(0.2)) { v.y = Math.sin(0.2); v.normalize(); }
      const p = at(v);
      placed.push(p);
      pos.set([p.x, p.y, p.z], i * 3);
      const g = Math.min(1, Math.log2(1 + games) / Math.log2(800));
      let c, s = 0.22 + 0.75 * g, b = 0.35 + 0.65 * g;
      if (rating) {
        c = temperature((rating / 10 - 1.5) / 8.4); b = Math.max(b, 0.7); s = Math.max(s, 0.55);
      } else {
        const w = rnd(); c = new THREE.Color(0.92 + 0.08 * w, 0.9 + 0.06 * w, 0.84 + 0.16 * (1 - w));
      }
      if (named_.has(i)) { s *= 1.7; b = 1.25; }
      if (me && me.i === i) { c = GOLD.clone(); s = 2.6; b = 1.6; }
      col.set([c.r * b, c.g * b, c.b * b], i * 3);
      size[i] = s; phase[i] = rnd();
      byCountry[ci].push({ i, games, rating });
    });
    const geo = new THREE.BufferGeometry();
    geo.setAttribute("position", new THREE.BufferAttribute(pos, 3));
    geo.setAttribute("aColor", new THREE.BufferAttribute(col, 3));
    geo.setAttribute("aSize", new THREE.BufferAttribute(size, 1));
    geo.setAttribute("aPhase", new THREE.BufferAttribute(phase, 1));
    const mat = new THREE.ShaderMaterial({
      uniforms: { uTime: { value: 0 }, uScale: { value: 800 } },
      vertexShader: `attribute vec3 aColor; attribute float aSize; attribute float aPhase;
        uniform float uTime, uScale; varying vec3 vColor;
        void main() {
          vec4 mv = modelViewMatrix * vec4(position, 1.0);
          float tw = 0.78 + 0.22 * sin(uTime * (0.9 + aPhase * 2.3) + aPhase * 40.0);
          vColor = aColor * tw;
          gl_PointSize = clamp(aSize * uScale / -mv.z, 1.5, 48.0);
          gl_Position = projectionMatrix * mv;
        }`,
      fragmentShader: `varying vec3 vColor;
        void main() {
          float d = length(gl_PointCoord - 0.5);
          if (d > 0.5) discard;
          float a = exp(-d * d * 38.0) + 0.35 * exp(-d * d * 9.0);
          gl_FragColor = vec4(vColor * a, 1.0);
        }`,
      blending: THREE.AdditiveBlending, depthWrite: false, transparent: true,
    });
    this.mats.push(mat);
    const points = new THREE.Points(geo, mat);
    points.frustumCulled = false;
    this.group.add(points);

    // constellation lines: a country's brightest stars, each joined to its nearest earlier one
    const lines = [];
    byCountry.forEach((list, ci) => {
      if (!centre[ci] || list.length < 4) return;
      const best = list.slice().sort((a, b) => b.rating - a.rating || b.games - a.games).slice(0, Math.min(8, 3 + Math.floor(list.length / 40)));
      for (let k = 1; k < best.length; k++) {
        const p = placed[best[k].i];
        let q = null, dq = Infinity;
        for (let j = 0; j < k; j++) { const dd = p.distanceTo(placed[best[j].i]); if (dd < dq) { dq = dd; q = placed[best[j].i]; } }
        if (dq < 22) lines.push(p.x, p.y, p.z, q.x, q.y, q.z);
      }
    });
    const lg = new THREE.BufferGeometry();
    lg.setAttribute("position", new THREE.BufferAttribute(new Float32Array(lines), 3));
    this.group.add(new THREE.LineSegments(lg, new THREE.LineBasicMaterial({ color: 0x5a6fb0, transparent: true, opacity: 0.35, blending: THREE.AdditiveBlending, depthWrite: false })));

    // names: the bigger countries beside their constellation, the ten brightest players, you
    const inward = (p, k) => new THREE.Vector3(dm.x, dm.z + dm.h * 0.25, dm.y).lerp(p, k);
    // (pulled in from the dome, so a flat label is not cut by the corbels behind it)
    countries.filter((c) => c.code).slice(0, 16).forEach((c) => {
      const ci = countries.indexOf(c);
      this.label(c.name.toUpperCase(), inward(at(centre[ci]), 0.86), 2.2, "rgba(140,160,225,0.6)", "600");
    });
    for (const n of named) this.label(n.name, inward(placed[n.i], 0.9).add(new THREE.Vector3(0, -0.8, 0)), 1.5, "rgba(235,240,255,0.85)", "500");
    if (me) {
      const p = placed[me.i];
      this.label(`you · ${me.name}`, inward(p, 0.9).add(new THREE.Vector3(0, -1.2, 0)), 2.2, "rgba(255,212,110,1)", "700");
      // the beam: from your star straight down to the floor, a pool of light where it lands
      const h = p.y - dm.z;
      const beam = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.9, h, 12, 1, true),
        new THREE.MeshBasicMaterial({ color: 0xffcf6a, transparent: true, opacity: 0.1, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide }));
      beam.position.set(p.x, dm.z + h / 2, p.z);
      const pool = new THREE.Mesh(new THREE.CircleGeometry(1.4, 24),
        new THREE.MeshBasicMaterial({ color: 0xffcf6a, transparent: true, opacity: 0.25, blending: THREE.AdditiveBlending, depthWrite: false }));
      pool.rotation.x = -Math.PI / 2; pool.position.set(p.x, dm.z + 0.05, p.z);
      this.group.add(beam, pool);
      this.me = { beam, pool };
    }
    console.log(`[stars] ${N} stars, ${countries.length} countries${me ? ", yours lit" : ""}`);
  }

  label(text, p, height, colour, weight) {
    const c = document.createElement("canvas"), g = c.getContext("2d"), px = 64;
    g.font = `${weight} ${px}px system-ui, sans-serif`;
    c.width = Math.ceil(g.measureText(text).width) + 24; c.height = px + 24;
    g.font = `${weight} ${px}px system-ui, sans-serif`; g.textAlign = "center"; g.textBaseline = "middle";
    g.shadowColor = "rgba(0,0,20,0.9)"; g.shadowBlur = 10; g.fillStyle = colour;
    g.fillText(text, c.width / 2, c.height / 2);
    const tex = new THREE.CanvasTexture(c); tex.colorSpace = THREE.SRGBColorSpace;
    const s = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, transparent: true, depthWrite: false }));
    s.scale.set(height * c.width / c.height, height, 1);
    s.position.copy(p);
    this.group.add(s);
  }
}

/** A unit direction (three coords: y up) at azimuth az, elevation el. */
function dir(az, el) { return new THREE.Vector3(Math.cos(el) * Math.cos(az), Math.sin(el), Math.cos(el) * Math.sin(az)); }
/** A direction scattered round c (gaussian, sigma radians). */
function jitter(c, sigma, rnd) {
  const t1 = new THREE.Vector3(0, 1, 0).cross(c).normalize(), t2 = c.clone().cross(t1);
  const r = sigma * Math.sqrt(-2 * Math.log(1 - rnd() * 0.999)), a = rnd() * Math.PI * 2;
  return c.clone().addScaledVector(t1, r * Math.cos(a)).addScaledVector(t2, r * Math.sin(a)).normalize();
}
/** A star's colour by its rating, 0 (low: orange) .. 1 (high: blue-white). */
function temperature(u) {
  u = Math.max(0, Math.min(1, u));
  const a = new THREE.Color(1.0, 0.55, 0.3), b = new THREE.Color(1.0, 0.97, 0.9), c = new THREE.Color(0.62, 0.75, 1.0);
  return u < 0.5 ? a.lerp(b, u * 2) : b.clone().lerp(c, (u - 0.5) * 2);
}
function mulberry(seed) {
  let a = seed >>> 0;
  return () => { a = (a + 0x6d2b79f5) >>> 0; let t = a; t = Math.imul(t ^ (t >>> 15), t | 1); t ^= t + Math.imul(t ^ (t >>> 7), t | 61); return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
}
