// AssaultCube .cgz map parser (prototype).
// Layout read from the AC 1.3 source: source/src/world.h (header, sqr),
// source/src/worldio.cpp (load_world, rldecodecubes), source/src/tools.cpp
// (fixmapheadersize), source/src/entity.h (entity enum, persistent_entity).

export const CUBE = { SOLID: 0, CORNER: 1, FHF: 2, CHF: 3, SPACE: 4, SEMISOLID: 5 };

// Static entity types, AC 1.3 numbering (map format >= 8).
export const ENT = {
  NOTUSED: 0, LIGHT: 1, PLAYERSTART: 2, I_CLIPS: 3, I_AMMO: 4, I_GRENADE: 5,
  I_HEALTH: 6, I_HELMET: 7, I_ARMOUR: 8, I_AKIMBO: 9, MAPMODEL: 10, CARROT: 11,
  LADDER: 12, CTF_FLAG: 13, SOUND: 14, CLIP: 15, PLCLIP: 16, DUMMYENT: 17,
};
export const ENT_NAMES = Object.fromEntries(Object.entries(ENT).map(([k, v]) => [v, k]));

export const TEAM = { CLA: 0, RVSF: 1 };

// server.h entscale, first four attributes: multipliers that turn old-format
// (< 10) entity attributes into format-10 values (angles x10, clip sizes x5).
const ENTSCALE = {
  [ENT.PLAYERSTART]: [10, 1, 1, 1], [ENT.I_CLIPS]: [10, 1, 1, 1], [ENT.I_AMMO]: [10, 1, 1, 1], [ENT.I_GRENADE]: [10, 1, 1, 1],
  [ENT.I_HEALTH]: [10, 1, 1, 1], [ENT.I_HELMET]: [10, 1, 1, 1], [ENT.I_ARMOUR]: [10, 1, 1, 1], [ENT.I_AKIMBO]: [10, 1, 1, 1],
  [ENT.MAPMODEL]: [10, 1, 5, 1], [ENT.CTF_FLAG]: [10, 1, 1, 1], [ENT.CLIP]: [10, 5, 5, 5], [ENT.PLCLIP]: [10, 5, 5, 5],
};

const SIZEOF_HEADER = 980;          // sizeof(header) in world.h
const SIZEOF_BASEHEADER = 980 - 64; // header minus the 16 trailing ints

function fixMapHeaderSize(version, headersize) {
  if (version < 4) return SIZEOF_BASEHEADER;
  if (version === 7 || version === 8) return SIZEOF_HEADER + 128; // mediareq
  if (version < 10 || headersize < SIZEOF_HEADER) return SIZEOF_HEADER;
  return headersize;
}

function cstr(bytes) {
  const end = bytes.indexOf(0);
  return new TextDecoder("latin1").decode(end < 0 ? bytes : bytes.subarray(0, end));
}

/**
 * Fetch and parse a .cgz. Returns
 * { title, version, sfactor, size, numents, waterlevel,
 *   cubes: { type, floor, ceil, vdelta, tag } as parallel typed arrays (index = (y << sfactor) + x),
 *   ents: [{ type, name, x, y, z, attr1..attr4, team }] }
 */
export async function loadMap(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`fetch ${url}: ${res.status}`);
  const gz = new Uint8Array(await res.arrayBuffer());
  const raw = window.pako.inflate(gz);
  const map = parseMap(raw);
  // worldio.cpp load_world: without the "full embedded config" header flag
  // (MHF_AUTOMAPCONFIG) the game runs config/default_map_settings.cfg and
  // maps/<name>.cfg first, then the embedded config (parseheaderextra) on top.
  // Maps built outside the editor (cl_angkorwat) embed only sky and models
  // and take their textures from the defaults.
  if (!(map.headerFlags & 1)) {
    const dir = url.replace(/[^/]*$/, "");
    const fetchText = async (u) => { const r = await fetch(u); return r.ok ? r.text() : ""; };
    const [defaults, own] = await Promise.all([fetchText(dir + "default_map_settings.cfg"), fetchText(url.replace(/\.cgz$/, ".cfg"))]);
    map.config = parseMapConfig(defaults + "\n" + own + "\n" + map.configText);
    map.textures = map.config.textures;
    map.configSource = [own && "sidecar .cfg", map.configText && "embedded"].filter(Boolean).join(" + ") || "default_map_settings.cfg only";
  } else map.configSource = "embedded (full)";
  return map;
}

