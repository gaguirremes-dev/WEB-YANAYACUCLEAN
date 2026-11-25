"""Generate the recoloured hero splash variants from the original blue photos.

Usage (from the repo root):
    python -I scripts/tint-splash.py            # writes assets/img/hero/splash-<tone>-<size>.webp
    python -I scripts/tint-splash.py --preview DIR   # also writes mask previews into DIR

Only the splash water (drops, crown, ripples, floor reflections) and the towel are recoloured.
Sky, window, buildings, plants and the green leaves are left untouched. Masks are computed per
source image from a coarse hand-placed region of interest (ROI, in normalised coordinates, one per
crop: landscape / phone / tablet) refined by a saturation/hue mask, then feathered. Luminance and
specular highlights are preserved; saturation is capped so the result stays light, like water.
The original blue files (splash-*.webp without a tone) are never modified.
"""
import os
import sys

import numpy as np
from PIL import Image, ImageDraw, ImageFilter
from scipy import ndimage as ndi

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
HERO = os.path.join(ROOT, 'assets', 'img', 'hero')

# tone -> (hue deg, water saturation cap, towel saturation cap)
FLOOR_TINT = 0.22  # saturation given to the near-white floor (pastel, like water)
TONES = {
    'pink':   (326, 0.50, 0.62),
    'purple': (268, 0.50, 0.62),
    'apple':  (100, 0.55, 0.66),
    'indigo': (236, 0.52, 0.64),
    'aqua':   (186, 0.52, 0.64),
    'lime':   (135, 0.55, 0.66),
}

# crop -> files, water ROI polygon, towel ROI box, floor-from y (None = no full-width floor zone),
# frame exclusion boxes (window frame above the splash arm). All in normalised (x, y).
CROPS = {
    'land': {
        'files': ['splash-1100', 'splash-1920'],
        'poly': [(0.40, 0.15), (0.62, 0.14), (0.80, 0.14), (0.93, 0.28), (0.93, 0.60),
                 (1.00, 0.72), (1.00, 1.00), (0.25, 1.00), (0.25, 0.80), (0.40, 0.70)],
        'towel': (0.78, 0.58, 1.0, 0.84),
        'floor': 0.70,
        'exclude': [(0.728, 0.0, 0.752, 0.42), (0.78, 0.0, 0.80, 0.40), (0.858, 0.0, 0.878, 0.52)],
    },
    'm': {
        'files': ['splash-m-640', 'splash-m-1170'],
        'poly': [(0.10, 0.44), (0.45, 0.44), (0.90, 0.44), (0.97, 0.60), (1.0, 0.70),
                 (1, 1), (0, 1), (0, 0.72), (0.10, 0.62)],
        'towel': (0.66, 0.64, 1.0, 0.80),
        'floor': 0.72,
        'exclude': [(0.44, 0.48, 0.54, 0.60), (0.72, 0.40, 0.77, 0.58), (0.82, 0.40, 0.88, 0.62)],
    },
    't': {
        'files': ['splash-t-1024', 'splash-t-1536'],
        'poly': [(0.10, 0.44), (0.45, 0.44), (0.90, 0.44), (0.97, 0.60), (1.0, 0.70),
                 (1, 1), (0, 1), (0, 0.72), (0.10, 0.62)],
        'towel': (0.66, 0.66, 1.0, 0.82),
        'floor': 0.72,
        'exclude': [(0.44, 0.48, 0.54, 0.60), (0.72, 0.40, 0.77, 0.58), (0.82, 0.40, 0.88, 0.62)],
    },
}


def smoothstep(x, a, b):
    t = np.clip((x - a) / (b - a), 0, 1)
    return t * t * (3 - 2 * t)


def hsv_to_rgb(h, s, v):
    h = (h % 360) / 60.0
    i = np.floor(h).astype(int) % 6
    f = h - np.floor(h)
    p, q, t = v * (1 - s), v * (1 - f * s), v * (1 - (1 - f) * s)
    c = [i == k for k in range(6)]
    r = np.select(c, [v, q, p, p, t, v])
    g = np.select(c, [t, v, v, q, p, p])
    b = np.select(c, [p, p, t, v, v, q])
    return np.stack([r, g, b], -1)


def poly_mask(size, pts):
    w, h = size
    m = Image.new('L', size, 0)
    ImageDraw.Draw(m).polygon([(x * w, y * h) for x, y in pts], fill=255)
    return np.asarray(m, dtype=np.float32) / 255


def box_mask(shape, box):
    h, w = shape
    m = np.zeros(shape, np.float32)
    x0, y0, x1, y1 = box
    m[int(y0 * h):int(y1 * h), int(x0 * w):int(x1 * w)] = 1
    return m


def blur(m, sigma):
    return ndi.gaussian_filter(m, sigma)


