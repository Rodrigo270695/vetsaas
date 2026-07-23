<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use App\Services\Subscriptions\SubscriptionTenantSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'estado',
        'ciclo',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'grace_ends_at',
        'grace_days',
        'cancelled_at',
        'cancel_reason',
        'cancel_feedback',
        'precio_pactado',
        'descuento_pct',
        'promo_code_id',
        'proximo_cobro_at',
        'metodo_pago_token',
        'bot_ia_activo',
        'bot_ia_precio_mensual',
        'bot_ia_activado_at',
    ];

    protected static function booted(): void
    {
        static::saved(function (Subscription $subscription): void {
            app(SubscriptionTenantSync::class)->sync($subscription);
        });
    }

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'grace_ends_at' => 'datetime',
            'grace_days' => 'integer',
            'cancelled_at' => 'datetime',
            'proximo_cobro_at' => 'datetime',
            'bot_ia_activado_at' => 'datetime',
            'precio_pactado' => 'decimal:2',
            'descuento_pct' => 'decimal:2',
            'bot_ia_precio_mensual' => 'decimal:2',
            'bot_ia_activo' => 'boolean',
        ];
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
     * Días de gracia efectivos de esta suscripción.
     * Por defecto usa `billing.grace_days` (3); se puede sobreescribir por tenant.
     */
    public function effectiveGraceDays(): int
    {
        $days = $this->grace_days;

        if ($days === null || (int) $days < 1) {
            return max(1, (int) config('billing.grace_days', 3));
        }

        return max(1, min(90, (int) $days));
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * Suscripciones vivas en planes de pago (excluye Free).
     *
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query
            ->whereIn('estado', ['trial', 'active', 'grace', 'suspended'])
            ->whereHas('plan', fn (Builder $planQuery) => $planQuery->excludingFree());
    }

    public function renewalReminders(): HasMany
    {
        return $this->hasMany(SubscriptionRenewalReminder::class);
    }
}
