"""Utilidades compartidas para generar iconos desde public/logo.png."""
from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
LOGO_SRC = ROOT / 'public' / 'logo.png'
SURFACE_LIGHT = (250, 250, 249, 255)


def crop_to_content(img: Image.Image) -> Image.Image:
    bbox = img.getbbox()
    if bbox is None:
        return img
    return img.crop(bbox)


def resize_logo(content: Image.Image, box: int) -> Image.Image:
    cw, ch = content.size
    scale = min(box / cw, box / ch)
    nw, nh = max(1, int(cw * scale)), max(1, int(ch * scale))
    return content.resize((nw, nh), Image.Resampling.LANCZOS)


def fit_square(
    img: Image.Image,
    size: int,
    *,
    background: tuple[int, int, int, int] | None,
    logo_ratio: float,
) -> Image.Image:
    content = crop_to_content(img)
    inner = max(16, int(size * logo_ratio))
    resized = resize_logo(content, inner)

    canvas = Image.new('RGBA', (size, size), background or (0, 0, 0, 0))
    ox = (size - resized.width) // 2
    oy = (size - resized.height) // 2
    canvas.paste(resized, (ox, oy), resized)
    return canvas


def white_circle_canvas(size: int) -> Image.Image:
    canvas = Image.new('RGBA', (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(canvas)
    radius = size * 0.42
    cx = cy = size / 2
    draw.ellipse(
        (cx - radius, cy - radius, cx + radius, cy + radius),
        fill=(255, 255, 255, 255),
    )
    return canvas


def compose_any_icon(logo: Image.Image, size: int) -> Image.Image:
    canvas = white_circle_canvas(size)
    content = crop_to_content(logo)
    inner = max(16, int(size * 0.58))
    resized = resize_logo(content, inner)
    ox = (size - resized.width) // 2
    oy = (size - resized.height) // 2
    canvas.paste(resized, (ox, oy), resized)
    return canvas


def compose_maskable_icon(logo: Image.Image, size: int) -> Image.Image:
    return fit_square(logo, size, background=SURFACE_LIGHT, logo_ratio=0.68)
