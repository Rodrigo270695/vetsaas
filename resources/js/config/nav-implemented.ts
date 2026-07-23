/**
 * Rutas del sidebar que aún no tienen módulo implementado (PlaceholderPage).
 * Se ocultan del menú para evitar pantallas vacías; quitar de esta lista al entregar el módulo.
 */
export const NAV_PLACEHOLDER_PATHS: ReadonlySet<string> = new Set([
    '/caja/pagos',
    '/comunicaciones/plantillas',
    '/reportes/snapshots',
    '/reportes/top-pacientes',
    '/configuracion/horarios',
    '/configuracion/bloqueos',
    '/auditoria/login-attempts',
    '/auditoria/api-logs',
    '/auditoria/tokens',
]);

export function isNavRouteImplemented(href: string): boolean {
    const path = href.split('?')[0] ?? href;

    return !NAV_PLACEHOLDER_PATHS.has(path);
}
