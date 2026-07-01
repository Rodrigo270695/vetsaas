const BRAND_SCALE_KEYS = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'] as const;

type BrandScaleKey = (typeof BRAND_SCALE_KEYS)[number];

export type BrandScale = Record<BrandScaleKey, string>;

function hexToRgb(hex: string): [number, number, number] | null {
    const normalized = normalizeHex(hex);

    if (!normalized) {
        return null;
    }

    const value = normalized.slice(1);

    return [
        Number.parseInt(value.slice(0, 2), 16),
        Number.parseInt(value.slice(2, 4), 16),
        Number.parseInt(value.slice(4, 6), 16),
    ];
}

function rgbToHex(r: number, g: number, b: number): string {
    const clamp = (channel: number) => Math.max(0, Math.min(255, Math.round(channel)));

    return `#${[clamp(r), clamp(g), clamp(b)]
        .map((channel) => channel.toString(16).padStart(2, '0'))
        .join('')
        .toUpperCase()}`;
}

export function normalizeHex(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    const trimmed = value.trim();

    if (!/^#[0-9A-Fa-f]{6}$/.test(trimmed)) {
        return null;
    }

    return trimmed.toUpperCase();
}

/** Mezcla dos colores hex. `weight` 0 = base, 1 = tint. */
export function mixHex(base: string, tint: string, weight: number): string {
    const from = hexToRgb(base);
    const to = hexToRgb(tint);

    if (!from || !to) {
        return base;
    }

    const ratio = Math.max(0, Math.min(1, weight));

    return rgbToHex(
        from[0] + (to[0] - from[0]) * ratio,
        from[1] + (to[1] - from[1]) * ratio,
        from[2] + (to[2] - from[2]) * ratio,
    );
}

/** Oscurece o aclara un color. Valores negativos oscurecen, positivos aclaran. */
export function shadeHex(hex: string, percent: number): string {
    const rgb = hexToRgb(hex);

    if (!rgb) {
        return hex;
    }

    const factor = 1 + percent / 100;

    return rgbToHex(rgb[0] * factor, rgb[1] * factor, rgb[2] * factor);
}

export function contrastingForeground(hex: string): string {
    const rgb = hexToRgb(hex);

    if (!rgb) {
        return '#FFFFFF';
    }

    const luminance = (0.299 * rgb[0] + 0.587 * rgb[1] + 0.114 * rgb[2]) / 255;

    return luminance > 0.58 ? '#0C0A09' : '#FFFFFF';
}

/**
 * Escala de marca a partir del color primario (botones, hero) y secundario (sidebar, fondos).
 */
export function buildBrandScale(primary: string, secondary: string): BrandScale {
    return {
        50: mixHex(secondary, '#FFFFFF', 0.55),
        100: mixHex(secondary, '#FFFFFF', 0.3),
        200: mixHex(secondary, '#FFFFFF', 0.12),
        300: mixHex(secondary, primary, 0.35),
        400: mixHex(secondary, primary, 0.62),
        500: mixHex('#FFFFFF', primary, 0.82),
        600: primary,
        700: shadeHex(primary, -14),
        800: shadeHex(primary, -28),
        900: shadeHex(primary, -42),
        950: shadeHex(primary, -55),
    };
}

export function applyClinicBrandTheme(
    colorPrimario: string | null | undefined,
    colorSecundario: string | null | undefined,
): void {
    const primary = normalizeHex(colorPrimario);
    const secondary = normalizeHex(colorSecundario);

    if (!primary && !secondary) {
        clearClinicBrandTheme();

        return;
    }

    const resolvedPrimary = primary ?? secondary!;
    const resolvedSecondary = secondary ?? mixHex(resolvedPrimary, '#FFFFFF', 0.72);
    const scale = buildBrandScale(resolvedPrimary, resolvedSecondary);
    const root = document.documentElement;

    for (const key of BRAND_SCALE_KEYS) {
        root.style.setProperty(`--brand-${key}`, scale[key]);
    }

    root.style.setProperty('--primary-foreground', contrastingForeground(resolvedPrimary));
    root.dataset.clinicThemed = 'true';
}

export function clearClinicBrandTheme(): void {
    const root = document.documentElement;

    for (const key of BRAND_SCALE_KEYS) {
        root.style.removeProperty(`--brand-${key}`);
    }

    root.style.removeProperty('--primary-foreground');
    delete root.dataset.clinicThemed;
}
