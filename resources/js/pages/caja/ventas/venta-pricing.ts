/**
 * Cálculo de importes de venta según `cfg_clinic_settings.precio_incluye_igv`.
 * Debe mantenerse alineado con `VentaCheckoutService` (PHP).
 */

export function precioUnitarioSinIgv(
    precioLista: number,
    igvPct: number,
    precioIncluyeIgv: boolean,
): number {
    if (precioLista <= 0) {
        return 0;
    }

    if (!precioIncluyeIgv) {
        return Math.round(precioLista * 10000) / 10000;
    }

    const divisor = 1 + igvPct / 100;

    if (divisor <= 0) {
        return Math.round(precioLista * 10000) / 10000;
    }

    return Math.round((precioLista / divisor) * 10000) / 10000;
}

/** Importe de la línea que paga el cliente (precio de lista × cantidad si incluye IGV). */
export function lineTotalLinea(
    precioLista: number,
    cantidad: number,
    igvPct: number,
    precioIncluyeIgv: boolean,
    descuentoPct = 0,
): number {
    if (precioLista <= 0 || cantidad <= 0) {
        return 0;
    }

    const factorDescuento = 1 - Math.min(100, Math.max(0, descuentoPct)) / 100;

    if (precioIncluyeIgv) {
        return Math.round(precioLista * cantidad * factorDescuento * 100) / 100;
    }

    const pu = precioUnitarioSinIgv(precioLista, igvPct, false);
    const base = Math.round(pu * cantidad * factorDescuento * 100) / 100;
    const igv = Math.round(base * (igvPct / 100) * 100) / 100;

    return Math.round((base + igv) * 100) / 100;
}

/** Importe bruto de línea a partir del subtotal (sin IGV) ya descontado. */
export function lineTotalFromSubtotal(
    subtotal: number,
    igvPct: number,
    precioIncluyeIgv: boolean,
): number {
    if (subtotal <= 0) {
        return 0;
    }

    if (precioIncluyeIgv) {
        return Math.round(subtotal * (1 + igvPct / 100) * 100) / 100;
    }

    const igv = Math.round(subtotal * (igvPct / 100) * 100) / 100;

    return Math.round((subtotal + igv) * 100) / 100;
}

export function calcTotalesVenta(
    lineas: {
        precio_venta: string | null;
        cantidad: number;
        descuento_pct?: number;
    }[],
    igvPct: number,
    precioIncluyeIgv: boolean,
): { subtotal: number; igv: number; total: number; discount: number } {
    if (precioIncluyeIgv) {
        const divisor = 1 + igvPct / 100;
        let sub = 0;
        let total = 0;
        let originalTotal = 0;

        for (const line of lineas) {
            const lista = Number(line.precio_venta ?? 0);
            const factor =
                1 - Math.min(100, Math.max(0, line.descuento_pct ?? 0)) / 100;
            originalTotal += Math.round(lista * line.cantidad * 100) / 100;
            const gross =
                Math.round(lista * line.cantidad * factor * 100) / 100;
            const lineBase =
                divisor > 0 ? Math.round((gross / divisor) * 100) / 100 : gross;
            sub += lineBase;
            total += gross;
        }

        sub = Math.round(sub * 100) / 100;
        total = Math.round(total * 100) / 100;
        const igv = Math.round((total - sub) * 100) / 100;

        return {
            subtotal: sub,
            igv,
            total,
            discount: Math.round((originalTotal - total) * 100) / 100,
        };
    }

    let sub = 0;
    let originalSub = 0;

    for (const line of lineas) {
        const lista = Number(line.precio_venta ?? 0);
        const pu = precioUnitarioSinIgv(lista, igvPct, false);
        const factor =
            1 - Math.min(100, Math.max(0, line.descuento_pct ?? 0)) / 100;
        originalSub += pu * line.cantidad;
        sub += pu * line.cantidad * factor;
    }

    sub = Math.round(sub * 100) / 100;
    const igv = Math.round(sub * (igvPct / 100) * 100) / 100;
    const total = Math.round((sub + igv) * 100) / 100;

    const originalTotal = originalSub * (1 + igvPct / 100);

    return {
        subtotal: sub,
        igv,
        total,
        discount: Math.round((originalTotal - total) * 100) / 100,
    };
}
