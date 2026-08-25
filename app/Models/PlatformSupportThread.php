<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Índice público del hilo «Soporte VetSaaS» por tenant (chat interno).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $conversation_id
 * @property string $support_user_id
 * @property ?\Illuminate\Support\Carbon $last_message_at
 * @property ?string $last_preview
 * @property bool $from_clinic
 * @property ?\Illuminate\Support\Carbon $platform_last_read_at
 */
class PlatformSupportThread extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'platform_support_threads';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'support_user_id',
        'last_message_at',
        'last_preview',
        'from_clinic',
        'platform_last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'from_clinic' => 'boolean',
            'platform_last_read_at' => 'datetime',
        ];
    }

    public function isUnreadForPlatform(): bool
    {
        if (! Schema::hasColumn('platform_support_threads', 'from_clinic')) {
            return false;
        }

        if (! $this->from_clinic || $this->last_message_at === null) {
            return false;
        }

        if ($this->platform_last_read_at === null) {
            return true;
        }

        return $this->last_message_at->gt($this->platform_last_read_at);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supportUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'support_user_id');
    }
}
