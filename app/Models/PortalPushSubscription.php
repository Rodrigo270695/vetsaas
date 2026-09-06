<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalPushSubscription extends Model
{
    use HasUuids;

    protected $table = 'portal_push_subscriptions';

    protected $fillable = [
        'portal_propietario_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
    ];

    public function portal(): BelongsTo
    {
        return $this->belongsTo(PortalPropietario::class, 'portal_propietario_id');
    }
}
