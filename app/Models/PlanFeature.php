<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    use HasUuids, UsesPublicSchema;

    public $timestamps = false;

    protected $fillable = [
        'plan_id',
        'feature',
        'valor_int',
        'valor_bool',
        'valor_str',
    ];

    protected function casts(): array
    {
        return [
            'valor_bool' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
