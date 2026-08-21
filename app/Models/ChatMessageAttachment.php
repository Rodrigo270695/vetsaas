<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $message_id
 * @property string $path
 * @property string $name
 * @property ?string $mime
 * @property ?int $size
 * @property-read ?string $url
 */
class ChatMessageAttachment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'chat_message_attachments';

    protected $appends = ['url'];

    protected $fillable = [
        'message_id',
        'path',
        'name',
        'mime',
        'size',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->path
                ? asset('storage/'.ltrim($this->path, '/'))
                : null,
        );
    }

    public function isImage(): bool
    {
        return str_starts_with((string) ($this->mime ?? ''), 'image/');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
