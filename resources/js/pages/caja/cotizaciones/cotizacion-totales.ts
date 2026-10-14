/**
 * Alineado con `App\Support\Cotizacion\CotizacionTotales` (PHP).
 */

const round2 = (n: number): number => Math.round(n * 100) / 100;

export function lineaTotal(precio: number, cantidad: number, descuentoPct: number): number {
    if (!(precio > 0) || !(cantidad > 0)) {
        return 0;
    }
    const factor = 1 - Math.min(100, Math.max(0, descuentoPct || 0)) / 100;

    return Math.max(0, round2(precio * cantidad * factor));
}

export function calcularTotales(
    lineas: { precio_unitario: string | number; cantidad: number; descuento_pct: number }[],
    igvPct: number,
    precioIncluyeIgv: boolean,
): { subtotal: number; igv: number; descuento: number; total: number } {
    let neto = 0;
    let bruto = 0;

    for (const l of lineas) {
        const precio = Number(l.precio_unitario) || 0;
        bruto += round2(precio * l.cantidad);
        neto += lineaTotal(precio, l.cantidad, l.descuento_pct);
    }

    neto = round2(neto);
    const descuento = round2(Math.max(0, bruto - neto));

    if (precioIncluyeIgv) {
        const subtotal = igvPct > 0 ? round2(neto / (1 + igvPct / 100)) : neto;

        return { subtotal, igv: round2(neto - subtotal), descuento, total: neto };
    }

    const igv = round2(neto * (igvPct / 100));

    return { subtotal: neto, igv, descuento, total: round2(neto + igv) };
}
