/**
 * Sede predeterminada para formularios nuevos (primera opción activa).
 */
export function resolveDefaultSedeId(
    sedes: readonly { id: string }[],
): string | null {
    const id = sedes[0]?.id?.trim();

    return id ? id : null;
}

export function resolveDefaultSedeIdOrEmpty(
    sedes: readonly { id: string }[],
): string {
    return resolveDefaultSedeId(sedes) ?? '';
}

/** Muestra el selector solo cuando hay más de una sede disponible. */
export function shouldShowSedeSelector(sedes: readonly unknown[]): boolean {
    return sedes.length > 1;
}