export function parseMap(raw) {
  const dv = new DataView(raw.buffer, raw.byteOffset, raw.byteLength);
  const magic = cstr(raw.subarray(0, 4));
  if (magic !== "ACMP" && magic !== "CUBE") throw new Error(`not a cube map (magic "${magic}")`);

  const version = dv.getInt32(4, true);
  let headersize = dv.getInt32(8, true);
  const sfactor = dv.getInt32(12, true);
  const numents = dv.getInt32(16, true);
  const title = cstr(raw.subarray(20, 148)).replace(/\f./g, ""); // strip \f<colour> codes
  if (version > 10) throw new Error(`map version ${version} is newer than this parser`);
  if (sfactor < 6 || sfactor > 11) throw new Error(`illegal sfactor ${sfactor}`);
  headersize = fixMapHeaderSize(version, headersize);

  let waterlevel = -100000;
  if (version >= 4) {
    waterlevel = dv.getInt32(916, true);
    if (version < 10) waterlevel *= 10; // WATERLEVELSCALING
  }
  const watercolor = version >= 10 ? [0, 1, 2, 3].map((k) => raw[SIZEOF_HEADER - 60 + k]) : null; // header watercolor[4], after waterlevel
  const headerFlags = version >= 10 ? dv.getInt32(932, true) : 0; // MHF_* (world.h)

  // Header extras (format >= 9, worldio.cpp unpackheaderextra): a sequence of
  // (varint len, varint flags, data) records between the 980-byte header and
  // `headersize`. Record type HX_CONFIG (5) holds the embedded map config, which
  // in AC 1.3 is where the texture slot list lives (no separate .cfg file).
  const extras = [];
  if (version >= 9 && headersize > SIZEOF_HEADER) {
    let q = SIZEOF_HEADER;
    const getuint = () => { // tools.cpp getuint
      let n = raw[q++];
      if (n & 0x80) {
        n += (raw[q++] << 7) - 0x80;
        if (n & (1 << 14)) n += (raw[q++] << 14) - (1 << 14);
        if (n & (1 << 21)) n += (raw[q++] << 21) - (1 << 21);
        if (n & (1 << 28)) n |= -1 << 28;
      }
      return n;
    };
    while (q < headersize) {
      const len = getuint(), flags = getuint();
      if (len < 0 || flags < 0 || q + len > headersize) break;
      extras.push({ type: flags & 0x3f, persist: !!(flags & 0x40), data: raw.subarray(q, q + len) });
      q += len;
    }
  }
  const cfgRec = extras.find((r) => r.type === 5);
  const configText = cfgRec ? cstr(cfgRec.data) : "";
  const config = parseMapConfig(configText);

  // Entities start right after the (possibly extended) header.
  const oldEnts = version < 10;
  const entSize = oldEnts ? 12 : 16;
  let p = headersize;
  const ents = [];
  for (let i = 0; i < numents; i++) {
    const e = {
      x: dv.getInt16(p, true),
      y: dv.getInt16(p + 2, true),
      z: dv.getInt16(p + 4, true),
      attr1: dv.getInt16(p + 6, true),
      type: raw[p + 8],
      attr2: raw[p + 9],
      attr3: raw[p + 10],
      attr4: raw[p + 11],
      attr5: oldEnts ? 0 : dv.getInt16(p + 12, true),
      attr6: oldEnts ? 0 : dv.getInt8(p + 14),
      attr7: oldEnts ? 0 : raw[p + 15],
    };
    if (version < 8) console.warn("[cgz] map format < 8: entity type numbers are not remapped");
    if (oldEnts) { // formats < 10 store unscaled attributes; bring them to the format-10 scale (worldio.cpp SCALEATTR, table in server.h)
      const sc = ENTSCALE[e.type] ?? [1, 1, 1, 1];
      e.attr1 *= sc[0]; e.attr2 *= sc[1]; e.attr3 *= sc[2]; e.attr4 *= sc[3];
    }
    e.name = ENT_NAMES[e.type] ?? `type${e.type}`;
    e.team = (e.type === ENT.PLAYERSTART || e.type === ENT.CTF_FLAG) ? e.attr2 : null;
    ents.push(e);
    p += entSize;
  }

  // Cube grid, run-length encoded (worldio.cpp rldecodecubes).
  const size = 1 << sfactor;
  const n = size * size;
  const type = new Uint8Array(n);
  const floor = new Int8Array(n);
  const ceil = new Int8Array(n);
  const vdelta = new Uint8Array(n);
  const tag = new Uint8Array(n);
  const wtex = new Uint8Array(n);
  const ftex = new Uint8Array(n);
  const ctex = new Uint8Array(n);
  const utex = new Uint8Array(n); // upper wall texture (used where the neighbour's ceiling is lower)

  const setDefault = (i) => { type[i] = CUBE.SOLID; floor[i] = 0; ceil[i] = 16; vdelta[i] = 0; tag[i] = 0; wtex[i] = 2; ftex[i] = 3; ctex[i] = 4; utex[i] = 2; };
  const copy = (dst, src) => {
    type[dst] = type[src]; floor[dst] = floor[src]; ceil[dst] = ceil[src]; vdelta[dst] = vdelta[src];
    tag[dst] = tag[src]; wtex[dst] = wtex[src]; ftex[dst] = ftex[src]; ctex[dst] = ctex[src]; utex[dst] = utex[src];
  };

  let i = 0;
  let t = -1; // previous cube index
  let truncated = false;
  while (i < n) {
    if (p >= raw.length) { truncated = true; setDefault(i); t = i; i++; continue; }
    const code = raw[p++];
    switch (code) {
      case 255: { // repeat previous cube, count byte follows
        if (t < 0) { truncated = true; i = n; break; }
        const count = raw[p++];
        for (let k = 0; k < count && i < n; k++) { copy(i, t); i++; }
        t = i - 1;
        continue;
      }
      case 254: { // only in format <= 2
        if (t < 0) { truncated = true; i = n; break; }
        copy(i, t); p += 2;
        break;
      }
      case CUBE.SOLID: {
        setDefault(i);
        wtex[i] = raw[p++];
        vdelta[i] = raw[p++];
        if (version <= 2) p += 2;
        break;
      }
      default: {
        let ty = code === 253 ? CUBE.SOLID : code; // 253 = solid with textures (editing)
        if (ty >= 6) { console.warn(`[cgz] cube ${i}: type ${ty} out of range`); truncated = true; i = n; break; }
        setDefault(i);
        type[i] = ty;
        floor[i] = (raw[p++] << 24) >> 24;
        ceil[i] = (raw[p++] << 24) >> 24;
        if (floor[i] >= ceil[i]) floor[i] = ceil[i] - 1;
        wtex[i] = raw[p++];
        ftex[i] = raw[p++];
        ctex[i] = raw[p++];
        if (version <= 2) p += 2;
        vdelta[i] = raw[p++];
        utex[i] = version >= 2 ? raw[p++] : wtex[i];
        if (version >= 5) tag[i] = raw[p++];
      }
    }
    if (i >= n) break;
    t = i;
    i++;
  }
  if (truncated) console.warn("[cgz] cube data ended early or was malformed; remaining cubes are solid");

  return { title, version, sfactor, size, numents, waterlevel, watercolor, headerFlags, configText, headersize, ents, extras, config, textures: config.textures,
    cubes: { type, floor, ceil, vdelta, tag, wtex, ftex, ctex, utex } };
}

