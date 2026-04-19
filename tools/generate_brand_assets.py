from __future__ import annotations

import math
from pathlib import Path
from typing import Iterable

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont
from PIL import Image, ImageDraw, ImageFilter, ImageFont


ROOT = Path(__file__).resolve().parents[1]
PUBLIC_DIR = ROOT / "public"
BRAND_DIR = PUBLIC_DIR / "brand"

SANS_FONT = Path(r"C:\Windows\Fonts\bahnschrift.ttf")
SERIF_FONT = Path(r"C:\Windows\Fonts\GARABD.TTF")

GOLD_FLAT = "#D8AE53"
GOLD_LIGHT = "#F4DD9A"
GOLD_MID = "#E6BA59"
GOLD_DEEP = "#B37A1E"
GOLD_FAVICON = "#F7DE93"
BLACK = "#060606"
WHITE = "#F8F6F2"

FULL_LOGO_VIEWBOX = (22, 54, 1206, 308)


class OutlineFont:
    def __init__(self, font_path: Path) -> None:
        self.font = TTFont(str(font_path))
        self.glyph_set = self.font.getGlyphSet()
        self.cmap = self.font.getBestCmap()
        self.metrics = self.font["hmtx"].metrics
        self.units_per_em = self.font["head"].unitsPerEm
        self.kerning = {}

        if "kern" in self.font:
            for table in self.font["kern"].kernTables:
                if getattr(table, "kernTable", None):
                    self.kerning.update(table.kernTable)

    def text_path(
        self,
        text: str,
        size: float,
        x: float,
        baseline: float,
        tracking: float = 0.0,
    ) -> tuple[str, float]:
        scale = size / self.units_per_em
        cursor = x
        commands: list[str] = []
        previous = None

        for index, char in enumerate(text):
            glyph_name = self.cmap.get(ord(char), "space")

            if previous is not None:
                cursor += self.kerning.get((previous, glyph_name), 0) * scale

            pen = SVGPathPen(self.glyph_set)
            transform = TransformPen(pen, (scale, 0, 0, -scale, cursor, baseline))
            self.glyph_set[glyph_name].draw(transform)
            command = pen.getCommands()
            if command:
                commands.append(command)

            advance_width = self.metrics[glyph_name][0] * scale
            if index < len(text) - 1:
                advance_width += tracking

            cursor += advance_width
            previous = glyph_name

        return " ".join(commands), cursor - x


def polar(cx: float, cy: float, radius: float, angle: float) -> tuple[float, float]:
    rad = math.radians(angle)
    return cx + radius * math.cos(rad), cy + radius * math.sin(rad)


def arc_path(cx: float, cy: float, radius: float, start: float, end: float) -> str:
    sx, sy = polar(cx, cy, radius, start)
    ex, ey = polar(cx, cy, radius, end)
    delta = (end - start) % 360
    large_arc = 1 if delta > 180 else 0
    return (
        f"M {sx:.2f} {sy:.2f} "
        f"A {radius:.2f} {radius:.2f} 0 {large_arc} 1 {ex:.2f} {ey:.2f}"
    )


def svg_document(view_box: str, body: Iterable[str]) -> str:
    return (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{view_box}" fill="none">\n'
        + "\n".join(body)
        + "\n</svg>\n"
    )


def gold_gradient_definition() -> str:
    return """
    <defs>
        <linearGradient id="ys-gold-gradient" x1="0%" y1="8%" x2="100%" y2="92%">
            <stop offset="0%" stop-color="#F4DD9A" />
            <stop offset="44%" stop-color="#E6BA59" />
            <stop offset="100%" stop-color="#B37A1E" />
        </linearGradient>
    </defs>
    """.strip()


