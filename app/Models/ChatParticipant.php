<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $user_id
 * @property ?\Illuminate\Support\Carbon $last_read_at
 * @property ?\Illuminate\Support\Carbon $muted_at
 * @property \Illuminate\Support\Carbon $joined_at
 */
class ChatParticipant extends Model
{
    use HasUuids;

    protected $table = 'chat_participants';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'last_read_at',
        'muted_at',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'muted_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
