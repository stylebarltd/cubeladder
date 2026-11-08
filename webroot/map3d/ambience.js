// Ambient sound for our browser-only maps (cl_lost, cl_cube), Web Audio, in 3D around the walking player.
// Three kinds of source, all from the map config:
//   mapsound "<ambience/name>" <maxuses>      AC's own sound list; SOUND entities play them looped:
//                                             attr1 slot, attr2 radius (silent beyond), attr3 size (full volume
//                                             within), attr4 volume (0 = full, else 1..255)
//   synth <kind> <x> <y> <z> <radius> [gain]  a sound made here, at a point: crackle (a torch), choir, bells,
//                                             drone (a resonant hum), chimes, stars (notes from all over a dome)
//   soundzone <kind> <x0> <y0> <x1> <y1> <gain> [out]
//                                             a layer while the player is inside the rectangle (or outside, with
//                                             "out"): pad, wind, whispers, smoke (the island's monster, far off),
//                                             drips, stone (grinding, far off), heartbeat, reverb (the room's echo);
//                                             below:<z> / above:<z> limit it to a height (a room over a room)
// Everything is generated at run time (noise, oscillators, filters, a made-up room echo): no files but AC's.

import { Vector3 } from "three";


export class Ambience {
  constructor(sounds, map) {
    this.sounds = sounds;
    this.map = map;
    const c = map.config ?? {};
    this.mapsounds = c.mapsounds ?? [];
    this.synths = c.synths ?? [];
    this.zones = c.soundzones ?? [];
    this.lids = c.lidzones ?? [];          // stone layers (cl_cube's maze under the star chamber): no sound through them
    this.ents = (map.ents ?? []).filter((e) => e.type === 14 && this.mapsounds[e.attr1]);
    this.active = this.ents.length + this.synths.length + this.zones.length > 0;
    this.live = new Map();   // key -> { stop(), ... } sources playing now
    this.layers = new Map(); // zone kind -> { gain, stop }
    this.timers = {};        // zone kind -> next event time
    this.t = 0; this.scan = 0;
  }

  init() {
    const ctx = this.sounds.ctx;
    if (!ctx || this.out) return !!this.out;
    this.ctx = ctx;
    this.out = ctx.createGain(); this.out.gain.value = 0.85;
    this.out.connect(this.sounds.master);
    // the room's echo: a send into a convolver with a made-up impulse (decaying noise)
    this.verb = ctx.createConvolver();
    this.verb.buffer = impulse(ctx, 3.2, 2.6);
    this.wet = ctx.createGain(); this.wet.gain.value = 0;
    this.send = ctx.createGain();
    this.send.connect(this.verb).connect(this.wet).connect(this.out);
    this.noise = noiseBuffer(ctx, 4);
    this.crackleBuf = crackleBuffer(ctx, 6);
    return true;
  }

  stop() {
    for (const s of this.live.values()) s.stop();
    this.live.clear();
    for (const l of this.layers.values()) l.stop();
    this.layers.clear();
    this.out?.disconnect();
    this.out = null;
  }

  /** Per frame: the listener follows the camera; sources near enough start, far ones stop; layers fade. */
  update(camera, dt) {
    if (!this.active || !this.sounds.enabled || !this.init()) return;
    const ctx = this.ctx, L = ctx.listener, p = camera.position;
    this.t += dt;
    const fwd = camera.getWorldDirection(_v);
    if (L.positionX) {
      L.positionX.value = p.x; L.positionY.value = p.y; L.positionZ.value = p.z;
      L.forwardX.value = fwd.x; L.forwardY.value = fwd.y; L.forwardZ.value = fwd.z;
      L.upX.value = 0; L.upY.value = 1; L.upZ.value = 0;
    } else { L.setPosition(p.x, p.y, p.z); L.setOrientation(fwd.x, fwd.y, fwd.z, 0, 1, 0); }
    const x = p.x, y = p.z, z = p.y; // AC coordinates
    if ((this.scan -= dt) <= 0) { this.scan = 0.5; this.place(x, y, z); }
    // layers: on while inside (or outside) their rectangle, faded
    const want = new Map();
    for (const zn of this.zones) {
      const inside = x >= zn.x0 && x <= zn.x1 + 1 && y >= zn.y0 && y <= zn.y1 + 1 && !(zn.below != null && z > zn.below) && !(zn.above != null && z < zn.above);
      if (inside !== !zn.out) continue;
      want.set(zn.kind, Math.max(want.get(zn.kind) ?? 0, zn.gain));
    }
    const verb = want.get("reverb") ?? 0;
    this.wet.gain.setTargetAtTime(verb, ctx.currentTime, 0.8);
    want.delete("reverb");
    for (const [kind, g] of want) {
      if (!this.layers.has(kind) && LAYERS[kind]) this.layers.set(kind, LAYERS[kind](this));
      this.layers.get(kind)?.gain.gain.setTargetAtTime(g, ctx.currentTime, 1.5);
    }
    for (const [kind, l] of this.layers) {
      if (!want.has(kind)) l.gain.gain.setTargetAtTime(0, ctx.currentTime, 1.5);
      l.tick?.(this, x, y, z, dt, want.has(kind));
    }
  }

