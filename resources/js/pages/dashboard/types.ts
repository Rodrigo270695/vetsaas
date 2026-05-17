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
