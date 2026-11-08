// Live panels in our browser-only maps (cl_cube's hall of fame and "your chamber"): the map config lists
//   display <id> <x> <y> <z> <facing e|w|n|s> <w> <h>
// (the panel's centre in cubes, the way it faces, its size), and the viewer draws each one on a canvas from
// /run/halls (RunController::halls): the Hall of Fame's top three of every list, and the visitor's own card.
//   hof:<list key>   a list's top three        hof:title   the hall's name        hof:intro   what the hall is
//   me:card          avatar, name, level, rating          me:stats   totals        me:records   Hall of Fame places
//   sky:intro / sky:top / sky:countries / sky:me   the star chamber (stars.js), from /run/stars
// The panels glow (unlit material), so they read in the dark hall.

import * as THREE from "three";

const PX = 100;                       // canvas pixels per cube
const GOLD = "#e8c25a", INK = "#f3ead7", DIM = "#b8a98c", BG0 = "#1b1712", BG1 = "#2c241a";
const MEDAL = ["#f5c542", "#c9d1d9", "#d08a4a"];
const ROT = { s: 0, n: Math.PI, e: Math.PI / 2, w: -Math.PI / 2 };
const SERIF = "Georgia, 'Times New Roman', serif", SANS = "system-ui, sans-serif";

const cache = new Map();
/** One fetch per URL and page (the panels and the star dome share /run/stars). */
export function getJSON(url) {
  if (!cache.has(url)) {
    cache.set(url, fetch(new URL(url, location.href), { credentials: "same-origin" })
      .then((r) => (r.ok ? r.json() : null)).catch(() => null));
  }
  return cache.get(url);
}

export class Displays {
  constructor(group) {
    this.group = group;
    this.panels = [];
    this.images = new Map();
  }

  build(map) {
    for (const d of map.config?.displays ?? []) {
      const canvas = document.createElement("canvas");
      canvas.width = Math.round(d.w * PX); canvas.height = Math.round(d.h * PX);
      const tex = new THREE.CanvasTexture(canvas);
      tex.colorSpace = THREE.SRGBColorSpace;
      tex.anisotropy = 4;
      const mesh = new THREE.Mesh(new THREE.PlaneGeometry(d.w, d.h), new THREE.MeshBasicMaterial({ map: tex, color: 0xe6e6e6 }));
      mesh.position.set(d.x, d.z, d.y);
      mesh.rotation.y = ROT[d.facing];
      this.group.add(mesh);
      this.panels.push({ d, canvas, tex });
    }
    if (!this.panels.length) return;
    this.draw(null);
    getJSON("/run/halls").then((data) => { this.loaded = true; this.draw(data); });
    if (this.panels.some((p) => p.d.id.startsWith("sky:"))) getJSON("/run/stars").then((sky) => { this.sky = sky ?? false; this.draw(this.data); });
  }

  image(url) {
    if (!url) return null;
    if (!this.images.has(url)) {
      const img = new Image();
      img.onload = () => this.draw(this.data);
      img.src = url;
      this.images.set(url, img);
    }
    const img = this.images.get(url);
    return img.complete && img.naturalWidth ? img : null;
  }

  draw(data) {
    this.data = data;
    for (const p of this.panels) {
      const g = p.canvas.getContext("2d"), W = p.canvas.width, H = p.canvas.height;
      background(g, W, H);
      const [kind, key] = p.d.id.split(":");
      if (kind === "hof") this.hof(g, W, H, key, data);
      else if (kind === "me") this.me(g, W, H, key, data);
      else if (kind === "sky") this.skyPanel(g, W, H, key, this.sky);
      p.tex.needsUpdate = true;
    }
  }

