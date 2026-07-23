/** Rutas de Caja disponibles offline (navegación + SW). */
export const CAJA_OFFLINE_PATHS = [
    '/caja/sesiones',
    '/caja/ventas',
    '/caja/ventas/nuevo',
    '/caja/pagos',
    '/caja/descuentos',
] as const;

/** Rutas de Clínica disponibles offline (Fase 3–6). */
export const CLINICA_OFFLINE_PATHS = [
    '/clinica/citas',
    '/clinica/pacientes',
    '/clinica/propietarios',
    '/clinica/historias-clinicas',
    '/clinica/vacunaciones',
    '/clinica/cirugias',
    '/clinica/hospitalizacion',
    '/clinica/recetas',
    '/clinica/laboratorio',
] as const;

/** Rutas de Servicios disponibles offline (Fase 5). */
export const SERVICIOS_OFFLINE_PATHS = ['/servicios/grooming', '/servicios/hotel'] as const;

/** Rutas de Inventario disponibles offline (Fase 4). */
export const INVENTARIO_OFFLINE_PATHS = [
    '/inventario/productos',
    '/inventario/categorias',
    '/inventario/movimientos',
    '/inventario/compras',
    '/inventario/proveedores',
    '/inventario/stock',
    '/inventario/alertas',
] as const;

/** Rutas de Facturación navegables offline (solo lectura; emisión SUNAT requiere internet). */
export const FACTURACION_OFFLINE_PATHS = [
    '/facturacion/documentos',
    '/facturacion/series',
    '/facturacion/notas-baja',
    '/facturacion/resumenes',
] as const;

/** Rutas de Comunicaciones navegables offline (consulta cacheada; WhatsApp/cola requiere internet). */
export const COMUNICACIONES_OFFLINE_PATHS = [
    '/comunicaciones/cola',
    '/comunicaciones/historico',
    '/comunicaciones/plantillas',
] as const;

/** Rutas de Reportes navegables offline (consulta de última snapshot cacheada). */
export const REPORTES_OFFLINE_PATHS = [
    '/reportes/snapshots',
    '/reportes/top-pacientes',
] as const;

/** Rutas de Configuración navegables offline (alta de sedes; edición avanzada requiere internet). */
export const CONFIGURACION_OFFLINE_PATHS = [
    '/configuracion/general',
    '/configuracion/sedes',
    '/configuracion/usuarios',
    '/configuracion/roles',
    '/configuracion/tarifas',
    '/configuracion/horarios',
    '/configuracion/bloqueos',
    '/configuracion/suscripcion',
] as const;

/** Centro de sincronización offline (Fase 8). */
export const OFFLINE_SYNC_PATHS = ['/offline/cola'] as const;

export const OFFLINE_PATHS = [
    ...OFFLINE_SYNC_PATHS,
    ...CAJA_OFFLINE_PATHS,
    ...CLINICA_OFFLINE_PATHS,
    ...SERVICIOS_OFFLINE_PATHS,
    ...INVENTARIO_OFFLINE_PATHS,
    ...FACTURACION_OFFLINE_PATHS,
    ...COMUNICACIONES_OFFLINE_PATHS,
    ...REPORTES_OFFLINE_PATHS,
    ...CONFIGURACION_OFFLINE_PATHS,
] as const;

export function normalizeOfflinePath(path: string): string {
    return path.split('?')[0]?.replace(/\/$/, '') || '/';
}

function matchesPrefix(path: string, allowed: readonly string[]): boolean {
    return allowed.some(
        (prefix) => path === prefix || path.startsWith(`${prefix}/`),
    );
}

export function isCajaOfflinePath(path: string): boolean {
    const normalized = normalizeOfflinePath(path);

    return normalized === '/caja' || matchesPrefix(normalized, CAJA_OFFLINE_PATHS);
}

export function isClinicaOfflinePath(path: string): boolean {
    const normalized = normalizeOfflinePath(path);

    return normalized === '/clinica' || matchesPrefix(normalized, CLINICA_OFFLINE_PATHS);
}

export function isServiciosOfflinePath(path: string): boolean {
    const normalized = normalizeOfflinePath(path);

    return normalized === '/servicios' || matchesPrefix(normalized, SERVICIOS_OFFLINE_PATHS);
}

export function isInventarioOfflinePath(path: string): boolean {
    const normalized = normalizeOfflinePath(path);

    return normalized === '/inventario' || matchesPrefix(normalized, INVENTARIO_OFFLINE_PATHS);
}

export function isFacturacionOfflinePath(path: string): boolean {
    const normalized = normalizeOfflinePath(path);

    return normalized === '/facturacion' || matchesPrefix(normalized, FACTURACION_OFFLINE_PATHS);
}

export function isComunicacionesOfflinePath(path: string): boolean {
    const normalized = normalizeOfflinePath(path);

    return normalized === '/comunicaciones' || matchesPrefix(normalized, COMUNICACIONES_OFFLINE_PATHS);
}

export function isReportesOfflinePath(path: string): boolean {
    const normalized = normalizeOfflinePath(path);

    return normalized === '/reportes' || matchesPrefix(normalized, REPORTES_OFFLINE_PATHS);
}

export function isConfiguracionOfflinePath(path: string): boolean {
    const normalized = normalizeOfflinePath(path);

    return normalized === '/configuracion' || matchesPrefix(normalized, CONFIGURACION_OFFLINE_PATHS);
}

export function isOfflineSyncPath(path: string): boolean {
    const normalized = normalizeOfflinePath(path);

    return normalized === '/offline' || matchesPrefix(normalized, OFFLINE_SYNC_PATHS);
}

export function isOfflinePath(path: string): boolean {
    return (
        isOfflineSyncPath(path) ||
        isCajaOfflinePath(path) ||
        isClinicaOfflinePath(path) ||
        isServiciosOfflinePath(path) ||
        isInventarioOfflinePath(path) ||
        isFacturacionOfflinePath(path) ||
        isComunicacionesOfflinePath(path) ||
        isReportesOfflinePath(path) ||
        isConfiguracionOfflinePath(path)
    );
}
