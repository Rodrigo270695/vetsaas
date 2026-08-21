<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $user_id
 * @property ?string $reply_to_id
 * @property ?string $body
 * @property ?array $mentioned_user_ids
 * @property ?string $attachment_path
 * @property ?string $attachment_name
 * @property ?string $attachment_mime
 * @property ?int $attachment_size
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read ?string $attachment_url
 * @property-read ?ChatMessage $replyTo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ChatMessageAttachment> $attachments
 */
class ChatMessage extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'chat_messages';

    protected $appends = [
        'attachment_url',
    ];

    protected $fillable = [
        'conversation_id',
        'user_id',
        'reply_to_id',
        'body',
        'mentioned_user_ids',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'attachment_size' => 'integer',
            'mentioned_user_ids' => 'array',
        ];
    }

    protected function attachmentUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attachment_path
                ? asset('storage/'.ltrim($this->attachment_path, '/'))
                : null,
        );
    }

    public function isImage(): bool
    {
        $mime = (string) ($this->attachment_mime ?? '');

        return str_starts_with($mime, 'image/');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatMessageAttachment::class, 'message_id');
    }
}