  hof(g, W, H, key, data) {
    if (key === "title") {
      g.textAlign = "center"; g.fillStyle = GOLD;
      g.font = `bold ${H * 0.2}px ${SERIF}`; g.fillText("C U B E L A D D E R", W / 2, H * 0.36);
      g.font = `bold ${H * 0.34}px ${SERIF}`; g.fillText("HALL OF FAME", W / 2, H * 0.76);
      return;
    }
    if (key === "intro") {
      g.textAlign = "center"; g.fillStyle = GOLD; g.font = `bold ${W * 0.09}px ${SERIF}`;
      g.fillText("The Hall of Fame", W / 2, H * 0.16);
      g.fillStyle = INK; g.font = `${W * 0.052}px ${SANS}`;
      wrap(g, "The best single game of every list on cubeLadder. Here stand the first three of each, live: play on our servers and your name can hang on these walls.", W / 2, H * 0.3, W * 0.84, W * 0.07);
      g.fillStyle = DIM; g.font = `italic ${W * 0.045}px ${SERIF}`;
      g.fillText("cubeladder.ovh/players/hall-of-fame", W / 2, H * 0.9);
      return;
    }
    const list = data?.hall?.find((h) => h.key === key);
    g.textAlign = "center"; g.fillStyle = GOLD; g.font = `bold ${W * 0.085}px ${SERIF}`;
    g.fillText(list?.title ?? "Hall of Fame", W / 2, H * 0.1);
    g.fillStyle = DIM; g.font = `${W * 0.045}px ${SANS}`;
    g.fillText(list ? `in ${list.unit}, best single game` : "loading ...", W / 2, H * 0.155);
    if (!list) return;
    if (!list.top.length) { g.fillStyle = INK; g.fillText("Nobody yet.", W / 2, H * 0.5); return; }
    list.top.forEach((r, i) => {
      const y0 = H * (0.21 + i * 0.26), rowH = H * 0.24;
      g.fillStyle = "rgba(255,255,255,0.04)"; roundRect(g, W * 0.05, y0, W * 0.9, rowH, 18); g.fill();
      const cy = y0 + rowH * 0.42, r0 = rowH * 0.3;
      // medal, avatar, name, value
      g.fillStyle = MEDAL[i]; circle(g, W * 0.14, cy, r0 * 0.55); g.fill();
      g.fillStyle = "#1b1712"; g.font = `bold ${r0 * 0.7}px ${SANS}`; g.textAlign = "center"; g.fillText(String(r.pos), W * 0.14, cy + r0 * 0.25);
      avatar(g, this.image(r.picture), W * 0.33, cy, r0, MEDAL[i]);
      g.textAlign = "left"; g.fillStyle = INK; g.font = `bold ${W * 0.062}px ${SANS}`;
      g.fillText(fit(g, r.name, W * 0.45), W * 0.47, cy - r0 * 0.05);
      g.fillStyle = GOLD; g.font = `bold ${W * 0.075}px ${SERIF}`;
      g.fillText(String(r.value), W * 0.47, cy + r0 * 0.8);
      if (r.map) { g.fillStyle = DIM; g.font = `${W * 0.038}px ${SANS}`; g.fillText(fit(g, r.map, W * 0.45), W * 0.47, y0 + rowH * 0.95); }
    });
  }

