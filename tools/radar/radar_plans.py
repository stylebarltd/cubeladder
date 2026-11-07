#!/usr/bin/env python3
"""
Floor plans for the Radar quiz (/radar): one PNG + JSON per map, drawn from the map file itself.

    python3 radar_plans.py <out dir> <map> [<map> ...]          # map names without .cgz
    python3 radar_plans.py <out dir> --sheet sheet.jpg <map>...  # also a review sheet of all plans

Maps are looked up in $AC_HOME/packages/maps (the player profile, default ~/.assaultcube/v1.3)
and $AC_DIR/packages/maps/official (the install, default ~/assaultcube).

What is drawn: only the ground players reach (plan.py: standable cells reached from any spawn,
so scenery outside the playable area is left out), widened by two cells so rooms keep their
shape; coloured by floor height, dark brown low to warm sand high, with dark outlines along the
walls. No textures, models or entities: the plan is our own drawing of the layout.

<map>.json (read by src/Service/Radar.php):
  name, title (colour codes removed), author (from "... by X" in the title, may be empty),
  scale (px per cell), width / height (px), centres ([x, y] in px: walkable spots about half
  open around them, where a question's piece is centred).
layouts.json: {map: answer} for all maps of the run. Maps whose drawn cells overlap by
SAME_LAYOUT or more (casa / kasa, douze / douze_ctf, syria 3.1 / 3.2: 92-100 %; different maps
stay under 70 %) are one answer, named after the first of them in the argument order, so list
the maps most played first and build the whole pool in one run.
"""
import json, os, random, re, sys
from PIL import Image, ImageDraw, ImageFilter
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import acmap, plan

SCALE = 6
SAME_LAYOUT = 0.85
BG = (30, 26, 22)
LOW, HIGH = (95, 70, 45), (235, 185, 115)
OUTLINE = (20, 16, 12)
AC_HOME = os.path.expanduser(os.environ.get("AC_HOME", "~/.assaultcube/v1.3"))
AC_DIR = os.path.expanduser(os.environ.get("AC_DIR", "~/assaultcube"))


def find(name):
    for p in (os.path.join(AC_HOME, "packages/maps", name + ".cgz"),
              os.path.join(AC_DIR, "packages/maps/official", name + ".cgz")):
        if os.path.isfile(p):
            return p
    raise FileNotFoundError(f"{name}.cgz not found in {AC_HOME} or {AC_DIR}")


def title_author(raw):
    title = re.sub(r"\x0c.", "", raw).strip()
    m = re.search(r"\b(?:made\s+)?by\s+(.+)$", title, re.I)
    return title, (m.group(1).strip() if m else "")


def playable(m):
    """Cells to draw: reached from any spawn, widened by 2 cells over open (non-solid) ground."""
    st = plan.stand(m)
    starts = [(e.x, e.y) for e in m.entities if e.name == "PLAYERSTART"]
    starts = [s for s in (plan.near(st, x, y, 2) for x, y in starts) if s]
    seen = plan.reach(m, st, starts) if starts else set()
    standable = {(x, y) for y in range(m.ssize) for x in range(m.ssize) if st[y][x] is not None}
    if len(seen) < 0.5 * len(standable):      # ladders and teleports are not modelled: fall back
        seen = standable
    out = set(seen)
    for (x, y) in seen:
        for dy in range(-2, 3):
            for dx in range(-2, 3):
                c = (x + dx, y + dy)
                if c not in out and 0 <= c[0] < m.ssize and 0 <= c[1] < m.ssize and m.cell(*c).type != acmap.SOLID:
                    out.add(c)
    return out


