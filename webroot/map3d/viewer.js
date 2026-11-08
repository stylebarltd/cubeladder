// Three.js scene for an AssaultCube map: merged floor/wall geometry, entity
// markers, path lines and an optional distance-field floor colouring.
// Coordinates: AC (x, y, height) -> three (x, height, y). AC's y runs "down" the
// minimap, so this (mirrored handedness) matches what players see in the game.
//
// Textures: every face is assigned the texture slot AC would use (worldrender.cpp
// render_wall: a solid or higher neighbour contributes its wtex, a lower ceiling
// its utex; floors use ftex). Geometry is grouped per slot and drawn with one
// material per slot. UVs are in cube units; the per-texture repeat factor
// TEXTURESCALE(32) * scale / pixels (rendercubes.cpp) is applied through the
// texture's repeat once the image size is known.

import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import { Line2 } from "three/addons/lines/Line2.js";
import { LineMaterial } from "three/addons/lines/LineMaterial.js";
import { LineGeometry } from "three/addons/lines/LineGeometry.js";
import { CUBE, ENT, TEAM, floorAt, entityBase } from "./cgz.js";
import { loadMd3 } from "./md3.js";
import { CaveLight } from "./light.js";
import { Displays } from "./displays.js";
import { Stars } from "./stars.js";

const TEAM_COLOR = { [TEAM.CLA]: 0xe0443a, [TEAM.RVSF]: 0x3a7be0 };
const FLOOR_LOW = new THREE.Color(0xb59a6b);
const FLOOR_HIGH = new THREE.Color(0xe6d4ad);
const WALL_LOW = new THREE.Color(0x6f5d48);
const WALL_HIGH = new THREE.Color(0xc2ae8e);
const CAP_COLOR = new THREE.Color(0x4a4036);
const INSTANCE_MIN = 24; // a mapmodel slot placed this often is drawn instanced (addMapModels)
const TEXTURESCALE = 32; // rendercubes.cpp: one texture repeat covers 32/scale... pixels per cube
// Relative to this module, not the page, so other pages (the flag run on /run) can use the viewer too.
const TEXTURE_BASE = new URL("textures/", import.meta.url).href; // mirrors packages/textures/ for the textures the bundled maps use
const MODEL_BASE = new URL("models/", import.meta.url).href;     // mirrors packages/models/mapmodels/ for the models the bundled maps use (+ models.json)

export class Viewer {
  constructor(canvas) {
    this.canvas = canvas;
    this.renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color(0x12151c);
    this.camera = new THREE.PerspectiveCamera(50, 1, 0.5, 4000);
    this.controls = new OrbitControls(this.camera, canvas);
    this.controls.enableDamping = true;
    this.controls.maxPolarAngle = Math.PI / 2 - 0.02;

    const sun = new THREE.DirectionalLight(0xffffff, 1.6);
    sun.position.set(1, 2, 1.2);
    this.scene.add(sun, new THREE.AmbientLight(0xffffff, 1.4));

    this.mapGroup = new THREE.Group();
    this.entGroup = new THREE.Group();
    this.modelGroup = new THREE.Group();
    this.pathGroup = new THREE.Group();
    this.userGroup = new THREE.Group(); // route from a clicked point + its marker
    this.displayGroup = new THREE.Group(); // live panels (displays.js: cl_cube's hall of fame)
    this.scene.add(this.mapGroup, this.entGroup, this.modelGroup, this.pathGroup, this.userGroup, this.displayGroup);
    this.raycaster = new THREE.Raycaster();
    this.manifest = null; // models.json, fetched once
    this.lineMaterials = [];
    this.materials = [];
    this.textured = true;
    this.loader = new THREE.TextureLoader();
    this.textureCache = new Map(); // url -> Promise<Texture|null>

    window.addEventListener("resize", () => this.resize());
    this.resize();
    this.walker = null; // set while the first-person walk mode (walk.js) is active
    // HUD layer (the gun in the hand): its own scene, drawn after the world with a cleared depth buffer,
    // so it never sinks into walls (AC draws the HUD gun the same way, in front of everything)
    this.hudScene = new THREE.Scene();
    const hudSun = new THREE.DirectionalLight(0xffffff, 1.2);
    hudSun.position.set(0.3, 1, 0.6);
    this.hudAmbient = new THREE.AmbientLight(0xffffff, 1.6);
    this.hudSun = hudSun;
    this.hudScene.add(this.hudAmbient, hudSun);
    this.hudCamera = new THREE.PerspectiveCamera(50, 1, 0.01, 50);
    this.onFrame = null; // per-frame hook after the camera moved: (dt) => void
    this.clock = new THREE.Clock();
    this.renderer.setAnimationLoop(() => {
      const dt = this.clock.getDelta();
      if (this.walker) this.walker.update(dt); else this.controls.update();
      if (this.sky) this.sky.position.copy(this.camera.position);
      this.onFrame?.(dt);
      if (this.caveLight?.active) {
        this.caveLight.update(this.clock.elapsedTime, this.textured);
        this.stars?.update(this.clock.elapsedTime, this.camera, this.renderer.domElement.height);
        // the gun in the hand takes the cave's light where the player stands
        const c = this.camera.position, L = this.walker ? this.caveLight.at(c.x, c.z, c.y) : null;
        const k = L ? Math.min(1, (L[0] + L[1] + L[2]) / 3) : 1;
        this.hudAmbient.intensity = 1.6 * k; this.hudSun.intensity = 1.2 * k;
        const m = L ? Math.max(L[0], L[1], L[2]) || 1 : 1;     // and its colour, half way to white
        this.hudAmbient.color.setRGB(L ? 0.5 + 0.5 * L[0] / m : 1, L ? 0.5 + 0.5 * L[1] / m : 1, L ? 0.5 + 0.5 * L[2] / m : 1);
      }
      this.renderer.render(this.scene, this.camera);
      if (this.hudScene.children.length > 2) {
        this.hudCamera.fov = this.camera.fov; this.hudCamera.aspect = this.camera.aspect; this.hudCamera.updateProjectionMatrix();
        this.renderer.autoClear = false;
        this.renderer.clearDepth();
        this.renderer.render(this.hudScene, this.hudCamera);
        this.renderer.autoClear = true;
      }
      // frames per second and the slowest frame, every half second (onStats: UI hook)
      this.statFrames = (this.statFrames ?? 0) + 1;
      this.statWorst = Math.max(this.statWorst ?? 0, dt);
      this.statTime = (this.statTime ?? 0) + dt;
      if (this.statTime >= 0.5) {
        this.onStats?.({ fps: this.statFrames / this.statTime, worstMs: this.statWorst * 1000, calls: this.renderer.info.render.calls });
        this.statFrames = 0; this.statWorst = 0; this.statTime = 0;
      }
    });
  }

  resize() {
    const w = this.canvas.clientWidth || window.innerWidth, h = this.canvas.clientHeight || window.innerHeight;
    this.renderer.setSize(w, h, false);
    this.camera.aspect = w / h;
    this.camera.updateProjectionMatrix();
    for (const m of this.lineMaterials) m.resolution.set(w, h);
  }

  clear(group) {
    while (group.children.length) {
      const c = group.children.pop();
      c.geometry?.dispose();
      if (Array.isArray(c.material)) c.material.forEach((m) => m.dispose());
      else c.material?.dispose?.();
    }
  }