  skyPanel(g, W, H, key, sky) {
    g.textAlign = "center";
    const title = { intro: "The sky of cubeLadder", top: "The brightest stars", countries: "The great constellations", me: "Your star" }[key] ?? "";
    g.fillStyle = GOLD; g.font = `bold ${W * 0.07}px ${SERIF}`; g.fillText(title, W / 2, H * 0.15);
    if (sky === undefined) { g.fillStyle = DIM; g.font = `${W * 0.045}px ${SANS}`; g.fillText("loading ...", W / 2, H * 0.5); return; }
    if (!sky) { g.fillStyle = DIM; g.font = `${W * 0.045}px ${SANS}`; g.fillText("The sky is clouded over.", W / 2, H * 0.5); return; }
    const n = sky.stars.length, nc = sky.countries.filter((c) => c.code).length, fmt = (v) => Number(v).toLocaleString("en");
    if (key === "intro") {
      g.fillStyle = INK; g.font = `${W * 0.043}px ${SANS}`;
      wrap(g, `Every star above you is a player who has played on cubeLadder: ${fmt(n)} stars from ${nc} countries. ` +
        "Each country is a constellation. The more games, the brighter the star; the higher the rating, the hotter it burns, " +
        "from orange to white to blue.", W / 2, H * 0.3, W * 0.86, W * 0.062);
      g.fillStyle = DIM; g.font = `italic ${W * 0.04}px ${SERIF}`; g.fillText("Look up.", W / 2, H * 0.9);
      return;
    }
    if (key === "top" || key === "countries") {
      const rows = key === "top" ? sky.named.map((r) => [`${r.rank}.`, r.name, r.rating.toFixed(1)])
        : sky.countries.filter((c) => c.code).slice(0, 10).map((c, k) => [`${k + 1}.`, c.name, fmt(c.n)]);
      g.fillStyle = DIM; g.font = `${W * 0.036}px ${SANS}`;
      g.fillText(key === "top" ? "the ten highest rated: they carry their names up there" : "the most players: their names hang in the sky", W / 2, H * 0.23);
      rows.forEach(([a, b, c], k) => {
        const y = H * (0.33 + k * 0.064);
        g.font = `bold ${W * 0.036}px ${SANS}`; g.textAlign = "right"; g.fillStyle = k < 3 ? MEDAL[k] : DIM; g.fillText(a, W * 0.17, y);
        g.textAlign = "left"; g.fillStyle = INK; g.font = `${W * 0.038}px ${SANS}`; g.fillText(fit(g, b, W * 0.55), W * 0.21, y);
        g.textAlign = "right"; g.fillStyle = GOLD; g.font = `bold ${W * 0.038}px ${SANS}`; g.fillText(c, W * 0.88, y);
      });
      return;
    }
    // me
    const me = sky.me;
    g.fillStyle = INK; g.font = `${W * 0.045}px ${SANS}`;
    if (!me) {
      wrap(g, "Your star is not lit here yet. Play a game on a cubeLadder server, then open cubeladder.ovh in this browser: " +
        "the sky finds you, and your star turns gold.", W / 2, H * 0.34, W * 0.84, W * 0.065);
      return;
    }
    g.fillStyle = GOLD; g.font = `bold ${W * 0.075}px ${SANS}`; g.fillText(fit(g, me.name, W * 0.86), W / 2, H * 0.34);
    g.fillStyle = INK; g.font = `${W * 0.045}px ${SANS}`;
    const where = me.country ? `in the constellation of ${me.country}, one of its ${fmt(me.countryStars)} stars` : "among the stars of no country";
    wrap(g, `You shine ${where}: the gold one, with the beam of light. ${fmt(me.games)} games` +
      (me.rating ? `, rating ${me.rating.toFixed(1)}.` : "."), W / 2, H * 0.48, W * 0.84, W * 0.066);
    g.fillStyle = DIM; g.font = `italic ${W * 0.04}px ${SERIF}`; g.fillText("Stand in its light.", W / 2, H * 0.9);
  }

