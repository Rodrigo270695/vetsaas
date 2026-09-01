<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProductReview extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'rating',
        'comment',
        'author_name',
        'role_label',
        'clinic_name',
        'submitted_at',
        'prompt_dismissed_on',
        'published',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'submitted_at' => 'datetime',
            'prompt_dismissed_on' => 'date',
            'published' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
