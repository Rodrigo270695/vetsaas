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
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
