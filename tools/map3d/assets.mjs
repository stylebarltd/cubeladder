// Copies what the 3D viewer needs for a map out of the AssaultCube folders:
// the .cgz (+ .cfg), its textures, its sky box and its mapmodels (md3 + skins,
// listed in models/models.json the way md3.cfg describes them).
//
//   node tools/map3d/assets.mjs <map> [<map> ...]
//
// Sources, first hit wins: the user's AC folder (custom maps and packs, e.g.
// cl_angkorwat and the cubeladder/khmer models), then the game install.
// Override with AC_USER=... / AC_INSTALL=... . Everything lands in
// webroot/map3d/{maps,textures,models}, which is git-ignored (licences; the
// cl_angkorwat models also carry the QR hunt codes): upload it to the server
// separately. Re-run tools/flagrun/routes.mjs after adding a CTF map.
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import zlib from "node:zlib";
import { fileURLToPath, pathToFileURL } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const out = path.join(root, "webroot/map3d");
const SOURCES = [
  process.env.AC_USER ?? path.join(os.homedir(), ".assaultcube/v1.3/packages"),
  process.env.AC_INSTALL ?? path.join(os.homedir(), "Apps/AssaultCube_v1.3.0.2_LockdownEdition_RC1/packages"),
  // our own browser-only maps (cl_lost) are never installed into the game: take them from the generator's folder
  process.env.AC_MAPS ?? path.join(os.homedir(), "Development/ac-maps"),
].filter((d) => fs.existsSync(d));
if (!SOURCES.length) { console.error("no AssaultCube packages folder found (set AC_USER / AC_INSTALL)"); process.exit(1); }

