// Solid cubes of the /run maps, for the server's fair-play check (FlagRun::throughWall):
// a run whose path stands in, or steps through, a solid cube is noclip or a teleport,
// whatever its speed. Uses the viewer's own parser (webroot/map3d/cgz.js).
//
//   node tools/flagrun/walls.mjs [map ...]   (default: every map in flagrun.json with flags)
//
// Writes webroot/map3d/maps/flagrun-walls.json: { map: { sfactor, solid } }, solid =
// base64 of one bit per cube, index (y << sfactor) + x, bit i % 8 of byte i >> 3.
// Re-run after adding or changing a map there.
import fs from "node:fs";
import path from "node:path";
import zlib from "node:zlib";
import { fileURLToPath, pathToFileURL } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const dir = path.join(root, "webroot/map3d");
globalThis.window = { pako: { inflate: (b) => new Uint8Array(zlib.gunzipSync(b)) } };
globalThis.fetch = async (u) => {
  const p = path.join(dir, u);
  const ok = fs.existsSync(p);
  return { ok, status: ok ? 200 : 404, arrayBuffer: async () => fs.readFileSync(p), text: async () => fs.readFileSync(p, "utf8") };
};
const { loadMap, CUBE } = await import(pathToFileURL(path.join(dir, "cgz.js")).href);
const runs = JSON.parse(fs.readFileSync(path.join(dir, "maps/flagrun.json"), "utf8"));
const outFile = path.join(dir, "maps/flagrun-walls.json");
const out = fs.existsSync(outFile) ? JSON.parse(fs.readFileSync(outFile, "utf8")) : {};
const names = process.argv.slice(2).length ? process.argv.slice(2) : Object.keys(runs).filter((m) => runs[m].flags).sort();
for (const name of names) {
  const map = await loadMap(`maps/${name}.cgz`);
  const n = map.size * map.size;
  const bits = new Uint8Array(Math.ceil(n / 8));
  let count = 0;
  for (let i = 0; i < n; i++) {
    if (map.cubes.type[i] === CUBE.SOLID) { bits[i >> 3] |= 1 << (i & 7); count++; }
  }
  out[name] = { sfactor: map.sfactor, solid: Buffer.from(bits).toString("base64") };
  console.log(`${name}: ${map.size}x${map.size}, ${count} solid cubes`);
}
fs.writeFileSync(outFile, JSON.stringify(out) + "\n");
