"""
New map pictures in the style of AssaultCube's official map previews:
a 4:3 scene (1280x960 JPEG) without weapon, crosshair or console text.

  python3 -I bin/map_screenshots.py <screenshots dir> <out dir>

Then copy <out>/<shot>-1.jpg to webroot/img/maps/<map>.jpg and <shot>-2.jpg to
<map>-2.jpg (the second picture alternates per game), and run
bin/cake map_thumbs. Needs Pillow (python3-pil).

The screenshots (3285x1944) are 3840x2160 screen captures cropped on the
right and bottom. Per screenshot:
  1. the crosshair (fixed spot around 1919,1080) is covered with the
     pixels right next to it, blended in
  2. a 4:3 window left of the weapon (x < 1960) and above the arm
     (bottom at 92 % of the height) is cut out - this also drops the
     console text at the top - no mirroring, no artifacts
  3. scaled to 1280x960, JPEG quality 84
"""
import sys
from pathlib import Path
from PIL import Image, ImageFilter

SRC, OUT = Path(sys.argv[1]), Path(sys.argv[2])
OUT.mkdir(parents=True, exist_ok=True)

CROSSHAIR = (1879, 1040, 1959, 1120)   # box around the "+" (centre 1919,1080, ±40)
WINDOW_W = 1960                         # the weapon starts right of this
BOTTOM = 0.92                           # the arm reaches into the last 8 %


def process(path):
    im = Image.open(path).convert('RGB')
    W, H = im.size

    # 1. crosshair: copy the area to its left over it, soft edges
    x0, y0, x1, y1 = CROSSHAIR
    w, h = x1 - x0, y1 - y0
    patch = im.crop((x0 - w - 4, y0, x0 - 4, y1))
    mask = Image.new('L', (w, h), 0)
    mask.paste(255, (6, 6, w - 6, h - 6))
    im.paste(patch, (x0, y0), mask.filter(ImageFilter.GaussianBlur(3)))

    # 2. 4:3 window left of the weapon, above the arm
    wh = round(WINDOW_W * 3 / 4)
    bottom = int(H * BOTTOM)
    top = max(0, bottom - wh)
    im = im.crop((0, top, WINDOW_W, top + wh))

    # 3. preview size
    out = im.resize((1280, 960), Image.Resampling.LANCZOS)
    target = OUT / (path.stem + '.jpg')
    out.save(target, 'JPEG', quality=84, optimize=True, progressive=True)
    return target


for p in sorted(SRC.glob('*.png')):
    t = process(p)
    print(t.name, t.stat().st_size // 1024, 'KB')
