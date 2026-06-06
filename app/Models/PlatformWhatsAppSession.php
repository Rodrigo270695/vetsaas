<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformWhatsAppSession extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'platform_whatsapp_sessions';

    public const STATUS_READY = 'ready';

    protected $fillable = [
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

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
