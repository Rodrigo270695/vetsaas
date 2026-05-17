<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de una suscripción al SaaS.
 *
 * La fila base la genera Orvae cuando la pasarela (Niubiz/Culqi/MP)
 * confirma el cobro vía webhook. **VetSaaS no procesa pagos**; solo
 * los lee y los opera para soporte (`reembolso manual`, `nota interna`,
 * `reenvío de factura`).
 *
 * Estados (`estado` con CHECK constraint en BD):
 *   - pendiente    : webhook recibido pero no confirmado
 *   - procesado    : cobro exitoso
 *   - fallido      : la pasarela rechazó
 *   - reembolsado  : devuelto al cliente (manual o vía pasarela)
 *
 * Columnas inmutables (provienen del webhook):
 *   subscription_id, tenant_id, plan_id, monto, total, pasarela,
 *   pasarela_transaction_id, pasarela_response, periodo_*, pagado_at
 *
 * Columnas que el equipo de soporte puede modificar:
 *   estado (cuando se marca reembolso), internal_note, refunded_at,
 *   refunded_by, refund_reason, invoice_resent_at
 *
 * @property-read ?Subscription $subscription
 * @property-read ?Tenant $tenant
 * @property-read ?Plan $plan
 * @property-read ?User $refundedBy
 */
class SubscriptionPayment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'subscription_id',
        'tenant_id',
        'plan_id',
        'monto',
        'moneda',
        'igv_monto',
        'descuento_monto',
        'total',
        'estado',
        'pasarela',
        'pasarela_transaction_id',
        'pasarela_response',
        'periodo_inicio',
        'periodo_fin',
        'fel_emitido',
        'fel_numero',
        'error_mensaje',
        'pagado_at',
        'created_at',
        'internal_note',
        'refunded_at',
        'refunded_by',
        'refund_reason',
        'invoice_resent_at',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'igv_monto' => 'decimal:2',
            'descuento_monto' => 'decimal:2',
            'total' => 'decimal:2',
            'pasarela_response' => 'array',
            'periodo_inicio' => 'datetime',
            'periodo_fin' => 'datetime',
            'pagado_at' => 'datetime',
            'fel_emitido' => 'boolean',
            'created_at' => 'datetime',
            'refunded_at' => 'datetime',
            'invoice_resent_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Usuario del back-office (superadmin) que marcó el reembolso manual.
     */
    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
