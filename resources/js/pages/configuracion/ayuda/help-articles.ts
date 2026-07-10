export type HelpArticleConfig = {
    id: string;
    href?: string;
    permission?: string;
};

export type HelpCategoryConfig = {
    id: string;
    articles: HelpArticleConfig[];
};

/** Estructura del centro de ayuda (textos en i18n namespace `ayuda`). */
export const HELP_CATEGORIES: HelpCategoryConfig[] = [
    {
        id: 'setup',
        articles: [
            { id: 'sede', href: '/configuracion/sedes', permission: 'sedes.view' },
            { id: 'clinic', href: '/configuracion/general', permission: 'config-general.view' },
            { id: 'paciente', href: '/clinica/pacientes', permission: 'pacientes.view' },
        ],
    },
    {
        id: 'clinic',
        articles: [
            { id: 'cita', href: '/clinica/citas', permission: 'citas.view' },
            { id: 'consulta', href: '/clinica/historias-clinicas', permission: 'historias-clinicas.view' },
            { id: 'vacuna', href: '/clinica/vacunaciones', permission: 'vacunaciones.view' },
        ],
    },
    {
        id: 'caja',
        articles: [
            { id: 'sesion', href: '/caja/sesiones', permission: 'caja-sesiones.view' },
            { id: 'venta', href: '/caja/ventas/create', permission: 'ventas.create' },
        ],
    },
    {
        id: 'inventario',
        articles: [
            { id: 'producto', href: '/inventario/productos', permission: 'productos.view' },
            { id: 'stock', href: '/inventario/stock', permission: 'stock.view' },
            { id: 'compra', href: '/inventario/compras', permission: 'compras.view' },
        ],
    },
    {
        id: 'facturacion',
        articles: [
            { id: 'fel', href: '/configuracion/general', permission: 'config-general.view' },
            { id: 'series', href: '/facturacion/series', permission: 'series.view' },
            { id: 'comprobantes', href: '/facturacion/documentos', permission: 'documentos.view' },
        ],
    },
];
