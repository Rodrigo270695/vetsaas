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
            'modulos_deshabilitados' => 'array',
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

    public function whatsappSession(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TenantWhatsAppSession::class);
    }
}
