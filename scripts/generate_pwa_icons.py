"""Genera iconos PWA (any + maskable) desde public/logo.png."""
from __future__ import annotations

import sys
from pathlib import Path

from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parent))
from brand_icon_utils import LOGO_SRC, compose_any_icon, compose_maskable_icon

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / 'public' / 'icons' / 'pwa'

SIZES = (72, 96, 128, 144, 152, 180, 192, 384, 512)


def main() -> None:
    logo = Image.open(LOGO_SRC).convert('RGBA')
    OUT_DIR.mkdir(parents=True, exist_ok=True)

    for size in SIZES:
        compose_any_icon(logo, size).save(OUT_DIR / f'icon-{size}.png', 'PNG')
        compose_maskable_icon(logo, size).save(
            OUT_DIR / f'icon-maskable-{size}.png',
            'PNG',
        )
        print(f'OK icon-{size}.png + maskable')

    # Sincroniza raíz pública para favicon / apple (compatibilidad previa)
    compose_maskable_icon(logo, 180).save(ROOT / 'public' / 'apple-touch-icon.png', 'PNG')
    compose_any_icon(logo, 192).save(ROOT / 'public' / 'icon-192.png', 'PNG')
    compose_any_icon(logo, 512).save(ROOT / 'public' / 'icon-512.png', 'PNG')

    print('Iconos PWA generados en public/icons/pwa/')


if __name__ == '__main__':
    main()