def build_badge_svg(
    fill: str,
    ring: str,
    disk_fill: str | None,
    canvas_background: str | None = None,
) -> str:
    serif = OutlineFont(SERIF_FONT)
    monogram_path, width = serif.text_path("YR", 304, 0, 346, tracking=-14)
    monogram_x = (512 - width) / 2
    monogram_path, _ = serif.text_path("YR", 304, monogram_x, 346, tracking=-14)

    elements = [gold_gradient_definition()]

    if canvas_background:
        elements.append(
            f'<rect x="0" y="0" width="512" height="512" fill="{canvas_background}" rx="88" />'
        )

    if disk_fill:
        elements.append(f'<circle cx="256" cy="256" r="198" fill="{disk_fill}" />')

    elements.extend(
        [
            '<circle cx="256" cy="256" r="216" stroke="{ring}" stroke-width="26" />'.format(
                ring=ring
            ),
            '<circle cx="256" cy="256" r="190" stroke="{ring}" stroke-width="12" />'.format(
                ring=ring
            ),
            f'<path d="{monogram_path}" fill="{fill}" />',
        ]
    )

    return svg_document("0 0 512 512", elements)


def build_full_logo_svg(gold_fill: str, ring: str, text_fill: str, disk_fill: str | None) -> str:
    sans = OutlineFont(SANS_FONT)
    subtext_font = OutlineFont(SANS_FONT)

    wordmark_size = 202
    subtext_size = 63
    wordmark_baseline = 262
    subtext_baseline = 334
    wordmark_x = 366

    wordmark_path, wordmark_width = sans.text_path("Ysabelle", wordmark_size, 0, wordmark_baseline, tracking=-2.0)
    wordmark_path, _ = sans.text_path("Ysabelle", wordmark_size, wordmark_x, wordmark_baseline, tracking=-2.0)

    subtext_path, subtext_width = subtext_font.text_path("RETAIL SHOP", subtext_size, 0, subtext_baseline, tracking=13.5)
    subtext_x = wordmark_x + 18
    if subtext_width < wordmark_width - 24:
        subtext_x = wordmark_x + ((wordmark_width - subtext_width) / 2)
    subtext_path, _ = subtext_font.text_path("RETAIL SHOP", subtext_size, subtext_x, subtext_baseline, tracking=13.5)

    elements = [gold_gradient_definition()]

    if disk_fill:
        elements.append(f'<circle cx="182" cy="204" r="128" fill="{disk_fill}" />')

    elements.extend(
        [
            '<path d="{d}" stroke="{ring}" stroke-width="18" stroke-linecap="round" />'.format(
                d=arc_path(182, 204, 151, 38, 322),
                ring=ring,
            ),
            '<path d="{d}" stroke="{ring}" stroke-width="7" stroke-linecap="round" />'.format(
                d=arc_path(182, 204, 132, 40, 320),
                ring=ring,
            ),
            '<path d="M 98 124 L 186 126 L 176 242 L 86 249 Z" fill="{fill}" />'.format(fill=gold_fill),
            '<path d="M 86 126 L 61 136 L 66 230 L 86 249 Z" fill="{fill}" opacity="0.86" />'.format(fill=gold_fill),
            '<path d="M 111 124 C 111 86 124 66 147 66 C 170 66 182 86 182 124" stroke="{ring}" stroke-width="9" stroke-linecap="round" />'.format(ring=ring),
            '<path d="M 125 124 C 125 98 133 82 147 82 C 161 82 169 98 169 124" stroke="{ring}" stroke-width="5" stroke-linecap="round" opacity="0.88" />'.format(ring=ring),
            '<circle cx="110" cy="124" r="9" fill="{disk}" stroke="{ring}" stroke-width="4" />'.format(
                disk=disk_fill or "transparent",
                ring=ring,
            ),
            '<circle cx="170" cy="124" r="9" fill="{disk}" stroke="{ring}" stroke-width="4" />'.format(
                disk=disk_fill or "transparent",
                ring=ring,
            ),
            f'<path d="{wordmark_path}" fill="{text_fill}" />',
            f'<path d="{subtext_path}" fill="{text_fill}" />',
        ]
    )

    min_x, min_y, width, height = FULL_LOGO_VIEWBOX
    return svg_document(f"{min_x} {min_y} {width} {height}", elements)


