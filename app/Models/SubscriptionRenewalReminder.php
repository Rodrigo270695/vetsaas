<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionRenewalReminder extends Model
{
    use HasUuids, UsesPublicSchema;

    public const KIND_7D = '7d';

    public const KIND_3D = '3d';

    public const KIND_1D = '1d';

    public static function kindForDays(int $days): string
    {
        return "{$days}d";
    }

    public const CHANNEL_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'subscription_id',
        'reminder_kind',
        'anchor_at',
        'channel',
        'destinatario',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'anchor_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