  // ---- geometry -----------------------------------------------------------

  setMap(map) {
    this.map = map;
    this.clear(this.mapGroup); this.clear(this.entGroup); this.clear(this.pathGroup);
    this.clearModels();
    const t0 = performance.now();
    const built = buildGeometry(map);
    // dark zones (cl_lost's cave): LIGHT entities light the cave's floors and walls (light.js)
    this.caveLight = new CaveLight(map);
    this.caveLight.lightGeometry(built.floor);
    this.caveLight.lightGeometry(built.walls);
    this.caveLight.lightGeometry(built.roofs);     // ceilings (cl_cube's rooms have real ones)
    this.caveLight.lightGeometry(built.ceilings);
    this.floorGeo = built.floor;
    this.wallGeo = built.walls;
    this.ceilGeo = built.ceilings;
    this.roofGeo = built.roofs;
    this.floorVertexStart = built.floorVertexStart;
    this.bounds = built.bounds;
    this.materials = built.slots.map(() => new THREE.MeshLambertMaterial({ color: 0xffffff, vertexColors: true }));
    // Overhead ceilings (bridges, lintels, beams, tunnel roofs) are translucent so what runs underneath stays visible.
    this.ceilMaterials = built.slots.map(() => new THREE.MeshLambertMaterial({ color: 0xffffff, vertexColors: true, transparent: true, opacity: 0.55, depthWrite: false }));
    const ceilMesh = new THREE.Mesh(built.ceilings, this.ceilMaterials);
    ceilMesh.renderOrder = 1;
    this.ceilMesh = ceilMesh;
    this.roofMesh = new THREE.Mesh(built.roofs, this.materials);
    this.mapGroup.add(new THREE.Mesh(built.floor, this.materials), new THREE.Mesh(built.walls, this.materials), ceilMesh, this.roofMesh);
    this.terrainGeos = built.terrain;
    for (const g of built.terrain) this.mapGroup.add(new THREE.Mesh(g, this.materials));
    this.addWater(map);
    this.setWalkView(this.walkView ?? false);
    this.setSky(map);
    console.log(`[viewer] geometry: ${built.floor.attributes.position.count + built.walls.attributes.position.count + built.ceilings.attributes.position.count} vertices, ${built.slots.length} texture slots in ${(performance.now() - t0).toFixed(0)} ms`);
    this.textureStats = { slots: built.slots.length, loaded: 0, missing: 0 };
    this.loadTextures(map, built.slots);
    this.currentDist = null; // a previous map's distance field no longer fits
    this.applyColors();
    this.addEntities(map);
    if (this.gameView) this.setGameView(true);
    this.modelsReady = this.addMapModels(map);
    for (const c of this.displayGroup.children) c.material?.map?.dispose();
    this.clear(this.displayGroup);
    new Displays(this.displayGroup).build(map);
    this.stars = new Stars(this.displayGroup);   // cl_cube's star chamber (stardome)
    this.stars.build(map);
    this.frame();
  }

  /**
   * The water (the map header's waterlevel, in tenths of a cube, and watercolour): AC fills every
   * cube below it. A translucent plane over the whole map, when any floor lies below it.
   */
  addWater(map) {
    const level = map.waterlevel / 10;
    if (!(level > this.bounds.minH)) return;
    const [r, g, b, a] = map.watercolor ?? [20, 25, 20, 150];
    // an island map (big) gets its sea out to the horizon; a normal map's water ends with the map
    const span = map.size >= 512 ? map.size * 12 : map.size;
    const geo = new THREE.PlaneGeometry(span, span);
    geo.rotateX(-Math.PI / 2);
    const mat = new THREE.MeshLambertMaterial({ color: new THREE.Color(r / 255, g / 255, b / 255).convertSRGBToLinear(), transparent: true, opacity: map.size >= 512 ? 0.78 : Math.max(0.35, a / 255), depthWrite: false });
    const water = new THREE.Mesh(geo, mat);
    water.position.set(map.size / 2, level, map.size / 2);
    water.renderOrder = 2;
    this.mapGroup.add(water);
    if (map.size >= 512) {
      // beyond the map there is no sea floor: a dark one at the depth of the map's edge, so the open sea
      // looks like the water over the map (otherwise the sky shows through it)
      const floorGeo = new THREE.PlaneGeometry(span, span);
      floorGeo.rotateX(-Math.PI / 2);
      const seabed = new THREE.Mesh(floorGeo, new THREE.MeshLambertMaterial({ color: new THREE.Color(0x3a3020).convertSRGBToLinear() })); // dark mud, as cl_lost's deep sea floor
      seabed.position.set(map.size / 2, this.bounds.minH - 1, map.size / 2); // a cube below: no flicker against the map's own sea floor
      this.mapGroup.add(seabed);
    }
  }

  /**
   * Game view (the flag run): the map as the player sees it in the game, without
   * the viewer's helpers: no spawn markers, labels, routes or click marker,
   * textures and objects always on.
   */
  setGameView(on) {
    this.gameView = on;
    this.entGroup.visible = this.pathGroup.visible = this.userGroup.visible = !on;
    if (on) { this.setTextured(true); this.setModelsVisible(true); if (this.floorGeo) this.colorFloor(null); }
  }

  /** Walk mode: opaque ceilings everywhere, as in the game. Overview: open rooms, translucent bridges only. */
  setWalkView(on) {
    this.walkView = on;
    if (this.roofMesh) this.roofMesh.visible = on;
    if (this.ceilMesh) this.ceilMesh.visible = !on;
  }