// official maps sit in maps/official/ in the install
// and the ac-maps folder keeps its own models in models/<owner>/<pack>/ (no mapmodels/ level)
const find = (rel) => SOURCES.flatMap((s) => [path.join(s, rel), ...(rel.startsWith("maps/") ? [path.join(s, rel.replace(/^maps\//, "maps/official/"))] : []),
  ...(rel.startsWith("models/mapmodels/") ? [path.join(s, rel.replace(/^models\/mapmodels\//, "models/"))] : [])])
  .find((p) => fs.existsSync(p)) ?? null;
let copied = 0;
function copy(rel, dest) {
  const src = find(rel);
  if (!src) return false;
  const to = path.join(out, dest ?? rel);
  if (!fs.existsSync(to) || fs.statSync(to).mtimeMs < fs.statSync(src).mtimeMs) {
    fs.mkdirSync(path.dirname(to), { recursive: true });
    fs.copyFileSync(src, to);
    copied++;
  }
  return true;
}

globalThis.window = { pako: { inflate: (b) => new Uint8Array(zlib.gunzipSync(b)) } };
globalThis.fetch = async (u) => {
  const p = path.join(out, u);
  const ok = fs.existsSync(p);
  return { ok, status: ok ? 200 : 404, arrayBuffer: async () => fs.readFileSync(p), text: async () => fs.readFileSync(p, "utf8") };
};
const { loadMap, ENT } = await import(pathToFileURL(path.join(out, "cgz.js")).href);

const manifestFile = path.join(out, "models/models.json");
const manifest = fs.existsSync(manifestFile) ? JSON.parse(fs.readFileSync(manifestFile, "utf8")) : {};
const missing = [];

/** md3.cfg -> { parts: [{ md3, skins }], scale, trans }, paths relative to models/ (= mapmodels/). */
function modelEntry(name) {
  const dir = `models/mapmodels/${name}`;
  const cfgPath = find(`${dir}/md3.cfg`);
  const rel = (p) => path.posix.normalize(path.posix.join(name, p.replace(/^"|"$/g, "")));
  const parts = [];
  let scale = 1, trans = [0, 0, 0];
  if (cfgPath) {
    for (let line of fs.readFileSync(cfgPath, "utf8").split(/\r?\n/)) {
      line = line.replace(/\/\/.*$/, "").trim();
      const [cmd, ...a] = line.split(/\s+/);
      if (cmd === "md3load") parts.push({ md3: rel(a[0]), skins: {} });
      else if (cmd === "md3skin" && parts.length) parts[parts.length - 1].skins[a[0]] = rel(a[1]);
      else if (cmd === "mdlscale") scale = parseFloat(a[0]) / 100;
      else if (cmd === "mdltrans") trans = a.slice(0, 3).map(Number);
    }
  }
  if (!parts.length) parts.push({ md3: rel("tris.md3"), skins: {} });
  for (const part of parts) { // no md3skin: the folder's skin.jpg / skin.png (as the game)
    if (!Object.keys(part.skins).length) {
      const def = ["skin.jpg", "skin.png"].find((f) => find(`${dir}/${f}`));
      if (def) part.skins["*"] = rel(def);
    }
  }
  return { parts, scale, trans };
}

for (const name of process.argv.slice(2)) {
  if (!copy(`maps/${name}.cgz`)) { console.error(`${name}: .cgz not found in ${SOURCES.join(", ")}`); continue; }
  copy(`maps/${name}.cfg`);
  const map = await loadMap(`maps/${name}.cgz`);
  // textures (slot 0 is the sky, never drawn; mapmodels' attr4 world textures are in the same list)
  let tex = 0;
  for (const t of map.textures) if (t?.name && !copy(`textures/${t.name}`)) missing.push(`textures/${t.name}`); else tex++;
  // sky box
  const sky = (map.config?.sky ?? "").replace(/^textures\/skymaps\//, "");
  if (sky) {
    for (const side of ["lf", "rt", "ft", "bk", "dn", "up"]) if (!copy(`textures/skymaps/${sky}_${side}.jpg`)) missing.push(`sky ${sky}_${side}`);
    copy(`textures/skymaps/${path.dirname(sky)}/license.txt`);
  }
  // the map's ambient sounds (mapsound lines: AC's audio/, ambience.js plays them)
  for (const snd of map.config?.mapsounds ?? []) if (!copy(`audio/${snd}.ogg`, `sounds/${snd}.ogg`)) missing.push(`audio/${snd}`);
  if ((map.config?.mapsounds ?? []).length) copy("audio/ambience/authors.txt", "sounds/ambience/authors.txt");
  // mapmodels that are placed
  const used = new Set(map.ents.filter((e) => e.type === ENT.MAPMODEL).map((e) => map.config?.mapmodels?.[e.attr2]?.name).filter(Boolean));
  let models = 0;
  for (const m of used) {
    const entry = modelEntry(m);
    let ok = true;
    for (const part of entry.parts) {
      if (!copy(`models/mapmodels/${part.md3}`, `models/${part.md3}`)) { ok = false; missing.push(`model ${part.md3}`); }
      for (const s of Object.values(part.skins)) if (!copy(`models/mapmodels/${s}`, `models/${s}`)) missing.push(`skin ${s}`);
    }
    if (ok) { manifest[m] = entry; models++; }
  }
  console.log(`${name}: ${tex} textures, sky ${sky || "-"}, ${models}/${used.size} models`);
}
// The game's own models for the flag run: the assault rifle with hands (HUD), both flags, the crosshair.
for (const f of ["models/weapons/assault/tris_high.md3", "models/weapons/assault/skin_weapon.jpg", "models/weapons/knife/skin_hands.jpg", "models/weapons/knife/tris_high.md3", "models/weapons/knife/skin_weapon.jpg",
  "models/weapons/license.txt", "models/pickups/flags/CLA/tris.md3", "models/pickups/flags/CLA/CLA.jpg",
  "models/pickups/flags/RVSF/tris.md3", "models/pickups/flags/RVSF/RVSF.jpg", "models/pickups/license.txt",
  "crosshairs/default.png", "crosshairs/license.txt"]) {
  if (!copy(f, f.replace(/^models\//, "models/game/"))) missing.push(f);
}
// Pickups for the parkour run (entities.cpp entmdlnames): models and the pickup sounds (server.h).
const PICKUPS = { clips: "pistolclips", ammo: "ammobox", grenade: "nade", health: "health", helmet: "helmet", armour: "kevlar", akimbo: "akimbo" };
const pickups = {};
for (const [type, folder] of Object.entries(PICKUPS)) {
  const dir = `models/pickups/${folder}`, cfg = find(`${dir}/md3.cfg`);
  let md3 = null; const skins = {};
  for (const line of cfg ? fs.readFileSync(cfg, "utf8").split(/\r?\n/) : []) {
    const [cmd, a, b] = line.trim().split(/\s+/);
    if (cmd === "md3load") md3 = a; else if (cmd === "md3skin") skins[a] = b;
  }
  md3 ??= "tris.md3";
  if (!Object.keys(skins).length) skins["*"] = "skin.jpg";
  let ok = copy(`${dir}/${md3}`, `models/game/pickups/${folder}/${md3}`);
  for (const f of Object.values(skins)) ok = copy(`${dir}/${f}`, `models/game/pickups/${folder}/${f}`) && ok;
  if (ok) pickups[type] = { md3: `pickups/${folder}/${md3}`, skins: Object.fromEntries(Object.entries(skins).map(([k, f]) => [k, `pickups/${folder}/${f}`])) };
  else missing.push(`pickup ${type}`);
}
fs.mkdirSync(path.join(out, "models/game"), { recursive: true });
fs.writeFileSync(path.join(out, "models/game/pickups.json"), JSON.stringify(pickups, null, 1) + "\n");
for (const f of ["pickup_ammo_clip", "pickup_health", "pickup_armour", "pickup_helmet", "tick9"]) if (!copy(`audio/misc/${f}.ogg`, `sounds/misc/${f}.ogg`)) missing.push(f);

// 3D aim trainer: the player model as target (first CLA and RVSF skins), the rifle's and the hit sounds.
for (const f of ["tris.md2", "CLA/01.jpg", "RVSF/01.jpg", "license.txt"]) if (!copy(`models/playermodels/${f}`, `models/game/playermodels/${f}`)) missing.push(f);
for (const f of ["weapon/auto", "weapon/auto_reload", "misc/headshot", "player/gib", "player/die1", "player/pain1"]) if (!copy(`audio/${f}.ogg`, `sounds/${f}.ogg`)) missing.push(f);

// The game's sounds (sound.js): movement and flags, and the flag-grab music (config/sounds.cfg M_FLAGGRAB).
for (const f of ["jump", "step", "land", "footsteps", "crouch", "crouch_in", "crouch_out"]) if (!copy(`audio/player/${f}.ogg`, `sounds/player/${f}.ogg`)) missing.push(f);
for (const f of ["flagpickup", "flagscore"]) if (!copy(`audio/ctf/${f}.ogg`, `sounds/ctf/${f}.ogg`)) missing.push(f);
if (!copy("audio/soundtracks/pingpong/Ping_Pong_-_Kamikadze.ogg", "sounds/music/flaggrab.ogg")) missing.push("flag-grab music");
copy("audio/soundtracks/pingpong/license.txt", "sounds/music/license.txt");
fs.writeFileSync(manifestFile, JSON.stringify(manifest, null, 1) + "\n");
console.log(`${copied} files copied${missing.length ? `; missing: ${[...new Set(missing)].slice(0, 20).join(", ")}${missing.length > 20 ? " …" : ""}` : ""}`);
