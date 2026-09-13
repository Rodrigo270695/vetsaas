<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read ?Distrito $distritoModel
 */
class Tenant extends Model
{
    use HasUuids, SoftDeletes, UsesPublicSchema;

    protected $fillable = [
        'slug',
        'schema_name',
        'razon_social',
        'nombre_comercial',
        'ruc',
        'email_admin',
        'telefono',
            'distrito_id',
            'geo_lat',
            'geo_lng',
            'geo_consent_at',
            'geo_denied_at',
            'geo_captured_at',
            'geo_refresh_requested_at',
            'direccion',
            'logo_url',
        'estado',
        'trial_ends_at',
        'suspended_at',
        'suspension_reason',
        'cancelled_at',
        'cancel_reason',
        'onboarding_completado',
        'onboarding_paso',
        'timezone',
        'locale',
        'canal_adquisicion',
        'referido_por_tenant_id',
        'referral_code',
        'referral_days_balance',
        'modulos_deshabilitados',
    ];

    protected function casts(): array
    {
        return [
            'sunat_configurado' => 'boolean',
            'onboarding_completado' => 'boolean',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'geo_lat' => 'decimal:7',
            'geo_lng' => 'decimal:7',
            'geo_consent_at' => 'datetime',
            'geo_denied_at' => 'datetime',
            'geo_captured_at' => 'datetime',
            'geo_refresh_requested_at' => 'datetime',
            'modulos_deshabilitados' => 'array',
            'referral_days_balance' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class, 'tenant_id');
    }

    /**
     * Distrito vinculado al catálogo oficial.
     *
     * Mismo patrón que `Sede::distritoModel`: se nombra así para no
     * colisionar con eventuales caches denormalizados (`departamento`,
     * `provincia`, `distrito` como strings) que podrían añadirse luego.
     */
    public function distritoModel(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('estado', ['trial', 'active', 'grace'])
            ->latest()
            ->first();
    }

    /**
     * WhatsApp automático (cron / auto-reconnect) solo para planes de pago.
     * Free, demo y clínicas sin suscripción no deben ocupar procesos OpenWA.
     */
    public function qualifiesForPaidWhatsApp(): bool
    {
        $subscription = $this->relationLoaded('subscriptions')
            ? $this->subscriptions
                ->filter(static fn (Subscription $row): bool => in_array($row->estado, ['trial', 'active', 'grace'], true))
                ->sortByDesc(static fn (Subscription $row): int => $row->created_at?->getTimestamp() ?? 0)
                ->first()
            : $this->activeSubscription();

        if (! $subscription instanceof Subscription) {
            $subscription = $this->relationLoaded('subscriptions')
                ? $this->subscriptions->sortByDesc(static fn (Subscription $row): int => $row->created_at?->getTimestamp() ?? 0)->first()
                : $this->subscriptions()->latest()->first();
        }

        if (! $subscription instanceof Subscription) {
            return false;
        }

        $plan = $subscription->relationLoaded('plan')
            ? $subscription->plan
            : $subscription->plan()->first();

        if ($plan instanceof Plan) {
            return ! $plan->isFree();
        }

        return (float) $subscription->precio_pactado > 0;
    }

    public function whatsappSession(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TenantWhatsAppSession::class);
    }

    public function planOverrides(): HasMany
    {
        return $this->hasMany(TenantPlanOverride::class, 'tenant_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referido_por_tenant_id');
    }

    public function referidos(): HasMany
    {
        return $this->hasMany(self::class, 'referido_por_tenant_id');
    }
}
