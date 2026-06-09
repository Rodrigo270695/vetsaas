"""Recolorea logo.png a verde VetSaaS y elimina el fondo negro."""
from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / 'public' / 'logo.png'
ORIGINAL = ROOT / 'scripts' / 'logo-blue-source.png'

# Verde del botón de referencia (#006B53) y gradiente coherente
DARK_GREEN = (0, 72, 55)
MID_GREEN = (0, 107, 83)
LIGHT_GREEN = (0, 138, 106)


def is_background(r: int, g: int, b: int) -> bool:
    return r + g + b < 35 or max(r, g, b) < 18


def recolor_pixel(r: int, g: int, b: int) -> tuple[int, int, int]:
    lum = 0.299 * r + 0.587 * g + 0.114 * b
    t = (lum - 46) / (220 - 46)
    t = max(0.0, min(1.0, t))

    if t < 0.5:
        k = t / 0.5
        nr = int(DARK_GREEN[0] + (MID_GREEN[0] - DARK_GREEN[0]) * k)
        ng = int(DARK_GREEN[1] + (MID_GREEN[1] - DARK_GREEN[1]) * k)
        nb = int(DARK_GREEN[2] + (MID_GREEN[2] - DARK_GREEN[2]) * k)
    else:
        k = (t - 0.5) / 0.5
        nr = int(MID_GREEN[0] + (LIGHT_GREEN[0] - MID_GREEN[0]) * k)
        ng = int(MID_GREEN[1] + (LIGHT_GREEN[1] - MID_GREEN[1]) * k)
        nb = int(MID_GREEN[2] + (LIGHT_GREEN[2] - MID_GREEN[2]) * k)

    return nr, ng, nb


def main() -> None:
    source = ORIGINAL if ORIGINAL.exists() else SRC
    img = Image.open(source).convert('RGBA')
    pixels = img.load()
    w, h = img.size
    transparent = 0

    for y in range(h):
        for x in range(w):
            r, g, b, _a = pixels[x, y]
            if is_background(r, g, b):
                pixels[x, y] = (0, 0, 0, 0)
                transparent += 1
                continue

            nr, ng, nb = recolor_pixel(r, g, b)
            pixels[x, y] = (nr, ng, nb, 255)

    img.save(SRC, 'PNG')
    print(f'Origen: {source}')
    print(f'Guardado: {SRC} ({w}x{h})')
    print(f'Pixeles transparentes: {transparent}')


if __name__ == '__main__':
    main()