def masks(img, cfg):
    """Return (water_mask, towel_mask) float32 in 0..1 for one source image."""
    w, h = img.size
    a = np.asarray(img.convert('RGB'), dtype=np.float32) / 255
    hsv = np.asarray(img.convert('HSV'), dtype=np.float32)
    hue, sat, val = hsv[..., 0] / 255 * 360, hsv[..., 1] / 255, hsv[..., 2] / 255
    k = w / 1100.0  # scale-aware radii
    water_hue = smoothstep(hue, 160, 185) * (1 - smoothstep(hue, 245, 265))

    roi = poly_mask((w, h), cfg['poly'])
    if cfg['floor'] is not None:
        fl = np.zeros((h, w), np.float32)
        fl[int(cfg['floor'] * h):, :] = 1
        roi = np.maximum(roi, fl)
    for box in cfg['exclude']:
        roi = roi * (1 - box_mask((h, w), box))
    roi = blur(roi, 6 * k)

    tw_box = box_mask((h, w), cfg['towel'])
    # Towel: strongly saturated mid-blue block inside its box, closed and filled.
    tw = ((sat > 0.27) & (val < 0.99) & (hue > 165) & (hue < 250)).astype(np.float32) * tw_box
    tw = ndi.binary_closing(tw > 0.5, iterations=max(2, int(4 * k)))
    tw = ndi.binary_opening(tw, iterations=1)
    lab, n = ndi.label(tw)
    if n:
        sizes = ndi.sum(tw, lab, range(1, n + 1))
        tw = lab == (1 + int(np.argmax(sizes)))
        tw = ndi.binary_fill_holes(tw)
    tw = ndi.binary_dilation(tw, iterations=max(1, int(2 * k)))
    tw = blur(tw.astype(np.float32), 1.2 * k)

    # Water: core = strongly saturated water pixels inside the ROI, grown so that pale
    # translucent parts of the splash and reflections stay inside.
    core = (sat > 0.42) & (water_hue > 0.5) & (roi > 0.5)
    core = ndi.binary_closing(core, iterations=max(2, int(5 * k)))
    near = blur(ndi.binary_dilation(core, iterations=max(2, int(5 * k))).astype(np.float32), 4 * k)
    lo = 0.09 + 0.27 * (1 - near)      # inside the core halo, pale water is still recoloured
    hi = 0.28 + 0.36 * (1 - near)
    w_sat = smoothstep(sat, lo, hi)
    ramp = np.zeros((h, w), np.float32)  # filled below when the crop has a full-width floor zone
    if cfg['floor'] is not None:
        # The portrait crops show a floor that is water from edge to edge: recolour it fully,
        # even its palest parts, so no blue patches are left next to the new tone.
        fy = int(cfg['floor'] * h)
        ramp = np.zeros((h, w), np.float32)
        ramp[fy:, :] = 1
        ramp = blur(ramp, 6 * k)
        floor_sat = smoothstep(sat, 0.0, 0.035)
        w_sat = np.maximum(w_sat, ramp * floor_sat)
        near = np.maximum(near, ramp)
    lift = np.zeros((h, w), np.float32)
    if cfg['floor'] is not None:
        lift = ramp * (1 - smoothstep(val, 0.94, 1.0))
        # near-white floor has no hue to rotate: its weight must not depend on the original hue
        water_hue = np.maximum(water_hue, ramp)
    water = roi * water_hue * w_sat * np.maximum(near, 0.0)
    # Never touch greens (leaves, plants): they must keep their natural colour.
    is_green = smoothstep(hue, 55, 75) * (1 - smoothstep(hue, 148, 166)) * smoothstep(sat, 0.18, 0.32)
    green_guard = 1 - is_green
    # Ambient sky/wall: light, low-saturation blue far from the splash core is not water.
    sky = smoothstep(val, 0.86, 0.95) * (1 - smoothstep(sat, 0.22, 0.40))
    sky = sky * (1 - ramp)  # the floor is water too: never protect it as 'sky'
    water = water * green_guard * (1 - 0.9 * sky)
    water = np.clip(water * (1 - tw), 0, 1)
    tw = tw * green_guard
    return water, tw, a, hue, sat, val, lift


def recolour(a, hue, sat, val, water, towel, tone, lift):
    th, cap_w, cap_t = TONES[tone]
    dh = ((hue - 208 + 180) % 360 - 180) * 0.25  # keep a little of the original hue variation
    new_h = th + dh
    new_s = np.minimum(sat * 0.9, cap_w)
    new_s = np.maximum(new_s, lift * FLOOR_TINT)  # pastel water tint on the (near-white) floor
    new_s = np.where(towel > 0.01, np.minimum(sat * 0.95, cap_t), new_s)
    new_v = np.clip(val ** 0.9, 0, 1)
    rgb = hsv_to_rgb(new_h, new_s, new_v)
    # Keep the original luminance structure (avoid inky pink/purple or neon yellow-green).
    lum = lambda x: x @ np.array([0.2126, 0.7152, 0.0722], np.float32)
    ratio = (lum(a) + 1e-3) / (lum(rgb) + 1e-3)
    rgb = np.clip(rgb * np.clip(ratio, 0.7, 1.5)[..., None] ** 0.6, 0, 1)
    m = np.clip(water + towel, 0, 1)[..., None]
    return a * (1 - m) + rgb * m


def main():
    preview = None
    if '--preview' in sys.argv:
        preview = sys.argv[sys.argv.index('--preview') + 1]
        os.makedirs(preview, exist_ok=True)
    for crop, cfg in CROPS.items():
        for name in cfg['files']:
            src = os.path.join(HERO, name + '.webp')
            img = Image.open(src)
            water, towel, a, hue, sat, val, lift = masks(img, cfg)
            if preview:
                mk = np.stack([towel, water, np.zeros_like(water)], -1)
                ov = np.clip(a * 0.35 + mk * 0.65, 0, 1)
                o = Image.fromarray((ov * 255).astype(np.uint8))
                o.thumbnail((900, 900))
                o.save(os.path.join(preview, name + '-mask.png'))
            for tone in TONES:
                out = recolour(a, hue, sat, val, water, towel, tone, lift)
                dst = os.path.join(HERO, name.replace('splash-', 'splash-%s-' % tone, 1) + '.webp')
                Image.fromarray((out * 255 + 0.5).astype(np.uint8)).save(dst, 'WEBP', quality=80, method=6)
                print(dst, os.path.getsize(dst) // 1024, 'KB')


if __name__ == '__main__':
    main()
