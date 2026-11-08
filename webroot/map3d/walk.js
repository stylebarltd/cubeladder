// First-person walk mode with AssaultCube's own movement (physics.js, a port
// of physics.cpp): the same speed, acceleration, jump, crouch and crouch jump,
// strafe double jump, stairs, ladders, clip boxes and solid mapmodels.
//
// Keys as AC's default binds: W A S D move, Space jump, Shift crouch (jump,
// then Shift in the air = crouch jump). Mouse look via pointer lock, with a
// drag-to-look fallback when the browser refuses it. F (fly, no collision) is
// a viewer extra and is off in games (options.allowFly = false).

import * as THREE from "three";
import { PointerLockControls } from "three/addons/controls/PointerLockControls.js";
import { ENT, TEAM } from "./cgz.js";
import { Physics, Player, PHYSFRAMETIME } from "./physics.js";
import { Sounds } from "./sound.js";
import { Ambience } from "./ambience.js";

const FOV = 90;            // AC's default field of view (horizontal, rendergl.cpp fov)
const FLY_SPEED = 32;
// Chromium refuses to re-lock the pointer for about a second after the user
// leaves the lock with Esc; a request in that window fails with only a console
// error. resume() waits this long after the unlock before asking again (the
// click's user activation stays valid for several seconds).
const RELOCK_DELAY_MS = 1250;

const _look = new THREE.Vector3(), _right = new THREE.Vector3(), _move = new THREE.Vector3();

export class Walker {
  constructor(viewer) {
    this.viewer = viewer;
    this.camera = viewer.camera;
    this.controls = new PointerLockControls(this.camera, viewer.canvas);
    this.sounds = new Sounds();
    this.active = false;
    this.fly = false;
    this.allowFly = true;
    this.keys = new Set();
    this.onChange = () => {};        // UI hook: (state) => void
    this.onStep = null;              // game hook, after every physics step: (player, ms) => void
    this.unlockedAt = -Infinity;     // no cooldown before the first lock
    this.pending = null;             // timer of a delayed lock request
    this.error = "";
    this.drag = false;               // look by dragging when pointer lock is refused
    this.frozen = false;             // countdown: look around, but no moving or jumping
    this.acc = 0;                    // ms of physics not yet simulated
    this.prev = { x: 0, y: 0, z: 0 }; // eye before the last physics step, for smooth rendering

    this.controls.addEventListener("lock", () => { this.error = ""; this.drag = false; this.onChange(this.state()); });
    this.controls.addEventListener("unlock", () => { this.releaseKeys(); this.unlockedAt = performance.now(); this.onChange(this.state()); });
    document.addEventListener("pointerlockerror", () => this.lockFailed("pointerlockerror"));
    window.addEventListener("keydown", (e) => {
      if (!this.active) return;
      if (!this.playing()) { // paused: Enter or Space continues
        if (e.code === "Enter" || e.code === "Space") { e.preventDefault(); this.resume(); }
        return;
      }
      if (this.drag && e.code === "Escape") { this.drag = false; this.releaseKeys(); this.unlockedAt = performance.now(); this.onChange(this.state()); return; }
      if (e.code === "Space" || e.code.startsWith("Arrow") || e.code === "Tab") e.preventDefault();
      if (e.repeat) return;
      this.keys.add(e.code);
      // jump: a fresh press, not while crouching (physics.cpp jumpn)
      if (e.code === "Space" && this.player && !this.player.crouching && !this.frozen) this.player.jumpnext = true;
      if (e.code === "KeyF" && this.allowFly) { this.fly = !this.fly; if (!this.fly) this.player.vel.z = 0; this.onChange(this.state()); }
    });
    window.addEventListener("keyup", (e) => {
      this.keys.delete(e.code);
      if (e.code === "Space" && this.player) this.player.jumpnext = false;
    });
    const euler = new THREE.Euler(0, 0, 0, "YXZ");
    viewer.canvas.addEventListener("pointermove", (e) => {
      if (!this.active || !this.drag || !(e.buttons & 1)) return;
      euler.setFromQuaternion(this.camera.quaternion);
      euler.y -= e.movementX * 0.003;
      euler.x = Math.max(-1.55, Math.min(1.55, euler.x - e.movementY * 0.003));
      this.camera.quaternion.setFromEuler(euler);
    });
    window.addEventListener("blur", () => this.releaseKeys());
  }