  /**
   * The map's sky box (loadsky in the map config), as AC draws it
   * (texture.cpp draw_envbox): six faces around the camera, drawn behind
   * everything. The face table below is AC's, corner by corner: [s, t, x, y, z]
   * in AC space. AC's envbox height axis points the other way from the world's
   * (its "dn" face is drawn at +z), so it maps to three as (x, -z, y);
   * checked against screenshots: ground below, clouds above.
   */
  setSky(map) {
    if (this.sky) { this.scene.remove(this.sky); this.sky.traverse((o) => { if (o.isMesh) { o.geometry.dispose(); o.material.dispose(); } }); this.sky = null; }
    const name = (map.config?.sky ?? "").replace(/^textures\/skymaps\//, "");
    if (!name) return;
    const w = 1000;
    const faces = [ // lf, rt, ft, bk, dn, up (AC's order and corners)
      ["lf", [[0, 0, -w, -w, -w], [1, 0, -w, w, -w], [1, 1, -w, w, w], [0, 1, -w, -w, w]]],
      ["rt", [[1, 1, w, -w, w], [0, 1, w, w, w], [0, 0, w, w, -w], [1, 0, w, -w, -w]]],
      ["ft", [[1, 1, -w, -w, w], [0, 1, w, -w, w], [0, 0, w, -w, -w], [1, 0, -w, -w, -w]]],
      ["bk", [[1, 1, w, w, w], [0, 1, -w, w, w], [0, 0, -w, w, -w], [1, 0, w, w, -w]]],
      ["dn", [[0, 1, -w, w, w], [0, 0, w, w, w], [1, 0, w, -w, w], [1, 1, -w, -w, w]]],
      ["up", [[0, 1, w, w, -w], [0, 0, -w, w, -w], [1, 0, -w, -w, -w], [1, 1, w, -w, -w]]],
    ];
    const group = new THREE.Group();
    group.renderOrder = -1;
    for (const [side, c] of faces) {
      const pos = [], uv = [];
      for (const k of [0, 1, 2, 0, 2, 3]) { const [s, t, x, y, z] = c[k]; pos.push(x, -z, y); uv.push(s, t); }
      const geo = new THREE.BufferGeometry();
      geo.setAttribute("position", new THREE.Float32BufferAttribute(pos, 3));
      geo.setAttribute("uv", new THREE.Float32BufferAttribute(uv, 2));
      const mat = new THREE.MeshBasicMaterial({ color: 0xffffff, side: THREE.DoubleSide, depthWrite: false, depthTest: false, fog: false });
      this.loader.load(`${TEXTURE_BASE}skymaps/${name}_${side}.jpg`, (t) => {
        t.flipY = false; // GL texture coordinates as AC uses them: t = 0 is the image's first row
        t.colorSpace = THREE.SRGBColorSpace;
        t.wrapS = t.wrapT = THREE.ClampToEdgeWrapping;
        t.needsUpdate = true;
        mat.map = t; mat.needsUpdate = true;
      }, undefined, () => console.warn(`[viewer] sky missing: ${name}_${side}`));
      const mesh = new THREE.Mesh(geo, mat);
      mesh.renderOrder = -1;
      mesh.frustumCulled = false;
      group.add(mesh);
    }
    this.sky = group;
    this.scene.add(group);
  }

  /**
   * Upload every loaded texture and compile every shader up front. three.js
   * otherwise does both the first time an object comes into view, which shows
   * as a hitch when you turn around in walk mode.
   */
  async warmUp() {
    const map = this.map;
    const textures = (await Promise.all(this.textureCache.values())).filter(Boolean);
    if (this.map !== map) return;
    for (const m of [...this.materials, ...this.ceilMaterials]) if (m.userData.texture) textures.push(m.userData.texture);
    for (const t of textures) this.renderer.initTexture(t);
    await this.renderer.compileAsync(this.scene, this.camera);
    console.log(`[viewer] warm-up: ${textures.length} textures uploaded, shaders compiled`);
  }

  // ---- mapmodels ------------------------------------------------------------

  clearModels() {
    while (this.modelGroup.children.length) {
      const g = this.modelGroup.children.pop();
      g.traverse((o) => { if (o.isMesh) o.material.dispose(); }); // geometries are shared via the md3 cache
    }
  }

  /** Base (unrepeated) texture for a file under textures/ or models/, cached. */
  baseTexture(url) {
    if (!this.textureCache.has(url)) {
      this.textureCache.set(url, new Promise((resolve) => this.loader.load(url, (t) => {
        if (/\.png$/i.test(url)) t = bleedAlpha(t);
        t.wrapS = t.wrapT = THREE.RepeatWrapping;
        t.colorSpace = THREE.SRGBColorSpace;
        t.anisotropy = this.renderer.capabilities.getMaxAnisotropy();
        resolve(t);
      }, undefined, () => { console.warn(`[viewer] texture missing: ${url}`); resolve(null); })));
    }
    return this.textureCache.get(url);
  }

  /**
   * Places every MAPMODEL entity (entities.cpp renderentities): position
   * (x, y, cube floor + zoff + attr3/5), yaw attr1/10, optional world texture
   * attr4 replacing the skin, uniform scale from the mapmodel slot.
   */
  async addMapModels(map) {
    const stats = { placed: 0, skipped: 0 };
    const slots = map.config?.mapmodels ?? [];
    const ents = map.ents.filter((e) => e.type === ENT.MAPMODEL);
    if (!ents.length) return stats;
    if (!this.manifest) {
      this.manifest = await fetch(MODEL_BASE + "models.json").then((r) => (r.ok ? r.json() : {})).catch(() => ({}));
    }
    // One set of materials per slot (and world texture); a slot used INSTANCE_MIN+ times (a jungle's trees)
    // is drawn as instanced meshes, one per surface per TILE x TILE tile so the camera skips tiles out of view.
    const bySlot = new Map();
    for (const e of ents) {
      const key = `${e.attr2}:${e.attr4 > 0 ? e.attr4 : 0}`;
      if (!bySlot.has(key)) bySlot.set(key, []);
      bySlot.get(key).push(e);
    }
    const yaw = (e) => THREE.MathUtils.degToRad(180 - e.attr1 / 10);
    const m4 = new THREE.Matrix4(), q = new THREE.Quaternion(), up = new THREE.Vector3(0, 1, 0), pos = new THREE.Vector3(), scl = new THREE.Vector3();
    for (let list of bySlot.values()) {
      if (this.map !== map) return stats;
      const e0 = list[0], slot = slots[e0.attr2], info = slot && this.manifest[slot.name];
      if (!info) { stats.skipped += list.length; continue; }
      const worldTex = e0.attr4 > 0 && map.textures[e0.attr4] ? TEXTURE_BASE + map.textures[e0.attr4].name : null;
      const parts = [];
      try {
        for (const part of info.parts) {
          const surfaces = await loadMd3(MODEL_BASE + part.md3, info.trans, info.scale);
          for (const sf of surfaces) {
            const skin = worldTex ?? (part.skins[sf.name] ?? part.skins["*"] ? MODEL_BASE + (part.skins[sf.name] ?? part.skins["*"]) : null);
            const mat = new THREE.MeshLambertMaterial({ color: 0xffffff, side: THREE.DoubleSide, alphaTest: 0.5 });
            const dark = mat.clone(); dark.vertexColors = true;          // the same, lit per vertex in a cave (light.js)
            if (skin) this.baseTexture(skin).then((t) => { for (const m of [mat, dark]) { if (t) { m.map = t; m.needsUpdate = true; } else m.color.setHex(0x9a8c7a); } });
            else { mat.color.setHex(0x9a8c7a); dark.color.setHex(0x9a8c7a); }
            parts.push({ geometry: sf.geometry, mat, dark });
          }
        }
      } catch (err) { console.warn(`[viewer] model ${slot.name}: ${err.message}`); stats.skipped += list.length; continue; }
      if (this.map !== map) return stats;
      // Placement (entities.cpp renderentities): cube floor + zoff + attr3/5; yaw calibrated on the ladder
      // (ac-maps, checked in game: rungs east at yaw 0, north at 2700) and the cl_angkorwat plank: the
      // viewer's x/z mapping mirrors handedness, so the angle runs backwards.
      const place = (e) => {
        const z = entityBase(map, (e.y << map.sfactor) + e.x) + slot.zoff + e.attr3 / 5; // ENTSCALE5
        pos.set(e.x, z, e.y); q.setFromAxisAngle(up, yaw(e)); scl.setScalar(slot.scale || 1);
        return m4.compose(pos, q, scl);
      };
      const cave = this.caveLight?.active ? this.caveLight : null;
      const inCave = (e) => cave?.isDark((e.y << map.sfactor) + e.x);
      // models in a cave are drawn one by one with their own lit copy of the geometry (never instanced)
      for (const e of list.length < INSTANCE_MIN ? list : list.filter(inCave)) {
        const group = new THREE.Group();
        const mtx = place(e).clone();
        mtx.decompose(group.position, group.quaternion, group.scale);
        for (const pt of parts) {
          const lit = inCave(e) ? cave.lightModel(pt.geometry, mtx, (e.y << map.sfactor) + e.x) : null;
          group.add(lit ? new THREE.Mesh(lit, pt.dark) : new THREE.Mesh(pt.geometry, pt.mat));
        }
        this.modelGroup.add(group);
        stats.placed++;
      }
      if (list.length < INSTANCE_MIN) continue;
      list = list.filter((e) => !inCave(e));
      const tiles = new Map();
      for (const e of list) {
        const k = (e.y >> 7) * 64 + (e.x >> 7);
        if (!tiles.has(k)) tiles.set(k, []);
        tiles.get(k).push(e);
      }
      for (const tl of tiles.values()) {
        const group = new THREE.Group();
        for (const pt of parts) {
          const im = new THREE.InstancedMesh(pt.geometry, pt.mat, tl.length);
          tl.forEach((e, k) => im.setMatrixAt(k, place(e)));
          im.instanceMatrix.needsUpdate = true;
          im.computeBoundingSphere();
          group.add(im);
        }
        this.modelGroup.add(group);
      }
      stats.placed += list.length;
    }
    console.log(`[viewer] mapmodels: ${stats.placed} placed, ${stats.skipped} skipped`);
    return stats;
  }

  setModelsVisible(on) { this.modelGroup.visible = on; }

  /** Resolve the texture image for each used slot and attach it to that slot's material. */
  loadTextures(map, slots) {
    const defs = map.textures ?? [];
    const maxAniso = this.renderer.capabilities.getMaxAnisotropy();
    this.texturesReady = Promise.all(slots.map((slot, mi) => {
      const def = defs[slot];
      const mat = this.materials[mi];
      if (!def) { this.textureStats.missing++; return null; }
      const url = TEXTURE_BASE + def.name;
      if (!this.textureCache.has(url)) {
        this.textureCache.set(url, new Promise((resolve) => this.loader.load(url, (t) => {
          t.wrapS = t.wrapT = THREE.RepeatWrapping;
          t.colorSpace = THREE.SRGBColorSpace;
          t.anisotropy = maxAniso;
          resolve(t);
        }, undefined, () => { console.warn(`[viewer] texture missing: ${url}`); resolve(null); })));
      }
      return this.textureCache.get(url).then((base) => {
        if (mat !== this.materials[mi]) return; // map changed meanwhile
        if (!base) { this.textureStats.missing++; return; }
        // Same image, per-slot repeat (the slot's scale can differ), so clone cheaply.
        const t = base.clone();
        t.repeat.set(TEXTURESCALE * def.scale / base.image.width, TEXTURESCALE * def.scale / base.image.height);
        t.needsUpdate = true;
        mat.userData.texture = t;
        const cm = this.ceilMaterials[mi];
        cm.userData.texture = t;
        if (this.textured) { mat.map = t; mat.needsUpdate = true; cm.map = t; cm.needsUpdate = true; }
        this.textureStats.loaded++;
      });
    })).then(() => this.textureStats);
  }

  /** Switch between textured faces (white vertex colours) and the plain height shading. */
  setTextured(on) {
    this.textured = on;
    for (const m of [...this.materials, ...(this.ceilMaterials ?? [])]) {
      m.map = on ? m.userData.texture ?? null : null;
      m.needsUpdate = true;
    }
    this.applyColors();
  }

  applyColors() {
    for (const geo of [this.floorGeo, this.wallGeo, this.ceilGeo, this.roofGeo, ...(this.terrainGeos ?? [])]) {
      if (!geo) continue;
      const col = geo.attributes.color;
      col.array.set(this.textured ? geo.userData.texTint : geo.userData.shadeColor);
      col.clearUpdateRanges(); col.needsUpdate = true; // a full upload (light.js uploads parts)
    }
    if (this.currentDist) this.colorFloor(this.currentDist);
  }

  frame() {
    const b = this.bounds;
    const cx = (b.minX + b.maxX) / 2, cz = (b.minY + b.maxY) / 2, span = Math.max(b.maxX - b.minX, b.maxY - b.minY);
    this.controls.target.set(cx, b.minH, cz);
    this.camera.position.set(cx, b.minH + span * 0.85, cz + span * 0.85);
    this.controls.update();
  }

  addEntities(map) {
    const sphere = new THREE.SphereGeometry(1.4, 20, 14);
    const cone = new THREE.ConeGeometry(0.8, 2.4, 12);
    const flagPole = new THREE.CylinderGeometry(0.12, 0.12, 6, 8);
    for (const e of map.ents) {
      if (e.type !== ENT.PLAYERSTART && e.type !== ENT.CTF_FLAG) continue;
      if (e.team !== TEAM.CLA && e.team !== TEAM.RVSF) continue; // skip neutral (team 100) spawns and odd flags
      const idx = (e.y << map.sfactor) + e.x;
      const h = floorAt(map, idx);
      const color = TEAM_COLOR[e.team] ?? 0xcccccc;
      const mat = new THREE.MeshLambertMaterial({ color, emissive: color, emissiveIntensity: 0.25 });
      if (e.type === ENT.CTF_FLAG) {
        const pole = new THREE.Mesh(flagPole, new THREE.MeshLambertMaterial({ color: 0xeeeeee }));
        pole.position.set(e.x + 0.5, h + 3, e.y + 0.5);
        const ball = new THREE.Mesh(sphere, mat);
        ball.position.set(e.x + 0.5, h + 6.5, e.y + 0.5);
        this.entGroup.add(pole, ball, label(e.team === TEAM.CLA ? "CLA flag" : e.team === TEAM.RVSF ? "RVSF flag" : "flag", color, e.x + 0.5, h + 9.5, e.y + 0.5));
      } else {
        const c = new THREE.Mesh(cone, mat);
        c.position.set(e.x + 0.5, h + 1.2, e.y + 0.5);
        c.rotation.y = -THREE.MathUtils.degToRad(e.attr1);
        this.entGroup.add(c);
      }
    }
  }

  // ---- paths ----------------------------------------------------------------

  clearPaths() { this.clear(this.pathGroup); this.lineMaterials = this.lineMaterials.filter((m) => m.userData.user); }

  clearUserPath() { this.clear(this.userGroup); this.lineMaterials = this.lineMaterials.filter((m) => !m.userData.user); }

  /** Cube index under a canvas pixel (floor surfaces only), or -1. */
  pickCell(clientX, clientY) {
    const r = this.canvas.getBoundingClientRect();
    this.raycaster.setFromCamera(new THREE.Vector2(((clientX - r.left) / r.width) * 2 - 1, -((clientY - r.top) / r.height) * 2 + 1), this.camera);
    const hit = this.raycaster.intersectObjects(this.mapGroup.children.filter((m) => m.geometry === this.floorGeo || this.terrainGeos?.includes(m.geometry)), false)[0];
    if (!hit) return -1;
    const x = Math.floor(hit.point.x), y = Math.floor(hit.point.z);
    if (x < 0 || y < 0 || x >= this.map.size || y >= this.map.size) return -1;
    return (y << this.map.sfactor) + x;
  }

  /** Marker at a cube plus (optionally) a route from it; replaces the previous one. */
  setUserPath(cell, cells, color, heights = null, markerHeight = null) {
    this.clearUserPath();
    const map = this.map, x = cell & (map.size - 1), y = cell >> map.sfactor, h = markerHeight ?? floorAt(map, cell);
    const ring = new THREE.Mesh(new THREE.TorusGeometry(0.9, 0.18, 10, 24), new THREE.MeshBasicMaterial({ color }));
    ring.rotation.x = Math.PI / 2;
    ring.position.set(x + 0.5, h + 0.3, y + 0.5);
    const pin = new THREE.Mesh(new THREE.ConeGeometry(0.6, 2, 12), new THREE.MeshLambertMaterial({ color, emissive: color, emissiveIntensity: 0.3 }));
    pin.rotation.x = Math.PI;
    pin.position.set(x + 0.5, h + 2.2, y + 0.5);
    this.userGroup.add(ring, pin);
    if (cells) this.addPath(cells, color, this.userGroup, true, heights);
  }

  /** cells: cube indices; heights (optional): the surface height per step, e.g. on top of a clipped platform. */
  addPath(cells, color = 0xffffff, group = this.pathGroup, user = false, heights = null) {
    const map = this.map, pts = [];
    cells.forEach((i, k) => {
      const x = i & (map.size - 1), y = i >> map.sfactor;
      pts.push(x + 0.5, (heights ? heights[k] : floorAt(map, i)) + 0.6, y + 0.5);
    });
    const geo = new LineGeometry();
    geo.setPositions(pts);
    // Solid line where visible, plus a faint ghost that ignores depth so the
    // route stays readable where it runs under bridges or behind walls.
    const mat = new LineMaterial({ color, linewidth: 5, transparent: true, opacity: 0.95 });
    const ghost = new LineMaterial({ color, linewidth: 3, transparent: true, opacity: 0.3, depthTest: false, depthWrite: false });
    for (const m of [mat, ghost]) {
      m.resolution.set(this.canvas.clientWidth, this.canvas.clientHeight);
      m.userData.user = user;
      this.lineMaterials.push(m);
      const line = new Line2(geo, m);
      line.computeLineDistances();
      line.renderOrder = m === ghost ? 10 : 0;
      group.add(line);
    }
  }

  // ---- distance field --------------------------------------------------------

  /** dist: array per cube (Infinity = unreachable) or null to restore the base colours. */
  colorFloor(dist) {
    this.currentDist = dist;
    const col = this.floorGeo.attributes.color;
    if (!dist) {
      col.array.set(this.textured ? this.floorGeo.userData.texTint : this.floorGeo.userData.shadeColor);
      col.clearUpdateRanges(); col.needsUpdate = true;
      return;
    }
    let max = 0;
    for (let i = 0; i < dist.length; i++) if (isFinite(dist[i]) && dist[i] > max) max = dist[i];
    const c = new THREE.Color();
    for (let i = 0; i < dist.length; i++) {
      const start = this.floorVertexStart[i];
      if (start < 0) continue;
      if (!isFinite(dist[i])) c.setHex(0x3a3a3f);
      else c.setHSL(0.66 - 0.66 * Math.min(1, dist[i] / max), 0.85, this.textured ? 0.6 : 0.5);
      for (let v = 0; v < 6; v++) { col.setXYZ(start + v, c.r, c.g, c.b); }
    }
    col.clearUpdateRanges(); col.needsUpdate = true;
  }
}

/**
 * A model skin with alpha (leaves, grass): its see-through pixels are often white, and the mipmaps blend that
 * white into the leaves, so a tree far away turns pale grey. Give every see-through pixel the average colour
 * of the opaque ones (the alpha stays), so the blend stays green.
 */
function bleedAlpha(tex) {
  const img = tex.image;
  if (!img?.width) return tex;
  const cv = document.createElement("canvas");
  cv.width = img.width; cv.height = img.height;
  const ctx = cv.getContext("2d", { willReadFrequently: true });
  ctx.drawImage(img, 0, 0);
  const data = ctx.getImageData(0, 0, cv.width, cv.height), px = data.data;
  let r = 0, g = 0, b = 0, n = 0;
  for (let i = 0; i < px.length; i += 4) if (px[i + 3] > 127) { r += px[i]; g += px[i + 1]; b += px[i + 2]; n++; }
  if (!n || n * 4 === px.length) return tex; // fully clear or fully opaque: nothing to do
  r /= n; g /= n; b /= n;
  for (let i = 0; i < px.length; i += 4) if (px[i + 3] <= 127) { px[i] = r; px[i + 1] = g; px[i + 2] = b; }
  ctx.putImageData(data, 0, 0);
  const out = new THREE.CanvasTexture(cv);
  out.flipY = tex.flipY;
  tex.dispose();
  return out;
}

function label(text, color, x, y, z) {
  const cv = document.createElement("canvas");
  cv.width = 256; cv.height = 64;
  const ctx = cv.getContext("2d");
  ctx.font = "bold 36px system-ui, sans-serif";
  ctx.textAlign = "center"; ctx.textBaseline = "middle";
  ctx.fillStyle = "#" + color.toString(16).padStart(6, "0");
  ctx.fillText(text, 128, 32);
  const tex = new THREE.CanvasTexture(cv);
  const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, depthTest: false }));
  sp.scale.set(16, 4, 1);
  sp.position.set(x, y, z);
  return sp;
}