def draw(m, cells):
    x0 = min(x for x, _ in cells); y0 = min(y for _, y in cells)
    x1 = max(x for x, _ in cells); y1 = max(y for _, y in cells)
    floors = [m.cell(x, y).floor for x, y in cells]
    lo, hi = min(floors), max(floors)
    im = Image.new("RGB", ((x1 - x0 + 1) * SCALE, (y1 - y0 + 1) * SCALE), BG)
    d = ImageDraw.Draw(im)
    for x, y in cells:
        t = (m.cell(x, y).floor - lo) / max(1, hi - lo)
        c = tuple(int(a + (b - a) * t) for a, b in zip(LOW, HIGH))
        d.rectangle([(x - x0) * SCALE, (y - y0) * SCALE, (x - x0 + 1) * SCALE - 1, (y - y0 + 1) * SCALE - 1], fill=c)
    edge = im.filter(ImageFilter.FIND_EDGES).convert("L").point(lambda v: 255 if v > 40 else 0)
    im.paste(Image.new("RGB", im.size, OUTLINE), mask=edge.filter(ImageFilter.MaxFilter(3)).point(lambda v: 90 if v else 0))
    rel = sorted((x - x0, y - y0) for x, y in cells)
    return im, rel


def centres(rel, n=240, seed=1):
    """Walkable spots with about half of their surroundings (a 25 x 25 cell probe) open."""
    cells = set(rel)
    good = [c for c in rel if 18 <= sum((c[0] + dx, c[1] + dy) in cells
                                        for dx in range(-12, 13, 4) for dy in range(-12, 13, 4)) <= 34]
    random.Random(seed).shuffle(good)
    return [[x * SCALE + SCALE // 2, y * SCALE + SCALE // 2] for x, y in sorted(good[:n])]


def build(name, out):
    m = acmap.read(find(name))
    cells = playable(m)
    im, rel = draw(m, cells)
    title, author = title_author(m.title)
    meta = {
        "name": name, "title": title, "author": author, "scale": SCALE,
        "width": im.width, "height": im.height, "centres": centres(rel),
    }
    im.save(os.path.join(out, name + ".png"), optimize=True)
    with open(os.path.join(out, name + ".json"), "w") as f:
        json.dump(meta, f)
    print(f"{name}: {im.width}x{im.height} px, {len(meta['centres'])} centres, author {author or '?'}")
    return im, meta, set(rel)


def sheet(items, path, tile=320):
    cols = 4
    rows = (len(items) + cols - 1) // cols
    sh = Image.new("RGB", (cols * tile, rows * (tile + 34)), (18, 16, 14))
    d = ImageDraw.Draw(sh)
    for i, (im, meta) in enumerate(items):
        t = im.copy(); t.thumbnail((tile - 10, tile - 10))
        x, y = (i % cols) * tile, (i // cols) * (tile + 34)
        sh.paste(t, (x + (tile - t.width) // 2, y + (tile - t.height) // 2))
        d.text((x + 8, y + tile + 2), meta["name"], fill=(255, 200, 120))
        d.text((x + 8, y + tile + 16), (meta["author"] or "author ?")[:44], fill=(200, 200, 200))
    sh.save(path, quality=88)


if __name__ == "__main__":
    args = sys.argv[1:]
    out = args.pop(0)
    sheet_path = None
    if args and args[0] == "--sheet":
        args.pop(0); sheet_path = args.pop(0)
    os.makedirs(out, exist_ok=True)
    built = [build(n, out) for n in args]
    answer = {}
    for i, (_, meta, cells) in enumerate(built):
        answer[meta["name"]] = meta["name"]
        for _, first, fcells in built[:i]:
            if answer[first["name"]] == first["name"] and len(cells & fcells) / len(cells | fcells) >= SAME_LAYOUT:
                answer[meta["name"]] = first["name"]
                print(f"same layout: {meta['name']} -> {first['name']}")
                break
    with open(os.path.join(out, "layouts.json"), "w") as f:
        json.dump(answer, f, indent=1)
    if sheet_path:
        sheet([(im, meta) for im, meta, _ in built if answer[meta["name"]] == meta["name"]], sheet_path)
