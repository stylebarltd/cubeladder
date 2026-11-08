// Minimal Quake 2 MD2 reader for AssaultCube's player models: one frame,
// geometry and texture coordinates, plus a point from tag vertices (the head).
// AC (md2.h, vertmodel.h scaleverts): a vertex is
//   (v0*s0 + t0, -(v1*s1 + t1), v2*s2 + t2), then + (tx, -ty, tz), * scale/16
// with scale = mdlscale / 100 and (tx, ty, tz) = mdltrans; the viewer maps
// AC (x, y, z) to three (x, z, y).

import * as THREE from "three";

const cache = new Map();

/** { geometry, tag: (vertexIndices) => THREE.Vector3 } of one frame, cached per url/frame. */
export function loadMd2(url, { scale = 1, trans = [0, 0, 0], frame = 0 } = {}) {
  const key = `${url}|${scale}|${trans}|${frame}`;
  if (!cache.has(key)) {
    cache.set(key, fetch(url).then((r) => { if (!r.ok) throw new Error(`fetch ${url}: ${r.status}`); return r.arrayBuffer(); })
      .then((buf) => parseMd2(buf, scale, trans, frame)));
  }
  return cache.get(key);
}

export function parseMd2(buf, scale, [tx, ty, tz], frameNo) {
  const dv = new DataView(buf);
  if (dv.getUint32(0, true) !== 0x32504449) throw new Error("not an MD2 file"); // "IDP2"
  const skinW = dv.getInt32(8, true), skinH = dv.getInt32(12, true), frameSize = dv.getInt32(16, true);
  const numXyz = dv.getInt32(24, true), numSt = dv.getInt32(28, true), numTris = dv.getInt32(32, true), numFrames = dv.getInt32(40, true);
  const ofsSt = dv.getInt32(48, true), ofsTris = dv.getInt32(52, true), ofsFrames = dv.getInt32(56, true);
  const f = ofsFrames + Math.min(frameNo, numFrames - 1) * frameSize;
  const s = [0, 1, 2].map((i) => dv.getFloat32(f + i * 4, true)), t = [0, 1, 2].map((i) => dv.getFloat32(f + 12 + i * 4, true));
  const k = scale / 16;
  const verts = new Float32Array(numXyz * 3); // three space
  for (let v = 0; v < numXyz; v++) {
    const p = f + 40 + v * 4;
    const ax = (dv.getUint8(p) * s[0] + t[0] + tx) * k;
    const ay = (-(dv.getUint8(p + 1) * s[1] + t[1]) - ty) * k;
    const az = (dv.getUint8(p + 2) * s[2] + t[2] + tz) * k;
    verts.set([ax, az, ay], v * 3);
  }
  const st = [];
  for (let i = 0; i < numSt; i++) st.push([dv.getInt16(ofsSt + i * 4, true) / skinW, dv.getInt16(ofsSt + i * 4 + 2, true) / skinH]);
  // un-indexed triangles (an MD2 vertex can carry different texture coordinates per triangle)
  const pos = new Float32Array(numTris * 9), uv = new Float32Array(numTris * 6);
  for (let tri = 0; tri < numTris; tri++) {
    const o = ofsTris + tri * 12;
    for (let c = 0; c < 3; c++) {
      const vi = dv.getUint16(o + c * 2, true), ti = dv.getUint16(o + 6 + c * 2, true);
      pos.set(verts.subarray(vi * 3, vi * 3 + 3), (tri * 3 + c) * 3);
      uv.set([st[ti][0], 1 - st[ti][1]], (tri * 3 + c) * 2);
    }
  }
  const geometry = new THREE.BufferGeometry();
  geometry.setAttribute("position", new THREE.BufferAttribute(pos, 3));
  geometry.setAttribute("uv", new THREE.BufferAttribute(uv, 2));
  geometry.computeVertexNormals();
  const tag = (indices) => {
    const c = new THREE.Vector3();
    for (const i of indices) c.add(new THREE.Vector3(verts[i * 3], verts[i * 3 + 1], verts[i * 3 + 2]));
    return c.divideScalar(indices.length);
  };
  geometry.computeBoundingBox();
  return { geometry, tag, box: geometry.boundingBox };
}
