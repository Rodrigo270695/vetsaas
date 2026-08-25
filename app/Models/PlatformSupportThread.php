<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * Índice público del hilo «Soporte VetSaaS» por tenant (chat interno).
 */
class PlatformSupportThread extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'platform_support_threads';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'support_user_id',
        'assigned_agent_id',
        'last_message_at',
        'last_preview',
        'from_clinic',
        'clinic_waiting_since',
        'first_response_at',
        'platform_last_read_at',
        'muted_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'from_clinic' => 'boolean',
            'clinic_waiting_since' => 'datetime',
            'first_response_at' => 'datetime',
            'platform_last_read_at' => 'datetime',
            'muted_at' => 'datetime',
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

    public function isMuted(): bool
    {
        return Schema::hasColumn('platform_support_threads', 'muted_at')
            && $this->muted_at !== null;
    }

    /** Minutos esperando respuesta de plataforma (null si no aplica). */
    public function waitingMinutes(): ?int
    {
        if (! Schema::hasColumn('platform_support_threads', 'clinic_waiting_since')) {
            return null;
        }

        if (! $this->from_clinic || $this->clinic_waiting_since === null) {
            return null;
        }

        return (int) max(0, $this->clinic_waiting_since->diffInMinutes(now()));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supportUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'support_user_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(PlatformSupportNote::class, 'tenant_id', 'tenant_id');
    }
}
