// The game's look for walk mode: AC's assault rifle in the hand (HUD layer,
// with the sway of hudgun.h), the real CLA / RVSF flag models on the flag
// spots and AC's default crosshair. Assets come from models/game/ and
// crosshairs/ (tools/map3d/assets.mjs copies them from the AC install).

import * as THREE from "three";
import { loadMd3 } from "./md3.js";
import { ENT, TEAM, entityBase } from "./cgz.js";

const BASE = new URL("models/game/", import.meta.url).href;
export const CROSSHAIR_URL = new URL("crosshairs/default.png", import.meta.url).href;

/**
 * AC draws its crosshair additively (black = see-through). As an image on a
 * page that needs real transparency: brightness becomes alpha.
 */
export async function crosshairImage() {
  const img = new Image();
  img.src = CROSSHAIR_URL;
  await img.decode();
  const c = document.createElement("canvas");
  c.width = img.naturalWidth; c.height = img.naturalHeight;
  const ctx = c.getContext("2d");
  ctx.drawImage(img, 0, 0);
  const d = ctx.getImageData(0, 0, c.width, c.height);
  for (let i = 0; i < d.data.length; i += 4) {
    const a = Math.max(d.data[i], d.data[i + 1], d.data[i + 2]);
    d.data[i + 3] = a;
    if (a) for (let k = 0; k < 3; k++) d.data[i + k] = Math.min(255, d.data[i + k] * 255 / a);
  }
  ctx.putImageData(d, 0, 0);
  return c.toDataURL();
}

// HUD gun placement in the view (three units at the HUD camera, which sits at the eye):
// AC renders it at the eye, rotated with the view, model scale 1.28 (weapon.cpp renderhudmodel).
const GUN_SCALE = 1.28;
const GUN_OFFSET = new THREE.Vector3(0, 0, 0);
const GUN_YAW = Math.PI / 2;

export class GameKit {
  constructor(viewer) {
    this.viewer = viewer;
    this.loader = new THREE.TextureLoader();
    this.gun = null;
    this.flags = {};            // team -> Group
    this.flagGroup = new THREE.Group();
    this.itemGroup = new THREE.Group();
    viewer.scene.add(this.flagGroup, this.itemGroup);
    this.items = [];
    this.swaymillis = 0;
    this.swaydir = new THREE.Vector3();
  }

  texture(url) {
    return new Promise((resolve) => this.loader.load(url, (t) => { t.colorSpace = THREE.SRGBColorSpace; resolve(t); }, undefined, () => resolve(null)));
  }

  async model(md3, skins) {
    const surfaces = await loadMd3(BASE + md3, [0, 0, 0], 1);
    const g = new THREE.Group();
    for (const s of surfaces) {
      const mat = new THREE.MeshLambertMaterial({ color: 0xffffff, side: THREE.DoubleSide, alphaTest: 0.5 });
      const url = skins[s.name] ?? skins["*"];
      if (url) this.texture(BASE + url).then((t) => { if (t) { mat.map = t; mat.needsUpdate = true; } });
      g.add(new THREE.Mesh(s.geometry, mat));
    }
    return g;
  }

  /**
   * A weapon in the hand, in the HUD layer: the knife (default; nothing to
   * shoot in a flag run) or the assault rifle. AC draws every HUD weapon the
   * same way, so both use the same placement.
   */
  async showGun(on = true, weapon = "knife") {
    if (on && this.gun && this.gunWeapon !== weapon) { this.viewer.hudScene.remove(this.gun); this.gun = null; }
    if (on && !this.gun) {
      this.gunWeapon = weapon;
      const inner = await this.model(`weapons/${weapon}/tris_high.md3`, { weapon: `weapons/${weapon}/skin_weapon.jpg`, hands: "weapons/knife/skin_hands.jpg" });
      inner.scale.setScalar(GUN_SCALE); // right-handed, lower right, as AC's default (checked in screenshots)
      inner.rotation.y = GUN_YAW;
      this.gun = new THREE.Group();
      this.gun.add(inner);
      this.viewer.hudScene.add(this.gun);
    }
    if (this.gun) this.gun.visible = on;
  }

