<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClosingQueueWhatsAppSend extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'closing_queue_whatsapp_sends';

    protected $fillable = [
        'row_key',
        'kind',
        'phone',
        'from_phone',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
