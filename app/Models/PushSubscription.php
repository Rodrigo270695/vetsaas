<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suscripción Web Push (panel central / superadmin).
 *
 * @property int $id
 * @property string $user_id
 * @property string $endpoint
 * @property string|null $public_key
 * @property string $auth_token
 * @property string $content_encoding
 */
final class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';

    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
