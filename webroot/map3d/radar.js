// Radar for the browser games: round, top right, turning with the view like
// AssaultCube's radar. The map is drawn from the cube data (dark brown = low,
// sand = high, the colours of cubeLadder's Radar quiz). Markers out of range
// sit on the rim, so they still point the way.
//
// Map coordinates are AC's: x right, y down (north up on the plan).
import { floorAt } from "./cgz.js";

const RANGE = 56;   // cubes from the centre to the rim
const LOW = [58, 44, 32], HIGH = [226, 207, 163], SOLID = [18, 15, 12];

export class Radar {
  constructor(parent, size = 190) {
    this.size = size;
    this.canvas = document.createElement("canvas");
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    this.canvas.width = this.canvas.height = Math.round(size * dpr);
    Object.assign(this.canvas.style, { position: "absolute", top: "14px", right: "14px", width: `${size}px`, height: `${size}px`, pointerEvents: "none" });
    this.ctx = this.canvas.getContext("2d");
    this.ctx.scale(dpr, dpr);
    parent.appendChild(this.canvas);
    this.base = null;
  }

  /** Draws the plan of a map once (one pixel per cube). */
  setMap(map) {
    const { size, cubes } = map;
    let lo = Infinity, hi = -Infinity;
    for (let i = 0; i < size * size; i++) if (cubes.type[i] !== 0) { lo = Math.min(lo, floorAt(map, i)); hi = Math.max(hi, floorAt(map, i)); }
    const c = document.createElement("canvas");
    c.width = c.height = size;
    const ctx = c.getContext("2d");
    const img = ctx.createImageData(size, size);
    for (let i = 0; i < size * size; i++) {
      const open = cubes.type[i] !== 0;
      const t = open ? (floorAt(map, i) - lo) / Math.max(1, hi - lo) : 0;
      const col = open ? LOW.map((v, k) => v + (HIGH[k] - v) * t) : SOLID;
      img.data.set([col[0], col[1], col[2], 255], i * 4);
    }
    ctx.putImageData(img, 0, 0);
    this.base = c;
  }

  /**
   * player: { o: {x, y}, fwd: {x, y} } (physics.js Player);
   * markers: [{ x, y, color, label?, dim?, ring? }]
   */
  draw(player, markers = []) {
    const ctx = this.ctx, S = this.size, r = S / 2 - 3, k = r / RANGE;
    ctx.clearRect(0, 0, S, S);
    if (!this.base || !player) return;
    // turn the plan so the view direction points up
    const phi = -Math.PI / 2 - Math.atan2(player.fwd.y, player.fwd.x);
    const cos = Math.cos(phi), sin = Math.sin(phi);
    const toScreen = (x, y) => { const dx = (x - player.o.x) * k, dy = (y - player.o.y) * k; return [S / 2 + dx * cos - dy * sin, S / 2 + dx * sin + dy * cos]; };

    ctx.save();
    ctx.beginPath(); ctx.arc(S / 2, S / 2, r, 0, Math.PI * 2); ctx.clip();
    ctx.fillStyle = "rgb(18,15,12)"; ctx.fillRect(0, 0, S, S);
    ctx.globalAlpha = 0.9;
    ctx.translate(S / 2, S / 2); ctx.rotate(phi); ctx.scale(k, k); ctx.translate(-player.o.x, -player.o.y);
    ctx.imageSmoothingEnabled = false;
    ctx.drawImage(this.base, 0, 0);
    ctx.restore();

    // rim
    ctx.lineWidth = 3; ctx.strokeStyle = "rgba(230,233,239,.75)";
    ctx.beginPath(); ctx.arc(S / 2, S / 2, r, 0, Math.PI * 2); ctx.stroke();

    // markers (dim first, so the important ones are on top)
    for (const m of [...markers].sort((a, b) => (b.dim ? 1 : 0) - (a.dim ? 1 : 0))) {
      let [x, y] = toScreen(m.x, m.y);
      const dx = x - S / 2, dy = y - S / 2, d = Math.hypot(dx, dy), rim = d > r - 7;
      if (rim) { x = S / 2 + dx / d * (r - 7); y = S / 2 + dy / d * (r - 7); }
      ctx.globalAlpha = m.dim ? 0.55 : 1;
      ctx.fillStyle = m.color; ctx.strokeStyle = "#000"; ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.arc(x, y, m.dim ? 4 : 6, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
      if (m.ring) { ctx.strokeStyle = m.color; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(x, y, 10 + 2 * Math.sin(performance.now() / 150), 0, Math.PI * 2); ctx.stroke(); }
      if (m.label) {
        ctx.font = `bold ${m.dim ? 10 : 12}px system-ui, sans-serif`; ctx.textAlign = "center"; ctx.textBaseline = "middle";
        ctx.lineWidth = 3; ctx.strokeStyle = "rgba(0,0,0,.8)"; ctx.strokeText(m.label, x, y - (m.dim ? 10 : 13));
        ctx.fillStyle = "#fff"; ctx.fillText(m.label, x, y - (m.dim ? 10 : 13));
      }
    }
    ctx.globalAlpha = 1;
    // the player: an arrow in the middle, pointing up (the view direction)
    ctx.fillStyle = "#fff"; ctx.strokeStyle = "#000"; ctx.lineWidth = 1.5;
    ctx.beginPath(); ctx.moveTo(S / 2, S / 2 - 8); ctx.lineTo(S / 2 + 6, S / 2 + 6); ctx.lineTo(S / 2, S / 2 + 3); ctx.lineTo(S / 2 - 6, S / 2 + 6); ctx.closePath();
    ctx.fill(); ctx.stroke();
  }
}
