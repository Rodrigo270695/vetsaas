<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $portal_propietario_id
 * @property string $token_hash
 * @property ?string $ip
 * @property ?string $user_agent
 * @property Carbon $expires_at
 * @property ?Carbon $last_seen_at
 * @property ?Carbon $revoked_at
 */
class PortalPropietarioSesion extends Model
{
    use HasUuids;

    protected $table = 'portal_propietario_sesiones';

    protected $hidden = [
        'token_hash',
    ];

    protected $fillable = [
        'portal_propietario_id',
        'token_hash',
        'ip',
        'user_agent',
        'expires_at',
        'last_seen_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function portal(): BelongsTo
    {
        return $this->belongsTo(PortalPropietario::class, 'portal_propietario_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
