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
import { CUBE, ENT, TEAM, floorAt } from "./cgz.js";
import { loadMd3 } from "./md3.js";

const TEAM_COLOR = { [TEAM.CLA]: 0xe0443a, [TEAM.RVSF]: 0x3a7be0 };
const FLOOR_LOW = new THREE.Color(0xb59a6b);
const FLOOR_HIGH = new THREE.Color(0xe6d4ad);
const WALL_LOW = new THREE.Color(0x6f5d48);
const WALL_HIGH = new THREE.Color(0xc2ae8e);
const CAP_COLOR = new THREE.Color(0x4a4036);
const TEXTURESCALE = 32; // rendercubes.cpp: one texture repeat covers 32/scale... pixels per cube
const TEXTURE_BASE = "textures/"; // mirrors packages/textures/ for the textures the bundled maps use
const MODEL_BASE = "models/";     // mirrors packages/models/mapmodels/ for the models the bundled maps use (+ models.json)

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
    this.scene.add(this.mapGroup, this.entGroup, this.modelGroup, this.pathGroup, this.userGroup);
    this.raycaster = new THREE.Raycaster();
    this.manifest = null; // models.json, fetched once
    this.lineMaterials = [];
    this.materials = [];
    this.textured = true;
    this.loader = new THREE.TextureLoader();
    this.textureCache = new Map(); // url -> Promise<Texture|null>

    window.addEventListener("resize", () => this.resize());
    this.resize();
    this.renderer.setAnimationLoop(() => { this.controls.update(); this.renderer.render(this.scene, this.camera); });
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
    this.floorGeo = built.floor;
    this.wallGeo = built.walls;
    this.ceilGeo = built.ceilings;
    this.floorVertexStart = built.floorVertexStart;
    this.bounds = built.bounds;
    this.materials = built.slots.map(() => new THREE.MeshLambertMaterial({ color: 0xffffff, vertexColors: true }));
    // Overhead ceilings (bridges, lintels, beams, tunnel roofs) are translucent so what runs underneath stays visible.
    this.ceilMaterials = built.slots.map(() => new THREE.MeshLambertMaterial({ color: 0xffffff, vertexColors: true, transparent: true, opacity: 0.55, depthWrite: false }));
    const ceilMesh = new THREE.Mesh(built.ceilings, this.ceilMaterials);
    ceilMesh.renderOrder = 1;
    this.mapGroup.add(new THREE.Mesh(built.floor, this.materials), new THREE.Mesh(built.walls, this.materials), ceilMesh);
    console.log(`[viewer] geometry: ${built.floor.attributes.position.count + built.walls.attributes.position.count + built.ceilings.attributes.position.count} vertices, ${built.slots.length} texture slots in ${(performance.now() - t0).toFixed(0)} ms`);
    this.textureStats = { slots: built.slots.length, loaded: 0, missing: 0 };
    this.loadTextures(map, built.slots);
    this.currentDist = null; // a previous map's distance field no longer fits
    this.applyColors();
    this.addEntities(map);
    this.modelsReady = this.addMapModels(map);
    this.frame();
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
    for (const e of ents) {
      if (this.map !== map) return stats;
      const slot = slots[e.attr2], info = slot && this.manifest[slot.name];
      if (!info) { stats.skipped++; continue; }
      const group = new THREE.Group();
      const i = (e.y << map.sfactor) + e.x;
      const z = map.cubes.floor[i] + slot.zoff + e.attr3 / 5; // ENTSCALE5
      group.position.set(e.x, z, e.y);
      // AC rotates the model by (-yaw - 180) about its up axis; the viewer's x/z mapping mirrors handedness.
      group.rotation.y = THREE.MathUtils.degToRad(e.attr1 / 10 + 180);
      group.scale.setScalar(slot.scale || 1);
      const worldTex = e.attr4 > 0 && map.textures[e.attr4] ? TEXTURE_BASE + map.textures[e.attr4].name : null;
      try {
        for (const part of info.parts) {
          const surfaces = await loadMd3(MODEL_BASE + part.md3, info.trans, info.scale);
          for (const s of surfaces) {
            const skin = worldTex ?? (part.skins[s.name] ?? part.skins["*"] ? MODEL_BASE + (part.skins[s.name] ?? part.skins["*"]) : null);
            const mat = new THREE.MeshLambertMaterial({ color: 0xffffff, side: THREE.DoubleSide, alphaTest: 0.5 });
            if (skin) this.baseTexture(skin).then((t) => { if (t) { mat.map = t; mat.needsUpdate = true; } else mat.color.setHex(0x9a8c7a); });
            else mat.color.setHex(0x9a8c7a);
            group.add(new THREE.Mesh(s.geometry, mat));
          }
        }
      } catch (err) { console.warn(`[viewer] model ${slot.name}: ${err.message}`); stats.skipped++; continue; }
      if (this.map !== map) return stats;
      this.modelGroup.add(group);
      stats.placed++;
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
    for (const geo of [this.floorGeo, this.wallGeo, this.ceilGeo]) {
      if (!geo) continue;
      const col = geo.attributes.color;
      col.array.set(this.textured ? geo.userData.texTint : geo.userData.shadeColor);
      col.needsUpdate = true;
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
    const hit = this.raycaster.intersectObject(this.mapGroup.children[0], false)[0];
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
      col.needsUpdate = true;
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
    col.needsUpdate = true;
  }
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

// Per-texture-slot vertex buckets; concatenated into one geometry with a group per slot.
class Buckets {
  constructor() { this.by = new Map(); }
  get(slot) {
    let b = this.by.get(slot);
    if (!b) { b = { pos: [], norm: [], uv: [], shade: [], tint: [], cells: [] }; this.by.set(slot, b); }
    return b;
  }
  /** Builds the geometry; `slotIndex` maps slot -> material index (shared by floor and walls). */
  build(slotIndex, floorVertexStart) {
    const pos = [], norm = [], uv = [], shade = [], tint = [];
    const geo = new THREE.BufferGeometry();
    for (const [slot, b] of this.by) {
      const start = pos.length / 3;
      if (floorVertexStart) for (let k = 0; k < b.cells.length; k++) floorVertexStart[b.cells[k]] = start + k * 6;
      geo.addGroup(start, b.pos.length / 3, slotIndex.get(slot));
      for (const a of b.pos) pos.push(a);
      for (const a of b.norm) norm.push(a);
      for (const a of b.uv) uv.push(a);
      for (const a of b.shade) shade.push(a);
      for (const a of b.tint) tint.push(a, a, a);
    }
    geo.setAttribute("position", new THREE.Float32BufferAttribute(pos, 3));
    geo.setAttribute("normal", new THREE.Float32BufferAttribute(norm, 3));
    geo.setAttribute("uv", new THREE.Float32BufferAttribute(uv, 2));
    geo.setAttribute("color", new THREE.Float32BufferAttribute(tint, 3));
    geo.userData.shadeColor = Float32Array.from(shade); // colours for the untextured view
    geo.userData.texTint = Float32Array.from(tint);     // brightness multipliers for the textured view
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
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const i = idx(x, y);
      if (cubes.type[i] === CUBE.SOLID || cubes.ctex[i] === SKY) continue;
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
  const slots = [...new Set([...floors.by.keys(), ...walls.by.keys(), ...ceilings.by.keys()])].sort((a, b) => a - b);
  const slotIndex = new Map(slots.map((s, k) => [s, k]));
  const floor = floors.build(slotIndex, floorVertexStart);
  const wallGeo = walls.build(slotIndex, null);
  const ceilGeo = ceilings.build(slotIndex, null);

  return { floor, walls: wallGeo, ceilings: ceilGeo, slots, floorVertexStart, bounds: { minX, maxX, minY, maxY, minH, maxH } };
}
