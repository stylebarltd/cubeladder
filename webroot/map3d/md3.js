// Minimal Quake 3 MD3 reader for AssaultCube mapmodels: first frame only,
// geometry and texture coordinates per surface. Layout follows md3.h in the AC
// source (header 108 bytes, surface header 108 bytes, xyz as int16 / 64).
//
// AC places a model vertex like this (md3.h load + vertmodel.h scaleverts):
//   ac = ((v[1]/64 + tx), (v[0]/64 - ty), (v[2]/64 + tz)) * (mdlscale / 16)
// and the viewer maps AC (x, y, z) to three (x, z, y).

import * as THREE from "three";

const cache = new Map(); // url -> Promise<[{ name, geometry }]>

export function loadMd3(url, trans = [0, 0, 0], scale = 1) {
  const key = `${url}|${trans.join(",")}|${scale}`;
  if (!cache.has(key)) {
    cache.set(key, fetch(url).then((r) => {
      if (!r.ok) throw new Error(`fetch ${url}: ${r.status}`);
      return r.arrayBuffer();
    }).then((buf) => parseMd3(buf, trans, scale)));
  }
  return cache.get(key);
}

function cstr(u8, off, len) {
  let s = "";
  for (let i = 0; i < len && u8[off + i]; i++) s += String.fromCharCode(u8[off + i]);
  return s;
}

export function parseMd3(buf, trans = [0, 0, 0], scale = 1) {
  const dv = new DataView(buf), u8 = new Uint8Array(buf);
  if (cstr(u8, 0, 4) !== "IDP3") throw new Error("not an MD3 file");
  const numSurfaces = dv.getInt32(84, true);
  let off = dv.getInt32(100, true);
  const k = scale / 16, [tx, ty, tz] = trans;
  const surfaces = [];
  for (let s = 0; s < numSurfaces; s++) {
    const name = cstr(u8, off + 4, 64);
    const numVerts = dv.getInt32(off + 80, true), numTris = dv.getInt32(off + 84, true);
    const ofsTris = off + dv.getInt32(off + 88, true), ofsSt = off + dv.getInt32(off + 96, true), ofsXyz = off + dv.getInt32(off + 100, true);
    const ofsEnd = dv.getInt32(off + 104, true);
    const pos = new Float32Array(numVerts * 3), uv = new Float32Array(numVerts * 2);
    for (let v = 0; v < numVerts; v++) {
      const p = ofsXyz + v * 8;
      const x0 = dv.getInt16(p, true) / 64, x1 = dv.getInt16(p + 2, true) / 64, x2 = dv.getInt16(p + 4, true) / 64;
      const ax = (x1 + tx) * k, ay = (x0 - ty) * k, az = (x2 + tz) * k; // AC coordinates
      pos[v * 3] = ax; pos[v * 3 + 1] = az; pos[v * 3 + 2] = ay;        // three: (x, up, y)
      uv[v * 2] = dv.getFloat32(ofsSt + v * 8, true);
      uv[v * 2 + 1] = 1 - dv.getFloat32(ofsSt + v * 8 + 4, true);
    }
    const index = new Uint32Array(numTris * 3);
    for (let t = 0; t < numTris * 3; t++) index[t] = dv.getInt32(ofsTris + t * 4, true);
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute("position", new THREE.BufferAttribute(pos, 3));
    geometry.setAttribute("uv", new THREE.BufferAttribute(uv, 2));
    geometry.setIndex(new THREE.BufferAttribute(index, 1));
    geometry.computeVertexNormals();
    surfaces.push({ name, geometry });
    off += ofsEnd;
  }
  return surfaces;
}
