/**
 * Tipos compartidos del módulo Plataforma → Cobros.
 *
 * Este módulo es read-only sobre `subscription_payments`. La fila base
 * la escribe Orvae vía webhook; VetSaaS solo expone acciones humanas
 * de soporte (reembolso manual, nota interna, reenvío de factura).
 */

import type { VencimientoFilter } from '@/lib/subscription-expiry';

/** Estados de un cobro (CHECK constraint en BD). */
export type PaymentEstado =
    | 'pendiente'
    | 'procesado'
    | 'fallido'
    | 'reembolsado';

export type PaymentEstadoFilter = 'todos' | PaymentEstado;

/** Pasarela de pago que procesó el cobro (texto libre del webhook). */
export type PaymentPasarela = string;

/** Mini-tenant referenciado en la fila de pago. */
export type PaymentTenantRef = {
    id: string;
    slug: string;
    razon_social: string;
    nombre_comercial: string | null;
    email_admin: string;
    subscriptions?: PaymentSubscriptionRef[];
};

/** Mini-plan referenciado en la fila de pago. */
export type PaymentPlanRef = {
    id: string;
    codigo: string;
    nombre: string;
    badge: string | null;
    color_hex: string | null;
};

/** Mini-suscripción referenciada en la fila de pago o viva del tenant. */
export type PaymentSubscriptionRef = {
    id: string;
    tenant_id: string;
    plan_id: string;
    estado: string;
    trial_ends_at?: string | null;
    current_period_end?: string | null;
    grace_ends_at?: string | null;
    proximo_cobro_at?: string | null;
    plan?: PaymentPlanRef | null;
};

/** Mini-usuario que marcó el reembolso (cuando aplica). */
export type PaymentRefundedByRef = {
    id: string;
    name: string;
    email: string;
};

export type SubscriptionPayment = {
    /** UUID. */
    id: string;
    subscription_id: string;
    tenant_id: string;
    plan_id: string;
    monto: string;
    moneda: string;
    igv_monto: string;
    descuento_monto: string;
    total: string;
    estado: PaymentEstado;
    pasarela: PaymentPasarela | null;
    pasarela_transaction_id: string | null;
    /** Respuesta cruda de la pasarela. Se renderiza como JSON. */
    pasarela_response: Record<string, unknown> | null;
    periodo_inicio: string | null;
    periodo_fin: string | null;
    fel_emitido: boolean;
    fel_numero: string | null;
    error_mensaje: string | null;
    pagado_at: string | null;
    created_at: string | null;
    /** Campos de soporte interno (no provienen del webhook). */
    internal_note: string | null;
    refunded_at: string | null;
    refunded_by: string | null;
    refund_reason: string | null;
    invoice_resent_at: string | null;
    tenant: PaymentTenantRef | null;
    plan: PaymentPlanRef | null;
    subscription: PaymentSubscriptionRef | null;
    refundedBy: PaymentRefundedByRef | null;
};

export type PaymentStats = {
    total: number;
    procesado: number;
    pendiente: number;
    fallido: number;
    reembolsado: number;
    /** Suma de `total` de los cobros procesados (excluye fallidos/reembolsados). */
    cobrado_total: number;
    /** Coincidencias con los filtros vigentes (todas las páginas). */
    coincidencias: number;
};

export type PaymentFilters = {
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    estado: PaymentEstadoFilter;
    subscription_id: string | null;
    tenant_id: string | null;
    plan_id: string | null;
    vencimiento: VencimientoFilter;
};

export type PaymentPlanOption = {
    id: string;
    codigo: string;
    nombre: string;
    badge: string | null;
    color_hex: string | null;
};

export type PaymentTenantOption = {
    id: string;
    slug: string;
    razon_social: string;
};
