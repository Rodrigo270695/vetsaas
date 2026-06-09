"""Genera favicon.ico, PNGs y apple-touch-icon desde public/logo.png."""
from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / 'public' / 'logo.png'
PUBLIC = ROOT / 'public'

SIZES_ICO = (16, 32, 48)
SIZES_PNG = (16, 32, 180, 192, 512)
THEME_GREEN = (0, 107, 83, 255)
SURFACE_LIGHT = (250, 250, 249, 255)


def crop_to_content(img: Image.Image) -> Image.Image:
    bbox = img.getbbox()
    if bbox is None:
        return img
    return img.crop(bbox)


def fit_square(img: Image.Image, size: int, *, background: tuple[int, int, int, int] | None) -> Image.Image:
    content = crop_to_content(img)
    cw, ch = content.size
    pad_ratio = 0.12
    inner = int(size * (1 - pad_ratio * 2))
    scale = min(inner / cw, inner / ch)
    nw, nh = max(1, int(cw * scale)), max(1, int(ch * scale))
    resized = content.resize((nw, nh), Image.Resampling.LANCZOS)

    canvas = Image.new('RGBA', (size, size), background or (0, 0, 0, 0))
    ox = (size - nw) // 2
    oy = (size - nh) // 2
    canvas.paste(resized, (ox, oy), resized)
    return canvas


def main() -> None:
    src = Image.open(SRC).convert('RGBA')

    ico_images = [fit_square(src, s, background=None) for s in SIZES_ICO]
    ico_path = PUBLIC / 'favicon.ico'
    ico_images[0].save(
        ico_path,
        format='ICO',
        sizes=[(s, s) for s in SIZES_ICO],
        append_images=ico_images[1:],
    )

    for size in SIZES_PNG:
        bg = SURFACE_LIGHT if size >= 180 else None
        icon = fit_square(src, size, background=bg)
        if size in (16, 32):
            icon.save(PUBLIC / f'favicon-{size}x{size}.png', 'PNG')
        elif size == 180:
            icon.save(PUBLIC / 'apple-touch-icon.png', 'PNG')
        elif size == 192:
            icon.save(PUBLIC / 'icon-192.png', 'PNG')
        elif size == 512:
            icon.save(PUBLIC / 'icon-512.png', 'PNG')

    print('Generados en public/:')
    print('  favicon.ico')
    for size in (16, 32):
        print(f'  favicon-{size}x{size}.png')
    print('  apple-touch-icon.png')
    print('  icon-192.png')
    print('  icon-512.png')


if __name__ == '__main__':
    main()