  releaseKeys() { this.keys.clear(); if (this.player) this.player.jumpnext = false; }

  /**
   * Is the mouse captured? Asked from the document, not PointerLockControls:
   * three.js fires its lock / unlock events before it updates isLocked, so
   * isLocked is stale inside them.
   */
  locked() { return document.pointerLockElement === this.viewer.canvas; }

  playing() { return this.active && (this.locked() || this.drag); }

  state() { return { active: this.active, locked: this.playing(), drag: this.drag, fly: this.fly, waiting: this.pending !== null, error: this.error }; }

  /** Ask for pointer lock, after the browser's re-lock cooldown if needed. Call from a click or key handler. */
  requestLock() {
    this.sounds.unlock();
    if (this.pending !== null) return;
    this.error = "";
    const wait = Math.max(0, this.unlockedAt + RELOCK_DELAY_MS - performance.now());
    const go = () => {
      this.pending = null;
      if (!this.active || this.locked()) return;
      try {
        const p = this.viewer.canvas.requestPointerLock();
        if (p && p.catch) p.catch((err) => this.lockFailed(`${err?.name ?? "error"}: ${err?.message ?? err}`));
      } catch (err) { this.lockFailed(`${err?.name ?? "error"}: ${err?.message ?? err}`); }
      this.onChange(this.state());
    };
    if (wait > 0) { this.pending = setTimeout(go, wait); this.onChange(this.state()); }
    else go();
  }

  /** The browser refused the mouse capture: keep walking, look by dragging. */
  lockFailed(reason) {
    console.warn(`[walk] pointer lock refused: ${reason}`);
    if (!this.active || this.locked()) return;
    this.error = `The browser refused to capture the mouse (${reason}).`;
    this.drag = true;
    this.onChange(this.state());
  }

  /**
   * Enter walk mode. options: at {x, y} (map coordinates, default a CLA
   * spawn), lookAt {x, y} (default the RVSF flag), allowFly (default true).
   */
  start(map, options = {}) {
    this.map = map;
    const v = this.viewer;
    this.saved = { pos: v.camera.position.clone(), quat: v.camera.quaternion.clone(), target: v.controls.target.clone(), fov: v.camera.fov, near: v.camera.near };
    v.controls.enabled = false;
    v.setWalkView(true);
    v.camera.near = 0.1;
    this.applyFov();

    this.allowFly = options.allowFly ?? true;
    this.physics = new Physics(map, {
      jump: () => this.sounds.play("player/jump"),
      land: (hard) => this.sounds.play(hard ? "player/land" : "player/step"),
      crouch: (on) => this.sounds.play(on ? "player/crouch_in" : "player/crouch_out"),
    });
    this.player = new Player();
    let at = options.at;
    if (!at) {
      const spawns = map.ents.filter((e) => e.type === ENT.PLAYERSTART);
      const s = spawns.find((e) => e.team === TEAM.CLA) ?? spawns[0];
      at = s ? { x: s.x + 0.5, y: s.y + 0.5 } : { x: map.size / 2, y: map.size / 2 };
    }
    const ok = this.physics.spawn(this.player, at.x, at.y);
    this.fly = !ok && this.allowFly; // stuck in a wall: fly so you can still look around
    this.prev = { ...this.player.o };
    this.acc = 0;
    this.placeCamera(1);
    let look = options.lookAt;
    if (!look) {
      const flag = map.ents.find((e) => e.type === ENT.CTF_FLAG && e.team === TEAM.RVSF) ?? map.ents.find((e) => e.type === ENT.CTF_FLAG);
      if (flag) look = { x: flag.x + 0.5, y: flag.y + 0.5 };
    }
    if (look) this.camera.lookAt(look.x, this.player.o.z, look.y);

    this.ambience?.stop();
    this.ambience = new Ambience(this.sounds, map);   // the map's ambient sound (cl_lost, cl_cube)
    this.active = true;
    v.walker = this;
    this.requestLock();
    this.onChange(this.state());
    return ok;
  }