  /** Whether a lid lies between the listener and a source (at either end's spot). */
  lidBetween(x, y, z, sx, sy, sz) {
    for (const l of this.lids) {
      const a = x >= l.x0 && x <= l.x1 + 1 && y >= l.y0 && y <= l.y1 + 1, b = sx >= l.x0 && sx <= l.x1 + 1 && sy >= l.y0 && sy <= l.y1 + 1;
      if ((a || b) && (z - l.z) * (sz - l.z) < 0) return true;
    }
    return false;
  }

  /** Start the point sources within reach, stop those out of it. */
  place(x, y, z) {
    const near = new Set();
    this.ents.forEach((e, k) => {
      const r = Math.max(4, e.attr2 || 20);
      if (Math.hypot(e.x - x, e.y - y) > r + 8 || this.lidBetween(x, y, z, e.x + 0.5, e.y + 0.5, e.z || 2)) return;
      near.add("e" + k);
      if (!this.live.has("e" + k)) this.live.set("e" + k, this.sample(e));
    });
    this.synths.forEach((s, k) => {
      if (Math.hypot(s.x - x, s.y - y) > s.r + 8 || (s.zmin != null && z < s.zmin) || this.lidBetween(x, y, z, s.x, s.y, s.z)) return;
      near.add("s" + k);
      if (!this.live.has("s" + k) && SYNTHS[s.kind]) this.live.set("s" + k, SYNTHS[s.kind](this, s));
    });
    for (const [key, src] of this.live) if (!near.has(key)) { src.stop(); this.live.delete(key); }
  }

  /** A panner at (x, y, z) (AC coordinates) with full volume within `size`, silent at `radius`. */
  panner(x, y, z, size, radius) {
    const pn = this.ctx.createPanner();
    pn.panningModel = "HRTF"; pn.distanceModel = "linear";
    pn.refDistance = Math.max(0.5, size); pn.maxDistance = Math.max(size + 1, radius); pn.rolloffFactor = 1;
    if (pn.positionX) { pn.positionX.value = x; pn.positionY.value = z; pn.positionZ.value = y; } else pn.setPosition(x, z, y);
    pn.connect(this.out); pn.connect(this.send);
    return pn;
  }

  sample(e) {
    const name = this.mapsounds[e.attr1];
    let src = null, stopped = false;
    const gain = this.ctx.createGain();
    gain.gain.value = e.attr4 ? e.attr4 / 255 : 1;
    gain.connect(this.panner(e.x + 0.5, e.y + 0.5, e.z || 2, e.attr3 || 2, e.attr2 || 20));    // z: the entity's own height
    this.sounds.load(name).then((buf) => {
      if (!buf || stopped) return;
      src = this.ctx.createBufferSource(); src.buffer = buf; src.loop = true;
      src.playbackRate.value = 0.94 + ((e.x * 7 + e.y * 13) % 12) / 100;   // no two the same
      src.connect(gain); src.start(this.ctx.currentTime, Math.random() * buf.duration);
    });
    return { stop() { stopped = true; try { src?.stop(); } catch { /* not started */ } gain.disconnect(); } };
  }
}

const _v = new Vector3();

