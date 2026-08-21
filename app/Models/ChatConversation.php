<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $type
 * @property ?string $name
 * @property ?string $direct_key
 * @property ?string $created_by_id
 */
class ChatConversation extends Model
{
    use HasUuids;

    public const TYPE_DIRECT = 'direct';

    public const TYPE_GROUP = 'group';

    protected $table = 'chat_conversations';

    protected $fillable = [
        'type',
        'name',
        'direct_key',
        'created_by_id',
    ];

    public function isDirect(): bool
    {
        return $this->type === self::TYPE_DIRECT;
    }

    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }
}
