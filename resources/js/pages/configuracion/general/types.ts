/**
 * Tipos del módulo Configuración → General.
 *
 * El recurso es singleton: hay una sola fila por tenant en
 * `cfg_clinic_settings`. Estos tipos describen el payload que el
 * controller emite a Inertia y el shape del formulario en el cliente.
 */

export type AuditUser = {
    id: string;
    name: string;
    email: string;
} | null;

/**
 * Cadena geográfica eager-loaded para mostrar la ubicación fiscal
 * completa (departamento → provincia → distrito).
 */
export type DistritoChain = {
    id: number;
    name: string;
    provincia_id: number;
    provincia: {
        id: number;
        name: string;
        departamento_id: number;
        departamento: {
            id: number;
            name: string;
        };
    };
} | null;

/**
 * Snapshot que el backend manda al frontend.
 *
 * Alcance: SOLO datos del cliente. Las credenciales globales (Twilio y
 * Brevo) ya NO viven aquí; viajan via el payload de
 * `plataforma/configuracion`. El cliente solo conserva Nubefact (su
 * propio RUC/token) y las preferencias de "remitente comercial visible".
 *
 * Las credenciales sensibles (Nubefact) JAMÁS viajan en claro. Solo se
 * expone el flag booleano `nubefact_configurado` para que la UI pueda
 * indicar visualmente si hay clave guardada.
 */
export type ClinicSetting = {
    id: string;
    // Identidad fiscal
    ruc: string | null;
    razon_social: string | null;
    nombre_comercial: string | null;
    direccion_fiscal: string | null;
    distrito_id: number | null;
    distrito_model: DistritoChain;
    // Branding
    //   `logo_url` se calcula en el backend desde `logo_path` (disco
    //   `public`). El frontend solo lee la URL pública para mostrar la
    //   imagen. La subida real va por el campo `logo` del form-data.
    logo_url: string | null;
    color_primario: string | null;
    color_secundario: string | null;
    // Contacto
    email_institucional: string | null;
    telefono_principal: string | null;
    web_url: string | null;
    // Operación (citas y agenda)
    duracion_cita_default_min: number;
    intervalo_agenda_min: number;
    dias_anticipacion_cita: number;
    horas_min_cancelacion: number;
    // Recordatorios automáticos
    recordatorio_48h_activo: boolean;
    recordatorio_2h_activo: boolean;
    recordatorio_vacuna_activo: boolean;
    recordatorio_vacuna_dias_antes: number;
    recordatorio_cumple_activo: boolean;
    // Facturación
    moneda: 'PEN' | 'USD';
    igv_porcentaje: string;
    precio_incluye_igv: boolean;
    ticket_ancho_mm: '58' | '80';
    emite_comprobantes_sunat: boolean;
    // Nubefact (única integración del cliente)
    nubefact_ruc: string | null;
    nubefact_configurado: boolean;
    // Remitente comercial visible (NO autentica con Twilio/Brevo, solo
    // personaliza la firma de los mensajes que envía la plataforma).
    whatsapp_display_number: string | null;
    email_from: string | null;
    email_from_nombre: string | null;
    // Audit
    updated_at: string | null;
    actualizado_por: AuditUser;
};

export type GeoOption = {
    id: number;
    name: string;
};

/**
 * Snapshot del tenant activo que el backend manda en cada render
 * para que la página muestre el nombre comercial en el subtítulo.
 */
export type TenantSnapshot = {
    id: string;
    slug: string;
    razon_social: string | null;
    nombre_comercial: string | null;
} | null;
