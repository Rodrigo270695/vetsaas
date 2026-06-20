/** Rutas de Caja disponibles offline (navegación + SW). */
export const CAJA_OFFLINE_PATHS = [
    '/caja/sesiones',
    '/caja/ventas',
    '/caja/ventas/nuevo',
    '/caja/pagos',
    '/caja/descuentos',
] as const;

export function normalizeOfflinePath(path: string): string {
    return path.split('?')[0]?.replace(/\/$/, '') || '/';
}

export function isCajaOfflinePath(path: string): boolean {
    const normalized = normalizeOfflinePath(path);

    return (
        normalized === '/caja' ||
        CAJA_OFFLINE_PATHS.some(
            (allowed) =>
                normalized === allowed || normalized.startsWith(`${allowed}/`),
        )
    );
}
