<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro central de sesiones de impersonación (modo soporte).
 */
class ImpersonationAuditLog extends Model
{
    use HasUuids, UsesPublicSchema;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'superadmin_id',
        'tenant_id',
        'ip_address',
        'user_agent',
        'central_origin',
        'started_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function superadmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superadmin_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
