import type { Paginated } from '@/types';

export type VentaEstadoFiltro =
    | 'todas'
    | 'pendiente'
    | 'pagado'
    | 'parcial'
    | 'anulado';

export type VentaRow = {
    id: string;
    numero: string;
    numero_display: string;
    estado: string;
    moneda: string;
    total: string;
    subtotal: string;
    igv_monto: string;
    metodo_pago: string | null;
    fel_estado: string;
    created_at: string | null;
    cliente: string;
    paciente: string | null;
    cajero: string;
    sede: string;
};

export type VentasIndexFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: string | null;
    estado: VentaEstadoFiltro;
};

export type VentasIndexProps = {
    ventas: Paginated<VentaRow>;
    filters: VentasIndexFilters;
    stats: {
        total: number;
        coincidencias: number;
    };
};

export type PropietarioOpcion = {
    id: string;
    label: string;
    doc: string | null;
};

export type ClinicaVentaConfig = {
    moneda: string;
    igv_porcentaje: string;
    precio_incluye_igv: boolean;
    emite_comprobantes_sunat: boolean;
    plan_permite_boletas: boolean;
    plan_permite_facturas: boolean;
};

export type MiSesionVenta = {
    id: string;
    sede_id: string;
    sede_nombre: string;
    moneda: string;
};

export type LineaInicialDesdeCargo = {
    producto_id: string | null;
    tipo_linea: string;
    concepto: string;
    cantidad: string;
    precio_lista: string;
    stock_sede: string;
    consulta_cargo_linea_id: string | null;
};

export type DesdeCargoPrefill = {
    consulta_id: string | null;
    consulta_cargo_id: string | null;
    grooming_turno_id?: string | null;
    hotel_estancia_id?: string | null;
    propietario_id: string;
    paciente_id: string | null;
    paciente_nombre: string | null;
    consulta_atendido_at: string | null;
    cargo_total: string;
    lineas_iniciales: LineaInicialDesdeCargo[];
};

export type GeoOption = {
    id: number;
    name: string;
};

export type VentasCreateProps = {
    puede_vender: boolean;
    mi_sesion: MiSesionVenta | null;
    clinica: ClinicaVentaConfig;
    propietarios_opciones: PropietarioOpcion[];
    departamentos: readonly GeoOption[];
    desde_cargo?: DesdeCargoPrefill | null;
};

export type ConsultaVinculoShow = {
    id: string;
    atendido_at: string | null;
    paciente: string | null;
};

export type VentaLineaShow = {
    id: string;
    descripcion: string;
    cantidad: string;
    precio_unitario: string;
    subtotal: string;
    sku: string | null;
    unidad: string | null;
};

export type FelDocumentShow = {
    numero_completo: string;
    estado: string;
    url_pdf: string | null;
    url_xml: string | null;
    enlace_consulta: string | null;
    error_mensaje: string | null;
    emitido_at: string | null;
};

export type VentaDetalle = {
    id: string;
    numero: string;
    estado: string;
    moneda: string;
    subtotal: string;
    igv_monto: string;
    descuento_monto: string;
    total: string;
    metodo_pago: string | null;
    monto_recibido: string | null;
    vuelto: string | null;
    fecha_pago: string | null;
    created_at: string | null;
    notas: string | null;
    fel_estado: string;
    tipo_comprobante_sunat: number | null;
    fel_document: FelDocumentShow | null;
    cliente: string;
    cliente_doc: string | null;
    paciente: string | null;
    cajero: string;
    sede: string;
    lineas: VentaLineaShow[];
};

export type VentaShowProps = {
    venta: VentaDetalle & { consulta_id?: string | null };
    clinica: {
        igv_porcentaje: string;
        ticket_ancho_mm: '58' | '80';
        emite_comprobantes_sunat: boolean;
        apisunat_configurado: boolean;
        plan_permite_boletas: boolean;
        plan_permite_facturas: boolean;
    };
    fel: {
        puede_emitir: boolean;
        emitir_url: string;
        tipo_comprobante: 'boleta' | 'factura';
        serie: string | null;
        doc_cliente: string;
    };
    ticket: {
        puede_imprimir: boolean;
    };
    anulacion: {
        puede_anular: boolean;
        anular_url: string;
        anulado_at: string | null;
        motivo: string | null;
    };
    consulta_vinculo?: ConsultaVinculoShow | null;
};

export type ServicioTarifaBusqueda = {
    nombre: string;
    precio_lista: string;
};

export type ProductoBusqueda = {
    id: string;
    nombre: string;
    sku: string | null;
    precio_venta: string | null;
    unidad: string;
    /** Existencia en la sede de la sesión de caja abierta. */
    stock_sede: string;
};