  /** Flag models on both flag spots (cube floor, the entity's yaw). */
  async placeFlags(map) {
    this.flagGroup.clear();
    this.flags = {};
    for (const e of map.ents) {
      if (e.type !== ENT.CTF_FLAG || (e.team !== TEAM.CLA && e.team !== TEAM.RVSF)) continue;
      const name = e.team === TEAM.CLA ? "CLA" : "RVSF";
      const g = await this.model(`pickups/flags/${name}/tris.md3`, { "*": `pickups/flags/${name}/${name}.jpg` });
      const floor = entityBase(map, (e.y << map.sfactor) + e.x);
      g.position.set(e.x, floor, e.y);
      g.rotation.y = THREE.MathUtils.degToRad(180 - e.attr1 / 10); // as the viewer's mapmodels
      this.flags[e.team] = g;
      this.flagGroup.add(g);
    }
  }

  setFlagVisible(team, on) { if (this.flags[team]) this.flags[team].visible = on; }

  /**
   * Pickups of a parkour course: [{x, y, z, type}] (z = floor + elevation).
   * AC draws them 1 above that, bobbing and spinning (entities.cpp renderent).
   */
  async placeItems(items) {
    this.itemGroup.clear();
    this.items = [];
    this.pickups ??= await fetch(BASE + "pickups.json").then((r) => r.json()).catch(() => ({}));
    for (const it of items ?? []) {
      const def = this.pickups[it.type];
      const g = def ? await this.model(def.md3, def.skins) : new THREE.Mesh(new THREE.BoxGeometry(1, 1, 1), new THREE.MeshLambertMaterial({ color: 0xf5c242 }));
      g.position.set(it.x, it.z + 1, it.y);
      g.userData.base = it.z + 1;
      g.userData.phase = it.x + it.y;
      this.items.push(g);
      this.itemGroup.add(g);
    }
  }

  setItemVisible(k, on) { if (this.items[k]) this.items[k].visible = on; }

  /** The weapon kicks back on a shot (hudgun.h kick: back and up, then settles). */
  kick() { this.kickT = performance.now(); }

  /**
   * Per frame: the gun follows the view with AC's sway (hudgun.h calcmove):
   * a lag behind the movement and a semicircle bob while walking.
   */
  update(dt, player) {
    const now = performance.now();
    for (const g of this.items) { // spin and bob as in the game
      g.rotation.y = -THREE.MathUtils.degToRad(now / 10);
      g.position.y = g.userData.base + Math.sin(now / 100 + g.userData.phase) / 20;
    }
    if (!this.gun || !this.gun.visible || !player) return;
    const ms = dt * 1000;
    const k = Math.pow(0.7, ms / 10);
    const v = player.vel, speed = Math.max(Math.hypot(v.x, v.y, v.z), player.maxspeed);
    this.swaydir.multiplyScalar(k).add(new THREE.Vector3(v.x * 1.5, v.z * 0.4, v.y * 1.5).multiplyScalar((1 - k) / speed));
    if (player.onfloor || player.onladder) this.swaymillis += ms;
    const plspeed = Math.min(1, Math.hypot(v.x, v.y));
    const side = Math.sin(this.swaymillis / 105) / 20 * plspeed / 2;
    const up = Math.abs(Math.cos(this.swaymillis / 105) / 20 * plspeed / 2);
    const crouch = player.crouching ? 0.75 : 1;
    // world-space lag turned into view space: the HUD camera does not move, so rotate it by the view
    const cam = this.viewer.camera;
    const lag = this.swaydir.clone().applyQuaternion(cam.quaternion.clone().invert());
    const kt = this.kickT ? (performance.now() - this.kickT) / 120 : 9;
    const kick = kt < 1 ? Math.sin(kt * Math.PI) * 0.12 : 0;
    this.gun.position.set(GUN_OFFSET.x - side * crouch - lag.x, GUN_OFFSET.y - up * crouch - lag.y + kick * 0.3, GUN_OFFSET.z - lag.z + kick);
  }
}
