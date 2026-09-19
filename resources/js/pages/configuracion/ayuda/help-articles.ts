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
            { id: 'firma_digital', href: '/configuracion/general', permission: 'config-general.view' },
            { id: 'autorizaciones', href: '/configuracion/documentos-autorizacion', permission: 'config-general.view' },
            { id: 'usuarios_roles', href: '/configuracion/usuarios', permission: 'usuarios.view' },
            { id: 'tarifas', href: '/configuracion/tarifas', permission: 'tarifas.view' },
            { id: 'horarios', href: '/configuracion/horarios', permission: 'horarios.view' },
            { id: 'modo_asesora', href: '/configuracion/general', permission: 'config-general.view' },
        ],
    },
    {
        id: 'clinic',
        articles: [
            { id: 'sala_espera', href: '/clinica/sala-espera', permission: 'sala-espera.view' },
            { id: 'propietario', href: '/clinica/propietarios', permission: 'propietarios.view' },
            { id: 'paciente', href: '/clinica/pacientes', permission: 'pacientes.view' },
            { id: 'cita', href: '/clinica/citas', permission: 'citas.view' },
            { id: 'consulta', href: '/clinica/historias-clinicas', permission: 'historias-clinicas.view' },
            { id: 'receta', href: '/clinica/recetas', permission: 'recetas.view' },
            { id: 'receta_whatsapp', href: '/clinica/recetas', permission: 'recetas.view' },
            { id: 'vacuna', href: '/clinica/vacunaciones', permission: 'vacunaciones.view' },
            { id: 'laboratorio', href: '/clinica/laboratorio', permission: 'laboratorio.view' },
            { id: 'cirugia', href: '/clinica/cirugias', permission: 'cirugias.view' },
            { id: 'hospitalizacion', href: '/clinica/hospitalizacion', permission: 'hospitalizacion.view' },
        ],
    },
    {
        id: 'servicios',
        articles: [
            { id: 'agenda_servicios', href: '/servicios/agenda', permission: 'servicios-agenda.view' },
            { id: 'grooming', href: '/servicios/grooming', permission: 'grooming.view' },
            { id: 'hotel', href: '/servicios/hotel', permission: 'hotel.view' },
        ],
    },
    {
        id: 'caja',
        articles: [
            { id: 'sesion', href: '/caja/sesiones', permission: 'caja-sesiones.view' },
            { id: 'venta', href: '/caja/ventas', permission: 'ventas.view' },
            { id: 'venta_whatsapp', href: '/caja/ventas', permission: 'ventas.view' },
            { id: 'pagos', href: '/caja/pagos', permission: 'pagos.view' },
            { id: 'descuentos', href: '/caja/descuentos', permission: 'descuentos.view' },
        ],
    },
    {
        id: 'inventario',
        articles: [
            { id: 'producto', href: '/inventario/productos', permission: 'productos.view' },
            { id: 'stock', href: '/inventario/stock', permission: 'stock.view' },
            { id: 'movimientos', href: '/inventario/movimientos', permission: 'movimientos-stock.view' },
            { id: 'alertas', href: '/inventario/alertas', permission: 'alertas-stock.view' },
            { id: 'proveedores', href: '/inventario/proveedores', permission: 'proveedores.view' },
            { id: 'compra', href: '/inventario/compras', permission: 'compras.view' },
        ],
    },
    {
        id: 'facturacion',
        articles: [
            { id: 'fel', href: '/configuracion/general', permission: 'config-general.view' },
            { id: 'series', href: '/facturacion/series', permission: 'series.view' },
            { id: 'comprobantes', href: '/facturacion/documentos', permission: 'documentos.view' },
            { id: 'notas_baja', href: '/facturacion/notas-baja', permission: 'notas-baja.view' },
        ],
    },
    {
        id: 'comunicaciones',
        articles: [
            { id: 'whatsapp_conectar', href: '/comunicaciones/cola', permission: 'comunicaciones-cola.view' },
            { id: 'cola', href: '/comunicaciones/cola', permission: 'comunicaciones-cola.view' },
            { id: 'campanas', href: '/comunicaciones/campanas', permission: 'comunicaciones-campanas.view' },
            { id: 'historico', href: '/comunicaciones/historico', permission: 'comunicaciones-historico.view' },
            { id: 'plantillas', href: '/comunicaciones/plantillas', permission: 'plantillas.view' },
            { id: 'chat_interno', href: '/comunicaciones/chat', permission: 'comunicaciones-chat.view' },
            { id: 'bot_ia', href: '/comunicaciones/bot-ia', permission: 'comunicaciones-bot-ia.view' },
        ],
    },
    {
        id: 'reportes',
        articles: [
            { id: 'snapshots', href: '/reportes/snapshots', permission: 'snapshots.view' },
            { id: 'financiero', href: '/reportes/financiero', permission: 'reporte-financiero.view' },
        ],
    },
    {
        id: 'app',
        articles: [
            { id: 'instalar_app' },
            { id: 'app_splash' },
            { id: 'offline' },
        ],
    },
    {
        id: 'faq',
        articles: [
            { id: 'faq_splash' },
            { id: 'faq_whatsapp_campana', permission: 'comunicaciones-campanas.view' },
            { id: 'faq_whatsapp_sesion', permission: 'comunicaciones-cola.view' },
            { id: 'faq_receta_enviar', permission: 'recetas.view' },
            { id: 'faq_firma', permission: 'config-general.view' },
            { id: 'faq_caja' },
            { id: 'faq_recordatorios' },
        ],
    },
];
