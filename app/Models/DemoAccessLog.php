<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acceso al tenant demo con origen aproximado (GPS del navegador o solo IP).
 */
class DemoAccessLog extends Model
{
    use HasUuids, UsesPublicSchema;

    public $timestamps = false;

    protected $fillable = [
        'lat',
        'lng',
        'ip',
        'user_agent',
        'user_id',
        'clinic_name',
        'phone',
        'email',
        'lead_captured_at',
        'lead_skipped_at',
        'outreach_sent_at',
        'outreach_channel',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'lead_captured_at' => 'datetime',
            'lead_skipped_at' => 'datetime',
            'outreach_sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