/**
 * Minimal reader for the (embedded) map config: only the texture slot list and
 * a few scene settings. Slot i is the i-th `texture <scale> "<path>"` line after
 * the last `texturereset` (texture.cpp); paths are relative to packages/textures.
 * Returns { textures: [{ scale, name }], sky, fogColour }.
 */
export function parseMapConfig(text) {
  const cfg = { textures: [], mapmodels: [], sky: null, fogColour: null, darkzones: [], lidzones: [], stardomes: [], displays: [], mapsounds: [], synths: [], soundzones: [] };
  for (let line of text.split(/\r?\n/)) {
    line = line.replace(/\/\/.*$/, "").trim();
    if (!line) continue;
    const m = line.match(/^(\S+)\s*(.*)$/);
    if (!m) continue;
    const cmd = m[1], args = m[2];
    if (cmd === "texturereset") cfg.textures = [];
    else if (cmd === "texture") {
      const a = args.match(/^([\d.]+)\s+"?([^"]*)"?/);
      if (!a) continue;
      let scale = parseFloat(a[1]);
      if (!(scale > 0 && scale <= 4)) scale = 1; // texture.cpp _texture: out-of-range scale falls back to 1
      const name = a[2].replace(/<[^>]*>/g, "").replace(/^\.\//, ""); // strip <tags> like <decal>
      cfg.textures.push({ scale, name });
    } else if (cmd === "mapmodelreset") cfg.mapmodels = [];
    else if (cmd === "mapmodel") {
      // mapmodel <rad> <h> <zoff> <scale> "<name>" (rendermodel.cpp); slot = position in the list
      const a = args.match(/^(-?\d+)\s+(-?\d+)\s+(-?\d+)\s+(\S+)\s+"?([^"]*)"?/);
      if (!a) continue;
      let scale = parseFloat(a[4]);
      if (!(scale >= 0.25 && scale <= 4)) scale = 1;
      cfg.mapmodels.push({ rad: +a[1], h: +a[2], zoff: +a[3], scale, name: a[5].replace(/^\.\//, "") });
    } else if (cmd === "loadsky") cfg.sky = args.replace(/"/g, "");
    else if (cmd === "fogcolour") cfg.fogColour = parseInt(args, 10);
    else if (cmd === "mapsoundreset") cfg.mapsounds = [];
    else if (cmd === "mapsound") cfg.mapsounds.push(args.replace(/"/g, "").trim().split(/\s+/)[0]); // AC: SOUND entities' attr1 = slot
    else if (cmd === "synth") {
      // synth <kind> <x> <y> <z> <radius> [gain] [min height]: a sound made by the viewer (ambience.js), our own command;
      // with a min height only heard above it (the star chamber, not the maze under it)
      const a = args.trim().split(/\s+/), n = a.slice(1).map(Number);
      if (a.length >= 5 && n.every(Number.isFinite)) cfg.synths.push({ kind: a[0], x: n[0], y: n[1], z: n[2], r: n[3], gain: n[4] ?? 1, zmin: n[5] ?? null });
    } else if (cmd === "soundzone") {
      // soundzone <kind> <x0> <y0> <x1> <y1> <gain> [out] [below:<z>] [above:<z>]: an ambient layer inside (or outside)
      // a rectangle (ambience.js), optionally only below / above a height (cl_cube's maze and the star chamber over it)
      const a = args.trim().split(/\s+/), n = a.slice(1, 6).map(Number), opt = a.slice(6);
      const h = (k) => { const o = opt.find((t) => t.startsWith(k + ":")); return o ? Number(o.slice(k.length + 1)) : null; };
      if (a.length >= 6 && n.every(Number.isFinite)) cfg.soundzones.push({ kind: a[0], x0: n[0], y0: n[1], x1: n[2], y1: n[3], gain: n[4], out: opt.includes("out"), below: h("below"), above: h("above") });
    }
    else if (cmd === "display") {
      // display <id> <x> <y> <z> <facing e|w|n|s> <w> <h>: a panel the viewer draws with live data (displays.js), our own command
      const a = args.trim().split(/\s+/);
      const n = a.slice(1, 4).concat(a.slice(5, 7)).map(Number);
      if (a.length >= 7 && n.every(Number.isFinite) && "ewns".includes(a[4])) cfg.displays.push({ id: a[0], x: n[0], y: n[1], z: n[2], facing: a[4], w: n[3], h: n[4] });
    }
    else if (cmd === "darkzone") {
      // darkzone <x0> <y0> <x1> <y1> <ambient> [<top>]: the SPACE cubes in it are dark, lit by LIGHT entities (light.js);
      // our own command for the browser-only maps (cl_lost), not AC's
      const a = args.trim().split(/\s+/).map(Number);
      if (a.length >= 5 && a.every(Number.isFinite)) cfg.darkzones.push({ x0: a[0], y0: a[1], x1: a[2], y1: a[3], ambient: a[4], top: a[5] });
    }
    else if (cmd === "lidzone") {
      // lidzone <x0> <y0> <x1> <y1> <z>: a stone layer at height z (lid models over cl_cube's maze, the star chamber on
      // top): light from one side of it does not reach the other (light.js)
      const a = args.trim().split(/\s+/).map(Number);
      if (a.length >= 5 && a.every(Number.isFinite)) cfg.lidzones.push({ x0: a[0], y0: a[1], x1: a[2], y1: a[3], z: a[4] });
    }
    else if (cmd === "stardome") {
      // stardome <x> <y> <z> <radius> <height>: a sky of cubeLadder's players drawn by the viewer (stars.js): a half
      // ellipsoid standing on (x, y, z)
      const a = args.trim().split(/\s+/).map(Number);
      if (a.length >= 5 && a.every(Number.isFinite)) cfg.stardomes.push({ x: a[0], y: a[1], z: a[2], r: a[3], h: a[4] });
    }
  }
  return cfg;
}

/** Effective floor height of a cube: FHF lowers it by vdelta/4 (physics.cpp). */
export function floorAt(map, idx) {
  const c = map.cubes;
  return c.type[idx] === CUBE.FHF ? c.floor[idx] - c.vdelta[idx] / 4 : c.floor[idx];
}
export function ceilAt(map, idx) {
  const c = map.cubes;
  return c.type[idx] === CUBE.CHF ? c.ceil[idx] + c.vdelta[idx] / 4 : c.ceil[idx];
}

export function summary(map) {
  const counts = {};
  for (const e of map.ents) counts[e.name] = (counts[e.name] ?? 0) + 1;
  const typeCounts = [0, 0, 0, 0, 0, 0];
  for (let i = 0; i < map.cubes.type.length; i++) typeCounts[map.cubes.type[i]]++;
  return { title: map.title, version: map.version, size: map.size, numents: map.numents, waterlevel: map.waterlevel / 10,
    entities: counts, cubes: { SOLID: typeCounts[0], CORNER: typeCounts[1], FHF: typeCounts[2], CHF: typeCounts[3], SPACE: typeCounts[4], SEMISOLID: typeCounts[5] } };
}

/**
 * The height an entity (mapmodel, clip, pickup) stands on at cube idx: the cube's floor, as in AC. Big maps
 * (size >= 512: our browser-only maps, cl_lost) are one heightfield whose cubes all share one floor value,
 * so there a heightfield cube gives the heightfield's height (its north-west corner).
 */
export function entityBase(map, idx) {
  return map.size >= 512 ? floorAt(map, idx) : map.cubes.floor[idx];
}
