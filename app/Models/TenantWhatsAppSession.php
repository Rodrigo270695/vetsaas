<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantWhatsAppSession extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'tenant_whatsapp_sessions';

    public const STATUS_READY = 'ready';

    protected $fillable = [
        'tenant_id',
        'openwa_session_id',
        'openwa_session_name',
        'status',
        'phone',
        'push_name',
        'connected_at',
        'last_synced_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
