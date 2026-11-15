"""Generates PWA icons from the supplied logo. Run: python3 tools/make_icons.py"""
from PIL import Image, ImageDraw
import os
root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
logo = Image.open(os.path.join(root, 'public/assets/img/gradely-logo-original.png')).convert('RGBA')
box = logo.getchannel('A').point(lambda v: 255 if v > 8 else 0).getbbox()
logo = logo.crop(box)
out = os.path.join(root, 'public/assets/icons'); os.makedirs(out, exist_ok=True)

def fit(size, ratio):
    s = int(size * ratio); w, h = logo.size; k = s / max(w, h)
    return logo.resize((max(1, int(w * k)), max(1, int(h * k))), Image.LANCZOS)

def compose(size, ratio, bg):
    im = Image.new('RGBA', (size, size), bg); l = fit(size, ratio)
    im.paste(l, ((size - l.width) // 2, (size - l.height) // 2), l); return im

compose(192, 0.72, (255, 255, 255, 0)).save(f'{out}/icon-192.png', optimize=True)
compose(512, 0.72, (255, 255, 255, 0)).save(f'{out}/icon-512.png', optimize=True)
compose(512, 0.52, (13, 43, 34, 255)).save(f'{out}/icon-maskable-512.png', optimize=True)   # safe zone for masking
compose(180, 0.70, (255, 255, 255, 255)).convert('RGB').save(f'{out}/apple-touch-icon.png', optimize=True)
compose(32, 0.92, (255, 255, 255, 0)).save(f'{out}/favicon-32.png', optimize=True)
print('icons written')
