export type DashboardCapabilities = {
    citas: boolean;
    consultas: boolean;
    ventas: boolean;
    pacientes: boolean;
    propietarios: boolean;
    vacunaciones: boolean;
    grooming: boolean;
    hotel: boolean;
    hospitalizacion: boolean;
    productos: boolean;
    alertas_stock: boolean;
    caja_sesiones: boolean;
};

export type DashboardKpis = {
    citas_hoy: number;
    citas_pendientes_hoy: number;
    consultas_hoy: number;
    consultas_abiertas: number;
    ventas_hoy_count: number;
    ventas_hoy_total: string;
    pacientes_nuevos_mes: number;
    propietarios_nuevos_mes: number;
    vacunaciones_mes: number;
    grooming_hoy: number;
    hotel_en_estancia: number;
    internamientos_activos: number;
    fel_pendientes: number;
    productos_activos: number;
    alertas_stock: number;
    caja_abierta: boolean;
};

export type VentasPorDiaRow = {
    date: string;
    label: string;
    total: number;
    count: number;
};

export type ConsultasPorDiaRow = {
    date: string;
    label: string;
    count: number;
};

export type VentasPorMetodoRow = {
    metodo: string;
    count: number;
    total: number;
};

export type CitasPorEstadoRow = {
    estado: string;
    count: number;
};

export type ProximaCitaRow = {
    id: string;
    inicio_at: string | null;
    estado: string;
    motivo: string | null;
    paciente_nombre: string | null;
    veterinario_nombre: string | null;
    sede_nombre: string | null;
};

export type IngresosMensualRow = {
    month: string;
    label: string;
    total: number;
    count: number;
    is_current: boolean;
};

export type ComparacionIngresosMes = {
    mes_actual_total: number;
    mes_anterior_total: number;
    variacion_pct: number | null;
    mes_actual_count: number;
    mes_anterior_count: number;
    ticket_promedio_actual: number;
    ticket_promedio_anterior: number;
};

export type TopProductoRow = {
    nombre: string;
    total: number;
    cantidad: number;
};

export type FelEstadoRow = {
    estado: string;
    count: number;
};

export type VacunacionesPorDiaRow = {
    date: string;
    label: string;
    count: number;
};

export type NuevosClientesMensualRow = {
    month: string;
    label: string;
    pacientes: number;
    propietarios: number;
    is_current: boolean;
};

export type RentabilidadPeriodo = 'semana' | 'mes_actual' | 'mes_pasado';

export type RentabilidadItemRow = {
    nombre: string;
    ingreso: number;
    costo: number;
    ganancia: number;
    cantidad: number;
    margen_pct: number | null;
};

export type RentabilidadComprobanteFiltros = {
    boleta: boolean;
    factura: boolean;
    ticket: boolean;
};

export type RentabilidadComprobanteSlice = {
    ingresos: number;
    costo: number;
    ganancia: number;
    margen_pct: number | null;
    unidades: number;
};

export type RentabilidadPorComprobante = {
    boleta: RentabilidadComprobanteSlice;
    factura: RentabilidadComprobanteSlice;
    ticket: RentabilidadComprobanteSlice;
};

export type RentabilidadResumen = {
    periodo: RentabilidadPeriodo;
    desde: string;
    hasta: string;
    filtros: RentabilidadComprobanteFiltros;
    ingresos: number;
    costo: number;
    ganancia: number;
    margen_pct: number | null;
    unidades: number;
    productos_sin_costo: number;
    por_comprobante: RentabilidadPorComprobante;
    items: RentabilidadItemRow[];
};

export type OnboardingStep = {
    id: string;
    title: string;
    description: string;
    href: string | null;
    completed: boolean;
    current: boolean;
    locked: boolean;
    required: boolean;
};

export type OnboardingSnapshot = {
    show: boolean;
    completed: boolean;
    paso: number;
    total_steps: number;
    completed_steps: number;
    requires_sede: boolean;
    preview: boolean;
    steps: OnboardingStep[];
};

export type RentabilidadGroomingResumen = {
    periodo: RentabilidadPeriodo;
    desde: string;
    hasta: string;
    filtros: RentabilidadComprobanteFiltros;
    ingresos: number;
    costo: number;
    ganancia: number;
    margen_pct: number | null;
    unidades: number;
    servicios_sin_insumos: number;
    por_comprobante: RentabilidadPorComprobante;
    items: RentabilidadItemRow[];
};