  stop() {
    if (!this.active) return;
    this.active = false;
    if (this.pending !== null) { clearTimeout(this.pending); this.pending = null; }
    this.error = "";
    this.drag = false;
    this.sounds.stopAll();
    this.ambience?.stop(); this.ambience = null;
    if (this.locked()) this.controls.unlock();
    const v = this.viewer, s = this.saved;
    v.walker = null;
    v.setWalkView(false);
    v.camera.fov = s.fov; v.camera.near = s.near; v.camera.updateProjectionMatrix();
    v.camera.position.copy(s.pos);
    v.camera.quaternion.copy(s.quat);
    v.controls.target.copy(s.target);
    v.controls.enabled = true;
    v.controls.update();
    this.onChange(this.state());
  }

  resume() { if (this.active) this.requestLock(); }

  /** AC's fov is horizontal; three.js wants the vertical angle. */
  applyFov() {
    const c = this.camera;
    const v = 2 * Math.atan(Math.tan(THREE.MathUtils.degToRad(FOV / 2)) / c.aspect);
    c.fov = THREE.MathUtils.radToDeg(v);
    c.updateProjectionMatrix();
  }

  // ---- per frame ---------------------------------------------------------------

  update(dt) {
    if (!this.active) return;
    if (this.camera.aspect !== this.lastAspect) { this.lastAspect = this.camera.aspect; this.applyFov(); }
    const p = this.player;
    const k = this.keys;
    const playing = this.playing() && !this.frozen;
    // input, as the game reads it
    p.kUp = playing && (k.has("KeyW") || k.has("ArrowUp"));
    p.kDown = playing && (k.has("KeyS") || k.has("ArrowDown"));
    p.move = (p.kUp ? 1 : 0) - (p.kDown ? 1 : 0);
    p.strafe = playing ? (k.has("KeyA") || k.has("ArrowLeft") ? 1 : 0) - (k.has("KeyD") || k.has("ArrowRight") ? 1 : 0) : 0;
    p.trycrouch = playing && !this.fly && (k.has("ShiftLeft") || k.has("ShiftRight"));
    // facing on the map plane: three (x, z) = AC (x, y)
    this.camera.getWorldDirection(_look);
    const len = Math.hypot(_look.x, _look.z) || 1;
    p.fwd.x = _look.x / len; p.fwd.y = _look.z / len;
    p.left.x = p.fwd.y; p.left.y = -p.fwd.x; // screen-left of the view direction

    if (this.fly) {
      if (playing) {
        _right.crossVectors(_look, this.camera.up).normalize();
        _move.copy(_look).multiplyScalar(p.move).addScaledVector(_right, -p.strafe);
        _move.y += (k.has("Space") ? 1 : 0) - (k.has("KeyC") || k.has("ShiftLeft") ? 1 : 0);
        if (_move.lengthSq() > 0) {
          _move.normalize().multiplyScalar(FLY_SPEED * Math.min(dt, 0.1));
          p.o.x += _move.x; p.o.y += _move.z; p.o.z += _move.y;
        }
      }
      this.prev = { ...p.o };
      this.sounds.loop("player/footsteps", false);
      this.placeCamera(1);
      this.ambience?.update(this.camera, dt);
      return;
    }

    // fixed 5 ms physics steps, as the game; a slow frame is caught up (up to 1 s), a longer stall (tab switch) skipped
    this.acc = Math.min(this.acc + dt * 1000, 1000);
    while (this.acc >= PHYSFRAMETIME) {
      this.prev = { ...p.o };
      this.physics.step(p, PHYSFRAMETIME);
      this.onStep?.(p, PHYSFRAMETIME);
      this.acc -= PHYSFRAMETIME;
      if (!this.active) return; // the game ended inside this step (flag scored): stop() has silenced everything, keep it so
    }
    // footsteps while walking on the floor (audiomanager.cpp updateplayerfootsteps)
    const walking = playing && (p.move || p.strafe) && (p.onfloor || p.timeinair <= 50) && !p.inwater;
    this.sounds.loop("player/footsteps", walking && !p.crouching);
    this.sounds.loop("player/crouch", walking && p.crouching);
    this.placeCamera(this.acc / PHYSFRAMETIME);
    this.ambience?.update(this.camera, dt);
  }

  /** Camera at the eye, between the last two physics steps (t = 0..1). */
  placeCamera(t) {
    const a = this.prev, b = this.player.o;
    this.camera.position.set(a.x + (b.x - a.x) * t, a.z + (b.z - a.z) * t, a.y + (b.y - a.y) * t);
  }
}
