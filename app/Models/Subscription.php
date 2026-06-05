<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use App\Services\Subscriptions\SubscriptionTenantSync;
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
        'cancelled_at',
        'cancel_reason',
        'cancel_feedback',
        'precio_pactado',
        'descuento_pct',
        'promo_code_id',
        'proximo_cobro_at',
        'metodo_pago_token',
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
            'cancelled_at' => 'datetime',
            'proximo_cobro_at' => 'datetime',
            'precio_pactado' => 'decimal:2',
            'descuento_pct' => 'decimal:2',
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

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