// ── buffers ──────────────────────────────────────────────────────────────────────────────────
function noiseBuffer(ctx, sec) {
  const SR = ctx.sampleRate, b = ctx.createBuffer(1, SR * sec, SR), d = b.getChannelData(0);
  for (let i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
  return b;
}
function crackleBuffer(ctx, sec) {   // a fire: soft roar and sharp pops
  const SR = ctx.sampleRate, b = ctx.createBuffer(1, SR * sec, SR), d = b.getChannelData(0);
  let lp = 0;
  for (let i = 0; i < d.length; i++) { lp = lp * 0.97 + (Math.random() * 2 - 1) * 0.03; d[i] = lp * 0.9; }
  for (let k = 0; k < sec * 22; k++) {
    const at = Math.floor(Math.random() * (d.length - 800)), a = Math.random() ** 2 * 0.9, len = 40 + Math.random() * 500;
    for (let i = 0; i < len; i++) d[at + i] += (Math.random() * 2 - 1) * a * Math.exp(-i / (len / 5));
  }
  return b;
}
function impulse(ctx, sec, decay) {
  const SR = ctx.sampleRate, b = ctx.createBuffer(2, SR * sec, SR);   // at the context's own rate (a convolver insists)
  for (let ch = 0; ch < 2; ch++) {
    const d = b.getChannelData(ch);
    for (let i = 0; i < d.length; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / d.length, decay) * (i < 400 ? i / 400 : 1);
  }
  return b;
}
function noiseSrc(a, loop = true) {
  const s = a.ctx.createBufferSource(); s.buffer = a.noise; s.loop = loop;
  s.start(a.ctx.currentTime, Math.random() * 3);
  return s;
}
function osc(ctx, type, f, detune = 0) { const o = ctx.createOscillator(); o.type = type; o.frequency.value = f; o.detune.value = detune; o.start(); return o; }
function lfo(ctx, rate, depth, target) { const o = osc(ctx, "sine", rate), g = ctx.createGain(); g.gain.value = depth; o.connect(g).connect(target); return o; }
const stopAll = (nodes) => () => { for (const n of nodes) { try { n.stop?.(); } catch { /* */ } n.disconnect?.(); } };

// ── point sources ────────────────────────────────────────────────────────────────────────────
const SYNTHS = {
  // a torch: the crackle buffer, looped from a random point, a little filtered
  crackle(a, s) {
    const ctx = a.ctx, src = ctx.createBufferSource(), f = ctx.createBiquadFilter(), g = ctx.createGain();
    src.buffer = a.crackleBuf; src.loop = true; src.playbackRate.value = 0.85 + Math.random() * 0.3;
    f.type = "highpass"; f.frequency.value = 300; g.gain.value = 0.55 * (s.gain ?? 1);
    src.connect(f).connect(g).connect(a.panner(s.x, s.y, s.z, 1.5, s.r));
    src.start(ctx.currentTime, Math.random() * 5);
    return { stop: stopAll([src, f, g]) };
  },
  // voices on a slow chord (A minor 9), formant-filtered saws: "aah", breathing
  choir(a, s) {
    const ctx = a.ctx, out = ctx.createGain(); out.gain.value = 0.05 * (s.gain ?? 1);
    out.connect(a.panner(s.x, s.y, s.z, s.r * 0.35, s.r));
    const nodes = [out];
    const f1 = ctx.createBiquadFilter(), f2 = ctx.createBiquadFilter();
    f1.type = f2.type = "bandpass"; f1.frequency.value = 730; f2.frequency.value = 1090; f1.Q.value = 6; f2.Q.value = 8;
    const mix = ctx.createGain(); mix.connect(f1).connect(out); mix.connect(f2).connect(out);
    nodes.push(f1, f2, mix);
    for (const n of [45, 52, 57, 60, 64, 67, 71]) {
      for (const d of [-7, 6]) {
        const o = osc(ctx, "sawtooth", 440 * 2 ** ((n - 69) / 12), d), g = ctx.createGain();
        g.gain.value = 0.25; lfo(ctx, 0.07 + Math.random() * 0.1, 0.2, g.gain);
        lfo(ctx, 5 + Math.random(), 4, o.detune);                       // vibrato
        o.connect(g).connect(mix); nodes.push(o, g);
      }
    }
    return { stop: stopAll(nodes) };
  },
  // a bell tolling now and then (inharmonic partials, long decay)
  bells(a, s) {
    const ctx = a.ctx, out = ctx.createGain(); out.gain.value = 0.22 * (s.gain ?? 1);
    out.connect(a.panner(s.x, s.y, s.z, s.r * 0.3, s.r));
    let alive = true, timer;
    const toll = () => {
      if (!alive) return;
      const t0 = ctx.currentTime;
      for (const [m, amp, dec] of [[1, 1, 6], [2.0, 0.6, 4], [2.76, 0.45, 3], [5.4, 0.25, 1.6], [8.9, 0.12, 0.8]]) {
        const o = ctx.createOscillator(), g = ctx.createGain();
        o.frequency.value = 98 * m; g.gain.setValueAtTime(0, t0); g.gain.linearRampToValueAtTime(amp, t0 + 0.01);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + dec);
        o.connect(g).connect(out); o.start(t0); o.stop(t0 + dec + 0.1);
      }
      timer = setTimeout(toll, (Math.random() < 0.3 ? 4500 : 40000 + Math.random() * 40000));
    };
    timer = setTimeout(toll, 3000 + Math.random() * 8000);
    return { stop: () => { alive = false; clearTimeout(timer); out.disconnect(); } };
  },
  // a resonant hum (the king's chamber): two close sines beating, an octave above, slowly swelling
  drone(a, s) {
    const ctx = a.ctx, out = ctx.createGain(); out.gain.value = 0.12 * (s.gain ?? 1);
    out.connect(a.panner(s.x, s.y, s.z, s.r * 0.5, s.r));
    const nodes = [out];
    for (const [f, g0] of [[117, 0.5], [117.7, 0.5], [234.3, 0.18], [351, 0.06]]) {
      const o = osc(ctx, "sine", f), g = ctx.createGain(); g.gain.value = g0;
      o.connect(g).connect(out); nodes.push(o, g);
    }
    nodes.push(lfo(ctx, 0.05, 0.05, out.gain));
    return { stop: stopAll(nodes) };
  },
  // wind chimes, a pentatonic glint now and then
  chimes(a, s) {
    const ctx = a.ctx, out = ctx.createGain(); out.gain.value = 0.08 * (s.gain ?? 1);
    out.connect(a.panner(s.x, s.y, s.z, s.r * 0.4, s.r));
    let alive = true, timer;
    const notes = [72, 74, 76, 79, 81, 84, 86, 88];
    const ring = () => {
      if (!alive) return;
      const t0 = ctx.currentTime, n = notes[Math.floor(Math.random() * notes.length)];
      for (const [m, amp] of [[1, 1], [2.4, 0.3], [5.9, 0.1]]) {
        const o = ctx.createOscillator(), g = ctx.createGain();
        o.frequency.value = 440 * 2 ** ((n - 69) / 12) * m; g.gain.setValueAtTime(amp, t0); g.gain.exponentialRampToValueAtTime(0.0001, t0 + 2.5 / m);
        o.connect(g).connect(out); o.start(t0); o.stop(t0 + 3);
      }
      timer = setTimeout(ring, 300 + Math.random() * (Math.random() < 0.7 ? 900 : 6000));
    };
    timer = setTimeout(ring, 500);
    return { stop: () => { alive = false; clearTimeout(timer); out.disconnect(); } };
  },
  // the star chamber: the music of the spheres. Glass notes on a slow lydian scale ring out from points high in
  // the dome, now one, now a few together, each with a long tail, over a faint airy shimmer and a deep breath
  stars(a, s) {
    const ctx = a.ctx, out = ctx.createGain(); out.gain.value = 0.11 * (s.gain ?? 1);
    out.connect(a.out); out.connect(a.send);
    const nodes = [out];
    const air = noiseSrc(a), hp = ctx.createBiquadFilter(), ag = ctx.createGain();       // the shimmer
    hp.type = "bandpass"; hp.frequency.value = 7000; hp.Q.value = 3; ag.gain.value = 0.05;
    air.connect(hp).connect(ag).connect(out); nodes.push(air, hp, ag, lfo(ctx, 0.05, 0.04, ag.gain), lfo(ctx, 0.031, 2500, hp.frequency));
    for (const [f, g0] of [[55, 0.22], [82.4, 0.12]]) {                                   // the breath, far below
      const o = osc(ctx, "sine", f), g = ctx.createGain(); g.gain.value = g0;
      o.connect(g).connect(out); nodes.push(o, g, lfo(ctx, 0.023 + f / 9000, g0 * 0.8, g.gain));
    }
    let alive = true, timer;
    const scale = [62, 64, 66, 69, 71, 73, 74, 76, 78, 81, 83, 86];                      // D lydian
    const note = (n, delay) => {
      const t0 = ctx.currentTime + delay, ang = Math.random() * Math.PI * 2, rr = Math.random() * s.r * 0.35;
      const pn = a.panner(s.x + Math.cos(ang) * rr, s.y + Math.sin(ang) * rr, s.z + Math.random() * 20, s.r * 0.5, s.r * 1.6);
      const g = ctx.createGain(); g.gain.value = 0.11 * (s.gain ?? 1); g.connect(pn);
      for (const [m, amp, dec] of [[1, 1, 7], [2, 0.18, 4], [3.01, 0.12, 2.5], [4.2, 0.05, 1.2]]) {
        const o = ctx.createOscillator(), e = ctx.createGain();
        o.frequency.value = 440 * 2 ** ((n - 69) / 12) * m;
        e.gain.setValueAtTime(0, t0); e.gain.linearRampToValueAtTime(amp, t0 + 0.04 + Math.random() * 0.3);
        e.gain.exponentialRampToValueAtTime(0.0001, t0 + dec);
        o.connect(e).connect(g); o.start(t0); o.stop(t0 + dec + 0.1);
      }
      setTimeout(() => pn.disconnect(), (delay + 8) * 1000);
    };
    const play = () => {
      if (!alive) return;
      const k = Math.floor(Math.random() * scale.length), r = Math.random();
      note(scale[k], 0);
      if (r < 0.35) note(scale[Math.min(scale.length - 1, k + 2)], 0.35 + Math.random() * 0.5);   // a second, rising
      if (r < 0.12) note(scale[Math.max(0, k - 3)], 1.1 + Math.random() * 0.8);                   // and a third
      timer = setTimeout(play, 1800 + Math.random() * 5200);
    };
    timer = setTimeout(play, 800);
    return { stop: () => { alive = false; clearTimeout(timer); stopAll(nodes)(); } };
  },
};

