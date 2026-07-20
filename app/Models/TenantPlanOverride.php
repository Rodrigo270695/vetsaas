<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Extra / override de límite de plan para un tenant concreto.
 *
 * - `extra`: se suma al límite del plan (ej. +1 usuario, +50 pacientes).
 * - `override`: si no es null, reemplaza el límite del plan (-1 = ilimitado).
 * - `expires_at`: si pasó, el override deja de aplicar.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $feature
 * @property int $extra
 * @property ?int $override
 * @property ?string $motivo
 * @property ?Carbon $expires_at
 * @property ?string $created_by_id
 */
class TenantPlanOverride extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'tenant_plan_overrides';

    protected $fillable = [
        'tenant_id',
        'feature',
        'extra',
        'override',
        'motivo',
        'expires_at',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'extra' => 'integer',
            'override' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isExpired(?Carbon $now = null): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->lte($now ?? now());
    }

    public function isActive(?Carbon $now = null): bool
    {
        return ! $this->isExpired($now);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query, ?Carbon $now = null)
    {
        $now ??= now();

        return $query->where(function ($q) use ($now): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
        });
    }

    public static function forTenantFeature(string $tenantId, string $feature): ?self
    {
        /** @var self|null $row */
        $row = static::query()
            ->where('tenant_id', $tenantId)
            ->where('feature', $feature)
            ->active()
            ->first();

        return $row;
    }
}
