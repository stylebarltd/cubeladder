// AssaultCube's own movement and flag sounds (packages/audio, copied to
// sounds/), at the volumes of AC's sound table (server.h). Web Audio, loaded
// on first use; looping sounds (footsteps) start and stop with the movement.

const BASE = new URL("sounds/", import.meta.url).href;
const VOLUME = { // server.h: name -> volume (0 = full)
  "player/jump": 80, "player/step": 90, "player/land": 95,
  "player/footsteps": 40, "player/crouch": 40, "player/crouch_in": 85, "player/crouch_out": 75,
  "ctf/flagpickup": 100, "ctf/flagscore": 100,
  "weapon/auto": 100, "weapon/auto_reload": 100, "misc/headshot": 100, "player/die1": 100, "player/gib": 100,
  "misc/pickup_ammo_clip": 100, "misc/pickup_health": 100, "misc/pickup_armour": 100, "misc/pickup_helmet": 100, "misc/tick9": 100, "music/flaggrab": 100,
};

export class Sounds {
  constructor() {
    this.ctx = null;
    this.buffers = new Map();
    this.loops = new Map(); // name -> { src, gain }
    this.enabled = true;
  }

  /** Call from a click or key handler: browsers start audio only after a user gesture. */
  unlock() {
    if (!this.ctx) {
      this.ctx = new AudioContext();
      this.master = this.ctx.createGain();
      this.master.gain.value = 0.7;
      this.master.connect(this.ctx.destination);
      for (const name of Object.keys(VOLUME)) this.load(name);
    }
    if (this.ctx.state === "suspended") this.ctx.resume();
  }

  load(name) {
    if (!this.buffers.has(name)) {
      this.buffers.set(name, fetch(`${BASE}${name}.ogg`).then((r) => (r.ok ? r.arrayBuffer() : Promise.reject(r.status)))
        .then((b) => this.ctx.decodeAudioData(b)).catch(() => null));
    }
    return this.buffers.get(name);
  }

  async play(name) {
    if (!this.enabled || !this.ctx) return;
    const buf = await this.load(name);
    if (!buf) return;
    const src = this.ctx.createBufferSource(), gain = this.ctx.createGain();
    gain.gain.value = (VOLUME[name] || 100) / 100;
    src.buffer = buf;
    src.connect(gain).connect(this.master);
    src.start();
  }

  /** Looping sound on or off (footsteps). */
  async loop(name, on) {
    const cur = this.loops.get(name);
    if (!on || !this.enabled || !this.ctx) {
      if (cur) { cur.src.stop(); this.loops.delete(name); }
      return;
    }
    if (cur) return;
    this.loops.set(name, { pending: true, src: { stop() {} } });
    const buf = await this.load(name);
    const slot = this.loops.get(name);
    if (!buf || !slot || !slot.pending) return; // turned off meanwhile
    const src = this.ctx.createBufferSource(), gain = this.ctx.createGain();
    gain.gain.value = (VOLUME[name] || 100) / 100;
    src.buffer = buf; src.loop = true;
    src.connect(gain).connect(this.master);
    src.start();
    this.loops.set(name, { src, gain });
  }

  stopAll() { for (const name of [...this.loops.keys()]) this.loop(name, false); }

  /**
   * Music on or off, as AC's musicsuggest / musicfadeout: the flag-grab track
   * plays while you carry the enemy flag and fades out when you score.
   */
  async music(name, on, fadeSeconds = 1.5) {
    const cur = this.track;
    if (!on) {
      if (cur) {
        this.track = null;
        if (cur.gain && this.ctx) {
          const t = this.ctx.currentTime;
          cur.gain.gain.setValueAtTime(cur.gain.gain.value, t);
          cur.gain.gain.linearRampToValueAtTime(0, t + fadeSeconds);
          cur.src.stop(t + fadeSeconds);
        } else cur.cancelled = true;
      }
      return;
    }
    if (!this.enabled || !this.ctx || (cur && cur.name === name)) return;
    if (cur) this.music(cur.name, false, 0.3);
    const slot = { name };
    this.track = slot;
    const buf = await this.load(name);
    if (!buf || slot.cancelled || this.track !== slot) return;
    const src = this.ctx.createBufferSource(), gain = this.ctx.createGain();
    gain.gain.value = 0.5; // AC's default musicvol 128 of 255
    src.buffer = buf; src.loop = true;
    src.connect(gain).connect(this.master);
    src.start();
    Object.assign(slot, { src, gain });
  }
}