// A growable Float32Array (or Int32Array) with push(): the geometry of a 1024 map has millions of
// numbers, which as plain JS arrays cost gigabytes.
class FList {
  constructor(Type = Float32Array) { this.a = new Type(1024); this.n = 0; }
  push() {
    const k = arguments.length;
    if (this.n + k > this.a.length) { const b = new this.a.constructor(Math.max(this.a.length * 2, this.n + k)); b.set(this.a.subarray(0, this.n)); this.a = b; }
    for (let i = 0; i < k; i++) this.a[this.n++] = arguments[i];
  }
  get length() { return this.n; }
  view() { return this.a.subarray(0, this.n); }
}

// Per-texture-slot vertex buckets; concatenated into one geometry with a group per slot.
class Buckets {
  constructor() { this.by = new Map(); }
  get(slot) {
    let b = this.by.get(slot);
    if (!b) { b = { pos: new FList(), norm: new FList(), uv: new FList(), shade: new FList(), tint: new FList(), cells: new FList(Int32Array) }; this.by.set(slot, b); }
    return b;
  }
  /** Builds the geometry; `slotIndex` maps slot -> material index (shared by floor and walls). */
  build(slotIndex, floorVertexStart) {
    let total = 0;
    for (const b of this.by.values()) total += b.pos.length / 3;
    const pos = new Float32Array(total * 3), norm = new Float32Array(total * 3), uv = new Float32Array(total * 2);
    const shade = new Float32Array(total * 3), tint = new Float32Array(total * 3);
    const geo = new THREE.BufferGeometry();
    let start = 0;
    for (const [slot, b] of this.by) {
      const count = b.pos.length / 3;
      if (floorVertexStart) { const cells = b.cells.view(); for (let k = 0; k < cells.length; k++) floorVertexStart[cells[k]] = start + k * 6; }
      geo.addGroup(start, count, slotIndex.get(slot));
      pos.set(b.pos.view(), start * 3); norm.set(b.norm.view(), start * 3); uv.set(b.uv.view(), start * 2); shade.set(b.shade.view(), start * 3);
      const t = b.tint.view();
      for (let k = 0; k < t.length; k++) tint[(start + k) * 3] = tint[(start + k) * 3 + 1] = tint[(start + k) * 3 + 2] = t[k];
      start += count;
    }
    geo.setAttribute("position", new THREE.BufferAttribute(pos, 3));
    geo.setAttribute("normal", new THREE.BufferAttribute(norm, 3));
    geo.setAttribute("uv", new THREE.BufferAttribute(uv, 2));
    geo.setAttribute("color", new THREE.BufferAttribute(tint.slice(), 3));
    geo.userData.shadeColor = shade; // colours for the untextured view
    geo.userData.texTint = tint;     // brightness multipliers for the textured view
    return geo;
  }
}