  me(g, W, H, key, data) {
    const me = data?.me;
    g.textAlign = "center";
    if (!me) {
      g.fillStyle = GOLD; g.font = `bold ${W * 0.075}px ${SERIF}`;
      g.fillText(key === "card" ? "Who are you?" : key === "stats" ? "Your numbers" : "Your records", W / 2, H * 0.2);
      g.fillStyle = INK; g.font = `${W * 0.045}px ${SANS}`;
      wrap(g, data ? "This chamber shows whoever enters it. Play on a cubeLadder server, then open cubeladder.ovh in this browser: it knows you, and you stand here." :
        "loading ...", W / 2, H * 0.38, W * 0.84, W * 0.065);
      return;
    }
    if (key === "card") {
      g.fillStyle = DIM; g.font = `italic ${W * 0.05}px ${SERIF}`; g.fillText("this chamber belongs to", W / 2, H * 0.1);
      avatar(g, this.image(me.picture), W / 2, H * 0.36, W * 0.2, GOLD);
      g.fillStyle = INK; g.font = `bold ${W * 0.085}px ${SANS}`; g.fillText(fit(g, me.name, W * 0.9), W / 2, H * 0.68);
      g.fillStyle = GOLD; g.font = `bold ${W * 0.05}px ${SERIF}`;
      g.fillText(me.level ? `QR hunt level ${me.level}${me.levelName ? " · " + me.levelName : ""} · ${me.level}/${me.codes} codes` : `QR hunt: no code found yet (${me.codes} hidden)`, W / 2, H * 0.77);
      g.fillStyle = DIM; g.font = `${W * 0.045}px ${SANS}`;
      const r = [me.rank ? `CTF rank #${me.rank}` : null, me.type, me.since ? `playing since ${me.since}` : null].filter(Boolean).join(" · ");
      wrap(g, r, W / 2, H * 0.86, W * 0.9, W * 0.055);
      return;
    }
    if (key === "stats") {
      g.fillStyle = GOLD; g.font = `bold ${W * 0.07}px ${SERIF}`; g.fillText("Your numbers", W / 2, H * 0.14);
      const kd = me.deaths ? (me.kills / me.deaths).toFixed(2) : String(me.kills);
      const cells = [["games", me.games], ["kills", me.kills], ["K/D", kd], ["headshots", me.headshots], ["flags", me.flags],
        ["best streak", me.streak], ["hours", me.hours], ["favourite map", me.favouriteMap ?? "-"], ["rating", me.rating ?? "-"]];
      cells.forEach(([label, v], i) => {
        const cx = W * (0.18 + (i % 3) * 0.32), cy = H * (0.36 + Math.floor(i / 3) * 0.24);
        g.fillStyle = INK; g.font = `bold ${W * (String(v).length > 8 ? 0.04 : 0.06)}px ${SANS}`; g.fillText(fit(g, String(v), W * 0.3), cx, cy);
        g.fillStyle = DIM; g.font = `${W * 0.032}px ${SANS}`; g.fillText(label, cx, cy + H * 0.07);
      });
      return;
    }
    // records: where you stand in the Hall of Fame
    g.fillStyle = GOLD; g.font = `bold ${W * 0.06}px ${SERIF}`; g.fillText("Your places in the Hall of Fame", W / 2, H * 0.14);
    if (!me.records.length) {
      g.fillStyle = INK; g.font = `${W * 0.04}px ${SANS}`;
      wrap(g, "None yet. One great game is enough: the top ten of every list are on cubeladder.ovh, the first three hang next door.", W / 2, H * 0.4, W * 0.8, W * 0.055);
      return;
    }
    me.records.slice(0, 6).forEach((r, i) => {
      const y = H * (0.3 + i * 0.11);
      g.textAlign = "left"; g.fillStyle = r.pos <= 3 ? MEDAL[r.pos - 1] : INK; g.font = `bold ${W * 0.04}px ${SANS}`;
      g.fillText(`#${r.pos}`, W * 0.1, y);
      g.fillStyle = INK; g.font = `${W * 0.04}px ${SANS}`; g.fillText(r.title, W * 0.2, y);
      g.textAlign = "right"; g.fillStyle = GOLD; g.fillText(`${r.value} ${r.unit}`, W * 0.9, y);
    });
  }
}

function background(g, W, H) {
  const gr = g.createLinearGradient(0, 0, 0, H);
  gr.addColorStop(0, BG1); gr.addColorStop(1, BG0);
  g.fillStyle = gr; g.fillRect(0, 0, W, H);
  g.strokeStyle = GOLD; g.lineWidth = Math.max(4, W * 0.012);
  g.strokeRect(g.lineWidth, g.lineWidth, W - 2 * g.lineWidth, H - 2 * g.lineWidth);
  g.textBaseline = "alphabetic";
}
function circle(g, x, y, r) { g.beginPath(); g.arc(x, y, r, 0, Math.PI * 2); }
function roundRect(g, x, y, w, h, r) { g.beginPath(); g.roundRect(x, y, w, h, r); }
function avatar(g, img, x, y, r, ring) {
  g.save(); circle(g, x, y, r); g.clip();
  if (img) g.drawImage(img, x - r, y - r, 2 * r, 2 * r);
  else { g.fillStyle = "#3a3126"; g.fillRect(x - r, y - r, 2 * r, 2 * r); }
  g.restore();
  g.strokeStyle = ring; g.lineWidth = r * 0.08; circle(g, x, y, r); g.stroke();
}
function fit(g, text, max) {
  let t = String(text);
  while (t.length > 1 && g.measureText(t).width > max) t = t.slice(0, -2) + "…";
  return t;
}
function wrap(g, text, x, y, max, lh) {
  let line = "";
  for (const w of String(text).split(" ")) {
    const t = line ? line + " " + w : w;
    if (g.measureText(t).width > max && line) { g.fillText(line, x, y); y += lh; line = w; } else line = t;
  }
  if (line) g.fillText(line, x, y);
}
