<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $propietario_id
 * @property ?string $pin_hash
 * @property ?Carbon $pin_set_at
 * @property int $pin_failed_attempts
 * @property ?Carbon $pin_locked_until
 * @property ?string $invite_token
 * @property ?Carbon $invite_expires_at
 * @property ?string $telefono_snapshot
 * @property ?string $reset_code_hash
 * @property ?Carbon $reset_code_expires_at
 * @property int $reset_failed_attempts
 * @property ?Carbon $reset_last_sent_at
 * @property int $reset_sent_hour_count
 * @property ?string $invited_by_id
 */
class PortalPropietario extends Model
{
    use HasUuids;

    protected $table = 'portal_propietarios';

    protected $hidden = [
        'pin_hash',
        'reset_code_hash',
        'invite_token',
    ];

    protected $fillable = [
        'propietario_id',
        'pin_hash',
        'pin_set_at',
        'pin_failed_attempts',
        'pin_locked_until',
        'invite_token',
        'invite_expires_at',
        'telefono_snapshot',
        'reset_code_hash',
        'reset_code_expires_at',
        'reset_failed_attempts',
        'reset_last_sent_at',
        'reset_sent_hour_count',
        'invited_by_id',
    ];

    protected function casts(): array
    {
        return [
            'pin_set_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'invite_expires_at' => 'datetime',
            'reset_code_expires_at' => 'datetime',
            'reset_last_sent_at' => 'datetime',
            'pin_failed_attempts' => 'integer',
            'reset_failed_attempts' => 'integer',
            'reset_sent_hour_count' => 'integer',
        ];
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'propietario_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(PortalPropietarioSesion::class, 'portal_propietario_id');
    }

    public function hasPin(): bool
    {
        return filled($this->pin_hash);
    }

    public function inviteIsValid(): bool
    {
        return filled($this->invite_token)
            && $this->invite_expires_at !== null
            && $this->invite_expires_at->isFuture();
    }

    public function isPinLocked(): bool
    {
        return $this->pin_locked_until !== null && $this->pin_locked_until->isFuture();
    }
}