// Builds two merged BufferGeometries (floor tiles, wall faces) grouped by texture slot.
function buildGeometry(map) {
  const { size, sfactor, cubes } = map;
  const idx = (x, y) => (y << sfactor) + x;
  const solid = (x, y) => x < 0 || y < 0 || x >= size || y >= size || cubes.type[idx(x, y)] === CUBE.SOLID;

  // Height range over open cubes for colouring and framing.
  let minH = 127, maxH = -128, minX = size, maxX = 0, minY = size, maxY = 0;
  for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) {
    const i = idx(x, y);
    if (cubes.type[i] === CUBE.SOLID) continue;
    const f = floorAt(map, i);
    if (f < minH) minH = f; if (cubes.ceil[i] > maxH) maxH = cubes.ceil[i];
    if (x < minX) minX = x; if (x > maxX) maxX = x; if (y < minY) minY = y; if (y > maxY) maxY = y;
  }
  const hRange = Math.max(1, maxH - minH);
  const shade = (lo, hi, h) => lo.clone().lerp(hi, Math.min(1, Math.max(0, (h - minH) / hRange)));

  const floors = new Buckets(), walls = new Buckets(), ceilings = new Buckets(); // ceilings: overhead structures, drawn translucent
  const floorVertexStart = new Int32Array(size * size).fill(-1);
  // Big maps (cl_lost, 1024): heightfield floors share their corner vertices in indexed meshes, a
  // vertex per grid corner instead of six per cube, in TILE x TILE tiles so the camera only draws the
  // tiles in view. Cubes drawn that way have no floorVertexStart (no heatmap).
  const big = size >= 512, TILE = 128, TV = TILE + 1;
  const tiles = new Map(); // tile id -> { vert, pos, uv, shade, idx: Map(slot -> FList) }
  const tCol = new THREE.Color();
  const tileOf = (x, y) => {
    const id = (y >> 7) * (size >> 7) + (x >> 7);
    let t = tiles.get(id);
    if (!t) tiles.set(id, t = { x0: x & ~(TILE - 1), y0: y & ~(TILE - 1), vert: new Int32Array(TV * TV).fill(-1), pos: new FList(), uv: new FList(), shade: new FList(), idx: new Map() });
    return t;
  };
  const tVertex = (t, vx, vy, h) => {
    const k = (vy - t.y0) * TV + (vx - t.x0);
    let v = t.vert[k];
    if (v >= 0 && t.pos.a[v * 3 + 1] === Math.fround(h)) return v;
    v = t.pos.length / 3;
    t.pos.push(vx, h, vy); t.uv.push(vx, vy);
    tCol.copy(shade(FLOOR_LOW, FLOOR_HIGH, h)); t.shade.push(tCol.r, tCol.g, tCol.b);
    if (t.vert[k] < 0) t.vert[k] = v;
    return v;
  };
  const c = new THREE.Color();
  const pushShade = (b, col, n = 6, tint = 1) => { for (let v = 0; v < n; v++) { b.shade.push(col.r, col.g, col.b); b.tint.push(tint); } };

  // Corner height of a floor heightfield: each vertex uses the vdelta of the cube at that corner.
  const cornerFloor = (i, cx, cy) => {
    if (cubes.type[i] !== CUBE.FHF) return cubes.floor[i];
    const j = idx(Math.min(cx, size - 1), Math.min(cy, size - 1));
    return cubes.floor[i] - cubes.vdelta[j] / 4;
  };

  // The four side faces of cube (x, y), each with its outward direction and the
  // normal a wall on that side has (pointing back into the cube).
  // c0/c1 are the grid corners at the two ends of the edge (end 0 = az0/ax0).
  const sidesOf = (x, y) => [
    { dx: 1, dy: 0, nx: -1, nz: 0, ax: x + 1, az0: y, az1: y + 1, c0: [x + 1, y], c1: [x + 1, y + 1] },
    { dx: -1, dy: 0, nx: 1, nz: 0, ax: x, az0: y + 1, az1: y, c0: [x, y + 1], c1: [x, y] },
    { dx: 0, dy: 1, nx: 0, nz: -1, az: y + 1, ax0: x + 1, ax1: x, c0: [x + 1, y + 1], c1: [x, y + 1] },
    { dx: 0, dy: -1, nx: 0, nz: 1, az: y, ax0: x, ax1: x + 1, c0: [x, y], c1: [x + 1, y] },
  ];
  // Heightfield-aware floor/ceiling of cube i at grid corner (cx, cy) as seen
  // from neighbour j (worldrender.cpp render_wall: the vdelta offset applies only
  // when the two cubes are not both heightfields, otherwise they share the slope).
  const vd = (cx, cy) => cubes.vdelta[idx(Math.min(cx, size - 1), Math.min(cy, size - 1))] / 4;
  const floorC = (i, j, [cx, cy]) => cubes.type[i] === CUBE.FHF && cubes.type[j] !== CUBE.FHF ? cubes.floor[i] - vd(cx, cy) : cubes.floor[i];
  const ceilC = (i, j, [cx, cy]) => cubes.type[i] === CUBE.CHF && cubes.type[j] !== CUBE.CHF ? cubes.ceil[i] + vd(cx, cy) : cubes.ceil[i];
  // Vertical quad on side `s` from lo to hi, with separate heights at the two
  // ends (ramps and slopes), UV (distance along the wall, -height) as in render_square.
  const pushWall = (b, s, lo0, lo1, hi0, hi1, col) => {
    if (hi0 <= lo0 && hi1 <= lo1) return;
    lo0 = Math.min(lo0, hi0); lo1 = Math.min(lo1, hi1);
    let p;
    if (s.dx) p = [[s.ax, lo0, s.az0, s.az0, -lo0], [s.ax, lo1, s.az1, s.az1, -lo1], [s.ax, hi1, s.az1, s.az1, -hi1], [s.ax, hi0, s.az0, s.az0, -hi0]];
    else p = [[s.ax0, lo0, s.az, s.ax0, -lo0], [s.ax1, lo1, s.az, s.ax1, -lo1], [s.ax1, hi1, s.az, s.ax1, -hi1], [s.ax0, hi0, s.az, s.ax0, -hi0]];
    const nx = s.nx, nz = s.nz;
    const tri = [p[0], p[1], p[2], p[0], p[2], p[3]];
    // ensure winding faces the normal
    // Winding: triangle (p0, p1, p2) has normal ~ edgeDirection x up = (-ez, 0, ex);
    // flip when that points away from the wanted normal. Independent of the heights,
    // so a quad that is degenerate at one end still winds correctly.
    const ex = s.dx ? 0 : s.ax1 - s.ax0, ez = s.dx ? s.az1 - s.az0 : 0;
    const flip = -ez * nx + ex * nz < 0;
    const order = flip ? [tri[0], tri[2], tri[1], tri[3], tri[5], tri[4]] : tri;
    for (const v of order) { b.pos.push(v[0], v[1], v[2]); b.norm.push(nx, 0, nz); b.uv.push(v[3], v[4]); }
    pushShade(b, col);
  };

  // Caps on solid cubes. AC never draws the top of solid cubes (the ceiling hides
  // it), but seen from above an uncapped solid is a hole. Every solid cube that
  // is enclosed by the map, plus a RIM-cube band of the outer solid mass, gets a
  // top at the ceiling height of the nearest open cube, textured with its wall
  // texture, and vertical faces wherever a neighbouring cap or ceiling is lower.
  const RIM = 5;
  const n = size * size;
  const exterior = new Uint8Array(n); // solid cubes 4-connected to the map border
  {
    const stack = [];
    for (let k = 0; k < size; k++) for (const i of [idx(k, 0), idx(k, size - 1), idx(0, k), idx(size - 1, k)]) {
      if (cubes.type[i] === CUBE.SOLID && !exterior[i]) { exterior[i] = 1; stack.push(i); }
    }
    while (stack.length) {
      const i = stack.pop(), x = i & (size - 1), y = i >> sfactor;
      for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
        const nx = x + dx, ny = y + dy;
        if (nx < 0 || ny < 0 || nx >= size || ny >= size) continue;
        const j = idx(nx, ny);
        if (exterior[j] || cubes.type[j] !== CUBE.SOLID) continue;
        exterior[j] = 1; stack.push(j);
      }
    }
  }
  const capDist = new Int16Array(n).fill(-1), capH = new Float32Array(n); // BFS from open cubes through solids
  {
    let frontier = [];
    for (let i = 0; i < n; i++) if (cubes.type[i] !== CUBE.SOLID) { capDist[i] = 0; capH[i] = cubes.ceil[i]; frontier.push(i); }
    for (let d = 1; frontier.length; d++) {
      const next = [];
      for (const i of frontier) {
        const x = i & (size - 1), y = i >> sfactor;
        for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
          if (!dx && !dy) continue;
          const nx = x + dx, ny = y + dy;
          if (nx < 0 || ny < 0 || nx >= size || ny >= size) continue;
          const j = idx(nx, ny);
          if (cubes.type[j] !== CUBE.SOLID) continue;
          if (capDist[j] < 0) { capDist[j] = d; capH[j] = capH[i]; next.push(j); }
          else if (capDist[j] === d && capH[i] > capH[j]) capH[j] = capH[i]; // highest neighbouring ceiling wins
        }
      }
      frontier = next;
    }
  }
  // Texture slot 0 is AC's sky: faces that carry it are not drawn in the game
  // (the skybox shows through), so solids whose wall texture is 0 border the
  // sky and get neither cap nor sides.
  const SKY = 0;
  const capped = (x, y) => {
    if (x < 0 || y < 0 || x >= size || y >= size) return false;
    const i = idx(x, y);
    return cubes.type[i] === CUBE.SOLID && cubes.wtex[i] !== SKY && capDist[i] > 0 && (!exterior[i] || capDist[i] <= RIM);
  };
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      if (!capped(x, y)) continue;
      const i = idx(x, y), top = capH[i];
      const b = walls.get(cubes.wtex[i]);
      b.pos.push(x, top, y, x, top, y + 1, x + 1, top, y, x + 1, top, y, x, top, y + 1, x + 1, top, y + 1);
      b.uv.push(x, y, x, y + 1, x + 1, y, x + 1, y, x, y + 1, x + 1, y + 1);
      for (let v = 0; v < 6; v++) b.norm.push(0, 1, 0);
      pushShade(b, CAP_COLOR);
      // sides down to a lower neighbouring cap or open ceiling
      for (const s of sidesOf(x, y)) {
        const nx = x + s.dx, ny = y + s.dy;
        if (nx < 0 || ny < 0 || nx >= size || ny >= size) continue;
        const j = idx(nx, ny);
        let lo0, lo1;
        if (capped(nx, ny)) lo0 = lo1 = capH[j];
        else if (cubes.type[j] !== CUBE.SOLID) { lo0 = ceilC(j, i, s.c0); lo1 = ceilC(j, i, s.c1); }
        else continue; // uncapped exterior: the map just ends here
        pushWall(b, s, lo0, lo1, top, top, shade(WALL_LOW, WALL_HIGH, top));
      }
    }
  }

  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const i = idx(x, y);
      if (cubes.type[i] === CUBE.SOLID) continue;
      const f = cubes.floor[i], cl = cubes.ceil[i];
      // floor quad: corners (x,y) (x+1,y) (x,y+1) (x+1,y+1) in AC space
      const h00 = cornerFloor(i, x, y), h10 = cornerFloor(i, x + 1, y), h01 = cornerFloor(i, x, y + 1), h11 = cornerFloor(i, x + 1, y + 1);
      if (big && cubes.type[i] === CUBE.FHF) {
        const t = tileOf(x, y);
        const a = tVertex(t, x, y, h00), b = tVertex(t, x + 1, y, h10), d = tVertex(t, x, y + 1, h01), e = tVertex(t, x + 1, y + 1, h11);
        let list = t.idx.get(cubes.ftex[i]);
        if (!list) t.idx.set(cubes.ftex[i], list = new FList(Int32Array));
        list.push(a, d, b, b, d, e); // the same winding as the quads below
      } else {
      const fb = floors.get(cubes.ftex[i]);
      fb.cells.push(i);
      // three: (x, h, y). Two triangles, counter-clockwise seen from above. UVs in cube units (rendercubes.cpp render_flat).
      fb.pos.push(x, h00, y, x, h01, y + 1, x + 1, h10, y);
      fb.pos.push(x + 1, h10, y, x, h01, y + 1, x + 1, h11, y + 1);
      fb.uv.push(x, y, x, y + 1, x + 1, y, x + 1, y, x, y + 1, x + 1, y + 1);
      // flat normal of the quad (good enough for the gentle FHF slopes)
      const n = new THREE.Vector3(h00 - h10, 1, h00 - h01).normalize();
      for (let v = 0; v < 6; v++) fb.norm.push(n.x, n.y, n.z);
      c.copy(shade(FLOOR_LOW, FLOOR_HIGH, (h00 + h11) / 2));
      if (cubes.type[i] === CUBE.CORNER) c.multiplyScalar(0.85);
      pushShade(fb, c);
      }

      // walls to the four neighbours
      for (const s of sidesOf(x, y)) {
        const nxp = x + s.dx, nyp = y + s.dy;
        if (nxp < 0 || nyp < 0 || nxp >= size || nyp >= size) continue;
        const j = idx(nxp, nyp);
        // Heights at the two ends of the shared edge (render_wall): our floor/ceiling
        // and the neighbour's, each lowered/raised by the heightfield offset at that corner.
        const f0 = floorC(i, j, s.c0), f1 = floorC(i, j, s.c1), c0 = ceilC(i, j, s.c0), c1 = ceilC(i, j, s.c1);
        if (cubes.type[j] === CUBE.SOLID) {
          if (cubes.wtex[j] !== SKY) pushWall(walls.get(cubes.wtex[j]), s, f0, f1, c0, c1, shade(WALL_LOW, WALL_HIGH, cl));
          continue;
        }
        const of0 = floorC(j, i, s.c0), of1 = floorC(j, i, s.c1), oc0 = ceilC(j, i, s.c0), oc1 = ceilC(j, i, s.c1);
        // lower wall: up to the neighbour's higher floor (its wall texture)
        if (cubes.wtex[j] !== SKY) pushWall(walls.get(cubes.wtex[j]), s, f0, f1, Math.min(of0, c0), Math.min(of1, c1), shade(WALL_LOW, WALL_HIGH, cubes.floor[j]));
        // upper wall: from the neighbour's lower ceiling up to ours (its upper texture)
        if (cubes.utex[j] !== SKY) pushWall(walls.get(cubes.utex[j]), s, Math.max(oc0, f0), Math.max(oc1, f1), c0, c1, shade(WALL_LOW, WALL_HIGH, cl));
      }
    }
  }

  // Bridges, arches, lintels and beams. The viewer draws no ceilings so rooms
  // stay open from above, but a bridge over a corridor *is* the corridor's
  // ceiling in the Cube engine. Rule per cube: take the run of "same surface"
  // cubes through it along x and along y (open, not sky, ceiling within 1 unit);
  // if either run is at most SPAN_MAX cubes long, the ceiling is a narrow
  // overhead structure and is drawn, top and underside, with the ceiling
  // texture. Wide ceilings (rooms, halls) are left open.
  const SPAN_MAX = 4;
  const sameSurface = (i, j) => cubes.type[j] !== CUBE.SOLID && cubes.ctex[j] !== SKY && Math.abs(cubes.ceil[j] - cubes.ceil[i]) <= 1;
  const span = (x, y, dx, dy) => { // same-surface run through (x, y) along (dx, dy), capped at SPAN_MAX + 1
    const i = idx(x, y);
    let len = 1;
    for (const dir of [1, -1]) {
      for (let s = 1; len <= SPAN_MAX; s++) {
        const nx = x + dx * dir * s, ny = y + dy * dir * s;
        if (nx < 0 || ny < 0 || nx >= size || ny >= size || !sameSurface(i, idx(nx, ny))) break;
        len++;
      }
    }
    return len;
  };
  const cornerCeil = (i, cx, cy) => cubes.type[i] === CUBE.CHF ? cubes.ceil[i] + vd(cx, cy) : cubes.ceil[i];
  let overhead = 0;
  // Walk mode shows every ceiling as the game does (the underside, opaque);
  // only the overview leaves rooms open from above.
  const roofs = new Buckets();
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const i = idx(x, y);
      if (cubes.type[i] === CUBE.SOLID || cubes.ctex[i] === SKY) continue;
      {
        const r = roofs.get(cubes.ctex[i]);
        const h00 = cornerCeil(i, x, y), h10 = cornerCeil(i, x + 1, y), h01 = cornerCeil(i, x, y + 1), h11 = cornerCeil(i, x + 1, y + 1);
        r.pos.push(x, h00, y, x + 1, h10, y, x, h01, y + 1, x + 1, h10, y, x + 1, h11, y + 1, x, h01, y + 1);
        r.uv.push(x, y, x + 1, y, x, y + 1, x + 1, y, x + 1, y + 1, x, y + 1);
        for (let v = 0; v < 6; v++) r.norm.push(0, -1, 0);
        pushShade(r, shade(WALL_LOW, WALL_HIGH, cubes.ceil[i]));
      }
      if (span(x, y, 1, 0) > SPAN_MAX && span(x, y, 0, 1) > SPAN_MAX) continue;
      overhead++;
      const b = ceilings.get(cubes.ctex[i]);
      const h00 = cornerCeil(i, x, y), h10 = cornerCeil(i, x + 1, y), h01 = cornerCeil(i, x, y + 1), h11 = cornerCeil(i, x + 1, y + 1);
      c.copy(shade(WALL_LOW, WALL_HIGH, cubes.ceil[i]));
      // top (seen from above; the game never shows it, so it is darkened to read as a roof, not a floor) ...
      b.pos.push(x, h00, y, x, h01, y + 1, x + 1, h10, y, x + 1, h10, y, x, h01, y + 1, x + 1, h11, y + 1);
      b.uv.push(x, y, x, y + 1, x + 1, y, x + 1, y, x, y + 1, x + 1, y + 1);
      for (let v = 0; v < 6; v++) b.norm.push(0, 1, 0);
      pushShade(b, c.clone().multiplyScalar(0.7), 6, 0.7);
      // ... and underside (what the game shows)
      b.pos.push(x, h00, y, x + 1, h10, y, x, h01, y + 1, x + 1, h10, y, x + 1, h11, y + 1, x, h01, y + 1);
      b.uv.push(x, y, x + 1, y, x, y + 1, x + 1, y, x + 1, y + 1, x, y + 1);
      for (let v = 0; v < 6; v++) b.norm.push(0, -1, 0);
      pushShade(b, c);
    }
  }
  console.log(`[viewer] overhead ceilings drawn: ${overhead} cubes`);

  // One material per used slot, shared by both geometries.
  const slots = [...new Set([...floors.by.keys(), ...walls.by.keys(), ...ceilings.by.keys(), ...roofs.by.keys(), ...[...tiles.values()].flatMap((t) => [...t.idx.keys()])])].sort((a, b) => a - b);
  const slotIndex = new Map(slots.map((s, k) => [s, k]));
  const floor = floors.build(slotIndex, floorVertexStart);
  const wallGeo = walls.build(slotIndex, null);
  const ceilGeo = ceilings.build(slotIndex, null);
  const roofGeo = roofs.build(slotIndex, null);
  const terrain = [];
  let tVerts = 0, tTris = 0;
  for (const t of tiles.values()) {
    let total = 0;
    for (const l of t.idx.values()) total += l.length;
    const index = new Uint32Array(total), geo = new THREE.BufferGeometry();
    let at = 0;
    for (const [slot, l] of t.idx) { index.set(l.view(), at); geo.addGroup(at, l.length, slotIndex.get(slot)); at += l.length; }
    geo.setIndex(new THREE.BufferAttribute(index, 1));
    geo.setAttribute("position", new THREE.BufferAttribute(t.pos.view().slice(), 3));
    geo.setAttribute("uv", new THREE.BufferAttribute(t.uv.view().slice(), 2));
    const ones = new Float32Array(t.pos.length).fill(1);
    geo.setAttribute("color", new THREE.BufferAttribute(ones.slice(), 3));
    geo.userData.shadeColor = t.shade.view().slice();
    geo.userData.texTint = ones;
    geo.computeVertexNormals();
    geo.computeBoundingSphere();
    terrain.push(geo);
    tVerts += t.pos.length / 3; tTris += total / 3;
  }
  if (terrain.length) console.log(`[viewer] terrain: ${terrain.length} tiles, ${tVerts} vertices, ${tTris} triangles`);

  return { floor, walls: wallGeo, ceilings: ceilGeo, roofs: roofGeo, terrain, slots, floorVertexStart, bounds: { minX, maxX, minY, maxY, minH, maxH } };
}