def write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def draw_tracked_text(
    draw: ImageDraw.ImageDraw,
    position: tuple[float, float],
    text: str,
    font: ImageFont.FreeTypeFont,
    fill: str,
    tracking: float = 0.0,
) -> None:
    x, y = position
    for char in text:
        draw.text((x, y), char, font=font, fill=fill)
        x += draw.textlength(char, font=font) + tracking


def render_full_logo_png(path: Path, width: int = 2200) -> None:
    min_x, min_y, view_width, view_height = FULL_LOGO_VIEWBOX
    scale = width / view_width
    height = round(view_height * scale)
    image = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    draw = ImageDraw.Draw(image)

    def px(x: float) -> float:
        return (x - min_x) * scale

    def py(y: float) -> float:
        return (y - min_y) * scale

    disk_bounds = (
        px(182 - 128),
        py(204 - 128),
        px(182 + 128),
        py(204 + 128),
    )
    draw.ellipse(disk_bounds, fill=BLACK)
    draw.arc(
        [px(182 - 151), py(204 - 151), px(182 + 151), py(204 + 151)],
        start=38,
        end=322,
        fill=GOLD_FLAT,
        width=max(8, round(18 * scale)),
    )
    draw.arc(
        [px(182 - 132), py(204 - 132), px(182 + 132), py(204 + 132)],
        start=40,
        end=320,
        fill=GOLD_FLAT,
        width=max(3, round(7 * scale)),
    )

    front_face = [(98, 124), (186, 126), (176, 242), (86, 249)]
    side_face = [(86, 126), (61, 136), (66, 230), (86, 249)]
    draw.polygon([(px(x), py(y)) for x, y in side_face], fill="#BB8A34")
    draw.polygon([(px(x), py(y)) for x, y in front_face], fill=GOLD_FLAT)

    handle_box = [px(111 - 36), py(66 - 10), px(182 + 0), py(124 + 10)]
    inner_handle_box = [px(125 - 22), py(82 - 8), px(169 + 0), py(124 + 8)]
    draw.arc(handle_box, start=180, end=360, fill=GOLD_FLAT, width=max(4, round(9 * scale)))
    draw.arc(inner_handle_box, start=180, end=360, fill=GOLD_FLAT, width=max(2, round(5 * scale)))

    for cx in (110, 170):
        draw.ellipse(
            [px(cx - 9), py(124 - 9), px(cx + 9), py(124 + 9)],
            fill=BLACK,
            outline=GOLD_FLAT,
            width=max(2, round(4 * scale)),
        )

    wordmark_size = 202
    subtext_size = 63
    wordmark_font = ImageFont.truetype(str(SANS_FONT), size=round(wordmark_size * scale))
    subtext_font = ImageFont.truetype(str(SANS_FONT), size=round(subtext_size * scale))
    draw.text((px(366), py(52)), "Ysabelle", font=wordmark_font, fill=GOLD_FLAT)
    draw_tracked_text(
        draw,
        (px(423), py(252)),
        "RETAIL SHOP",
        subtext_font,
        GOLD_FLAT,
        tracking=13.5 * scale,
    )

    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path)


def render_badge_png(path: Path, size: int) -> None:
    image = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(image)

    if size <= 16:
        inset_outer = size * 0.03
        inset_inner = size * 0.11
        inset_disk = size * 0.055
        outer_width = 1
        inner_width = 1
        font_scale = 0.72
        text_y_shift = size * 0.02
        ring_color = GOLD_FAVICON
    elif size <= 32:
        inset_outer = size * 0.042
        inset_inner = size * 0.118
        inset_disk = size * 0.068
        outer_width = max(2, round(size * 0.082))
        inner_width = max(1, round(size * 0.041))
        font_scale = 0.66
        text_y_shift = size * 0.012
        ring_color = GOLD_FAVICON
    else:
        inset_outer = size * 0.058
        inset_inner = size * 0.112
        inset_disk = size * 0.086
        outer_width = max(2, round(size * 0.064))
        inner_width = max(1, round(size * 0.031))
        font_scale = 0.6
        text_y_shift = size * 0.008
        ring_color = GOLD_FLAT

    draw.ellipse(
        [inset_disk, inset_disk, size - inset_disk, size - inset_disk],
        fill=BLACK,
    )
    draw.ellipse(
        [inset_outer, inset_outer, size - inset_outer, size - inset_outer],
        outline=ring_color,
        width=outer_width,
    )
    draw.ellipse(
        [inset_inner, inset_inner, size - inset_inner, size - inset_inner],
        outline=ring_color,
        width=inner_width,
    )

    font = ImageFont.truetype(str(SERIF_FONT), size=max(10, round(size * font_scale)))
    bbox = draw.textbbox((0, 0), "YR", font=font)
    text_w = bbox[2] - bbox[0]
    text_h = bbox[3] - bbox[1]
    text_x = (size - text_w) / 2 - bbox[0]
    text_y = (size - text_h) / 2 - bbox[1] - text_y_shift
    draw.text((text_x, text_y), "YR", font=font, fill=ring_color)

    if size <= 32:
        image = image.filter(ImageFilter.SHARPEN)

    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path)


