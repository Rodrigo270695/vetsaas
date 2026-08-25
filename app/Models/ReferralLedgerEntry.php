<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralLedgerEntry extends Model
{
    use HasUuids, UsesPublicSchema;

    public $timestamps = false;

    protected $table = 'referral_ledger';

    public const TYPE_EARNED = 'earned';

    public const TYPE_APPLIED = 'applied';

    public const TYPE_MANUAL_GRANT = 'manual_grant';

    public const TYPE_MANUAL_ADJUST = 'manual_adjust';

    protected $fillable = [
        'referrer_tenant_id',
        'referred_tenant_id',
        'subscription_payment_id',
        'days',
        'type',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'referrer_tenant_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'referred_tenant_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }
}