// ── layers (non-positional, or events placed round the player) ─────────────────────────────
function layer(a, nodes, extra = {}) {
  const gain = a.ctx.createGain(); gain.gain.value = 0; gain.connect(a.out); gain.connect(a.send);
  return { gain, stop: stopAll([gain, ...nodes]), ...extra, nodes };
}
function eventAt(a, x, y, z, build) { // a one-off sound at a point (it carries its own panner)
  const pn = a.panner(x, y, z, 6, 140);
  const end = build(pn);
  setTimeout(() => pn.disconnect(), (end + 1) * 1000);
}
function every(lo, hi) { return lo + Math.random() * (hi - lo); }

const LAYERS = {
  // the white world: an airy chord that slowly changes colour (C major 9 / F lydian), breathing
  pad(a) {
    const ctx = a.ctx, f = ctx.createBiquadFilter(); f.type = "lowpass"; f.frequency.value = 1400; f.Q.value = 0.7;
    const nodes = [f], l = layer(a, nodes);
    f.connect(l.gain);
    const chordA = [48, 55, 62, 64, 71, 74], chordB = [53, 60, 64, 67, 69, 76];
    const voices = chordA.map((n, i) => {
      const o = osc(ctx, i % 2 ? "triangle" : "sine", 440 * 2 ** ((n - 69) / 12), (i - 2) * 3), g = ctx.createGain();
      g.gain.value = 0.07; lfo(ctx, 0.04 + i * 0.013, 0.04, g.gain); o.connect(g).connect(f); nodes.push(o, g);
      return o;
    });
    nodes.push(lfo(ctx, 0.03, 600, f.frequency));
    let k = 0;
    l.tick = (am, x, y, z, dt) => {
      if ((l.next = (l.next ?? 20) - dt) > 0) return;
      l.next = 24; k ^= 1;
      voices.forEach((o, i) => o.frequency.setTargetAtTime(440 * 2 ** (((k ? chordB : chordA)[i] - 69) / 12), ctx.currentTime, 4));
    };
    return l;
  },
  // wind: noise through a slowly wandering band
  wind(a) {
    const ctx = a.ctx, src = noiseSrc(a), f = ctx.createBiquadFilter(), g = ctx.createGain();
    f.type = "bandpass"; f.frequency.value = 500; f.Q.value = 1.2; g.gain.value = 0.35;
    const l = layer(a, [src, f, g]);
    src.connect(f).connect(g).connect(l.gain);
    l.nodes.push(lfo(ctx, 0.06, 300, f.frequency), lfo(ctx, 0.11, 0.2, g.gain));
    return l;
  },
  // whispers, now and then, from one side: breathy noise in syllables, a soft echo (the island)
  whispers(a) {
    const l = layer(a, []);
    l.tick = (am, x, y, z, dt, on) => {
      if (!on || (l.next = (l.next ?? every(25, 50)) - dt) > 0) return;
      l.next = every(35, 90);
      const ctx = a.ctx, ang = Math.random() * Math.PI * 2, d = every(6, 14);
      eventAt(a, x + Math.cos(ang) * d, y + Math.sin(ang) * d, z + 1, (pn) => {
        const t0 = ctx.currentTime, src = noiseSrc(a, true), f = ctx.createBiquadFilter(), f2 = ctx.createBiquadFilter(), g = ctx.createGain();
        f.type = "bandpass"; f.frequency.value = 2400; f.Q.value = 2.5; f2.type = "peaking"; f2.frequency.value = 900; f2.gain.value = 9;
        g.gain.value = 0; src.connect(f).connect(f2).connect(g).connect(pn);
        let t = t0 + 0.1;
        for (let w = 0; w < 3 + Math.floor(Math.random() * 4); w++) {       // a few words
          for (let s = 0; s < 1 + Math.floor(Math.random() * 3); s++) {     // of a few syllables
            const len = every(0.09, 0.22);
            g.gain.setTargetAtTime(every(0.18, 0.4), t, 0.02); g.gain.setTargetAtTime(0, t + len, 0.03);
            f.frequency.setValueAtTime(every(1800, 3400), t);
            t += len + 0.05;
          }
          t += every(0.12, 0.3);
        }
        src.stop(t + 0.5);
        return t - t0 + 0.5;
      });
    };
    return l;
  },
  // the monster: far off in the trees, ticking machinery, a roar, crashing through and gone
  smoke(a) {
    const l = layer(a, []);
    l.tick = (am, x, y, z, dt, on) => {
      if (!on || (l.next = (l.next ?? every(60, 120)) - dt) > 0) return;
      l.next = every(150, 300);
      const ctx = a.ctx, ang = Math.random() * Math.PI * 2, d = every(45, 70);
      const sx = x + Math.cos(ang) * d, sy = y + Math.sin(ang) * d;
      const pn = a.panner(sx, sy, z + 6, 25, 160);
      const t0 = ctx.currentTime, dur = every(9, 14);
      // the clicking: a train of resonant ticks, speeding up and slowing down
      const clickBus = ctx.createBiquadFilter(); clickBus.type = "bandpass"; clickBus.frequency.value = 1500; clickBus.Q.value = 9;
      const cg = ctx.createGain(); cg.gain.value = 1.2; clickBus.connect(cg).connect(pn);
      let t = t0;
      while (t < t0 + dur) {
        const b = ctx.createBufferSource(); b.buffer = a.noise; const e = ctx.createGain();
        e.gain.setValueAtTime(0.9, t); e.gain.exponentialRampToValueAtTime(0.001, t + 0.03);
        b.connect(e).connect(clickBus); b.start(t, Math.random() * 3, 0.04);
        const phase = (t - t0) / dur;
        t += 0.045 + 0.09 * Math.abs(Math.sin(phase * 7)) + Math.random() * 0.02;
      }
      // the roar: low noise, swelling, a growl in it
      const r = noiseSrc(a), lp = ctx.createBiquadFilter(), rg = ctx.createGain(), gr = osc(ctx, "sawtooth", 46), grg = ctx.createGain();
      lp.type = "lowpass"; lp.frequency.value = 260; rg.gain.value = 0;
      grg.gain.value = 0.25; gr.connect(grg).connect(lp);
      r.connect(lp).connect(rg).connect(pn);
      rg.gain.setTargetAtTime(0.9, t0 + dur * 0.3, 1.2); rg.gain.setTargetAtTime(0, t0 + dur * 0.75, 1.0);
      lp.frequency.setTargetAtTime(700, t0 + dur * 0.35, 1.5); lp.frequency.setTargetAtTime(200, t0 + dur * 0.7, 1.5);
      // it moves: the panner slides across
      const ex = sx + Math.cos(ang + 1.6) * 40, ey = sy + Math.sin(ang + 1.6) * 40;
      if (pn.positionX) { pn.positionX.linearRampToValueAtTime(ex, t0 + dur); pn.positionZ.linearRampToValueAtTime(ey, t0 + dur); }
      r.stop(t0 + dur + 3); gr.stop(t0 + dur + 3);
      setTimeout(() => pn.disconnect(), (dur + 4) * 1000);
    };
    return l;
  },
  // water dripping somewhere near, a falling blip each
  drips(a) {
    const l = layer(a, []);
    l.tick = (am, x, y, z, dt, on) => {
      if (!on || (l.next = (l.next ?? 1) - dt) > 0) return;
      l.next = every(0.7, 4);
      const ctx = a.ctx, ang = Math.random() * Math.PI * 2, d = every(2, 10);
      eventAt(a, x + Math.cos(ang) * d, y + Math.sin(ang) * d, z - 3, (pn) => {
        const t0 = ctx.currentTime, o = ctx.createOscillator(), g = ctx.createGain(), f0 = every(900, 2200);
        o.frequency.setValueAtTime(f0, t0); o.frequency.exponentialRampToValueAtTime(f0 * 0.45, t0 + 0.08);
        g.gain.setValueAtTime(0.0001, t0); g.gain.exponentialRampToValueAtTime(0.35, t0 + 0.005); g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.12);
        o.connect(g).connect(pn); o.start(t0); o.stop(t0 + 0.2);
        return 0.3;
      });
    };
    return l;
  },
  // far off in the stone: something heavy grinding, a dull thud (the cube shifting)
  stone(a) {
    const l = layer(a, []);
    l.tick = (am, x, y, z, dt, on) => {
      if (!on || (l.next = (l.next ?? every(30, 60)) - dt) > 0) return;
      l.next = every(70, 160);
      const ctx = a.ctx, ang = Math.random() * Math.PI * 2;
      eventAt(a, x + Math.cos(ang) * 30, y + Math.sin(ang) * 30, z - 10, (pn) => {
        const t0 = ctx.currentTime, src = noiseSrc(a), f = ctx.createBiquadFilter(), g = ctx.createGain(), dur = every(2.5, 5);
        f.type = "bandpass"; f.frequency.value = 140; f.Q.value = 3; g.gain.value = 0;
        src.connect(f).connect(g).connect(pn);
        g.gain.setTargetAtTime(1.4, t0, 0.4);
        for (let k = 0; k < dur * 6; k++) f.frequency.setValueAtTime(every(90, 260), t0 + k / 6);
        g.gain.setTargetAtTime(0, t0 + dur, 0.2);
        const th = ctx.createOscillator(), tg = ctx.createGain();           // the thud at the end
        th.frequency.setValueAtTime(70, t0 + dur); th.frequency.exponentialRampToValueAtTime(35, t0 + dur + 0.4);
        tg.gain.setValueAtTime(0.0001, t0 + dur); tg.gain.exponentialRampToValueAtTime(1.2, t0 + dur + 0.02); tg.gain.exponentialRampToValueAtTime(0.0001, t0 + dur + 0.9);
        th.connect(tg).connect(pn); th.start(t0 + dur); th.stop(t0 + dur + 1);
        src.stop(t0 + dur + 1.2);
        return dur + 1.5;
      });
    };
    return l;
  },
  // a slow double heartbeat, felt more than heard (the maze)
  heartbeat(a) {
    const l = layer(a, []);
    l.tick = (am, x, y, z, dt, on) => {
      if (!on || (l.next = (l.next ?? 1) - dt) > 0) return;
      l.next = 1.35;
      const ctx = a.ctx, t0 = ctx.currentTime;
      for (const [at, amp] of [[0, 0.9], [0.24, 0.6]]) {
        const o = ctx.createOscillator(), g = ctx.createGain();
        o.frequency.setValueAtTime(58, t0 + at); o.frequency.exponentialRampToValueAtTime(38, t0 + at + 0.18);
        g.gain.setValueAtTime(0.0001, t0 + at); g.gain.exponentialRampToValueAtTime(amp, t0 + at + 0.015); g.gain.exponentialRampToValueAtTime(0.0001, t0 + at + 0.25);
        o.connect(g).connect(l.gain); o.start(t0 + at); o.stop(t0 + at + 0.3);
      }
    };
    return l;
  },
};
