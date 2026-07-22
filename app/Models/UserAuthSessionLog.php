<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial central de inicios/cierres de sesión (login) por usuario y clínica.
 */
class UserAuthSessionLog extends Model
{
    use HasUuids, UsesPublicSchema;

    public const REASON_LOGOUT = 'logout';

    public const REASON_EXPIRED = 'expired';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'session_id',
        'user_name',
        'user_email',
        'tenant_slug',
        'plan_codigo',
        'ip_address',
        'user_agent',
        'logged_in_at',
        'logged_out_at',
        'logout_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'logged_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isOpen(): bool
    {
        return $this->logged_out_at === null;
    }

    public function isFreePlan(): bool
    {
        return $this->plan_codigo === Plan::CODIGO_FREE;
    }

    /**
     * Solo sesiones de usuarios de clínica (excluye superadmins).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeClinicUsers(Builder $query): Builder
    {
        return $query->whereNotNull('tenant_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('logged_out_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('logged_out_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFreePlan(Builder $query): Builder
    {
        return $query->where('plan_codigo', Plan::CODIGO_FREE);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePaidPlan(Builder $query): Builder
    {
        return $query->where('plan_codigo', '!=', Plan::CODIGO_FREE);
    }
}