def build_assets() -> None:
    BRAND_DIR.mkdir(parents=True, exist_ok=True)

    full_logo_gold = BRAND_DIR / "ysabelle-logo-gold-flat.svg"
    full_logo_gradient = BRAND_DIR / "ysabelle-logo-gold-gradient.svg"
    full_logo_white = BRAND_DIR / "ysabelle-logo-white.svg"
    full_logo_black = BRAND_DIR / "ysabelle-logo-black.svg"

    badge_gold = BRAND_DIR / "ysabelle-badge-gold-flat.svg"
    badge_gradient = BRAND_DIR / "ysabelle-badge-gold-gradient.svg"
    badge_white = BRAND_DIR / "ysabelle-badge-white.svg"
    badge_black = BRAND_DIR / "ysabelle-badge-black.svg"
    badge_favicon = BRAND_DIR / "ysabelle-badge-favicon.svg"

    write_text(full_logo_gold, build_full_logo_svg(GOLD_FLAT, GOLD_FLAT, GOLD_FLAT, BLACK))
    write_text(full_logo_gradient, build_full_logo_svg("url(#ys-gold-gradient)", "url(#ys-gold-gradient)", "url(#ys-gold-gradient)", BLACK))
    write_text(full_logo_white, build_full_logo_svg(WHITE, WHITE, WHITE, None))
    write_text(full_logo_black, build_full_logo_svg(BLACK, BLACK, BLACK, None))

    write_text(badge_gold, build_badge_svg(GOLD_FLAT, GOLD_FLAT, BLACK, BLACK))
    write_text(badge_gradient, build_badge_svg("url(#ys-gold-gradient)", "url(#ys-gold-gradient)", BLACK, BLACK))
    write_text(badge_white, build_badge_svg(WHITE, WHITE, BLACK, BLACK))
    write_text(badge_black, build_badge_svg(BLACK, BLACK, WHITE))
    write_text(badge_favicon, build_badge_svg("url(#ys-gold-gradient)", "url(#ys-gold-gradient)", BLACK))

    render_full_logo_png(BRAND_DIR / "ysabelle-logo-gold-flat.png")
    render_badge_png(BRAND_DIR / "ysabelle-badge-gold-flat.png", 1024)
    render_badge_png(PUBLIC_DIR / "favicon-16x16.png", 16)
    render_badge_png(PUBLIC_DIR / "favicon-32x32.png", 32)
    render_badge_png(PUBLIC_DIR / "favicon-48x48.png", 48)
    render_badge_png(PUBLIC_DIR / "apple-touch-icon.png", 180)
    render_badge_png(PUBLIC_DIR / "android-192x192.png", 192)
    render_badge_png(PUBLIC_DIR / "android-512x512.png", 512)
    render_badge_png(PUBLIC_DIR / "android-chrome-192x192.png", 192)
    render_badge_png(PUBLIC_DIR / "android-chrome-512x512.png", 512)

    favicon = Image.open(PUBLIC_DIR / "favicon-48x48.png")
    favicon.save(
        PUBLIC_DIR / "favicon.ico",
        format="ICO",
        sizes=[(16, 16), (32, 32), (48, 48)],
    )


if __name__ == "__main__":
    build_assets()
