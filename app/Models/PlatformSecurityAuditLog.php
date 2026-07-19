<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditoría central de acciones de seguridad (roles, usuarios, etc.).
 * Visible para superadmin; se escribe desde cualquier tenant (schema public).
 */
class PlatformSecurityAuditLog extends Model
{
    use HasUuids, UsesPublicSchema;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_email',
        'tenant_id',
        'tenant_slug',
        'tenant_label',
        'action',
        'modulo',
        'subject_type',
        'subject_id',
        'subject_label',
        'summary',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
