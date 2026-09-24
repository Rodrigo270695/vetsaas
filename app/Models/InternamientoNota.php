<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $internamiento_id
 * @property Carbon $registrado_at
 * @property string $cuerpo
 */
class InternamientoNota extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'internamiento_notas';

    protected $fillable = [
        'internamiento_id',
        'registrado_at',
        'cuerpo',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'registrado_at' => 'datetime',
        ];
    }

    public function internamiento(): BelongsTo
    {
        return $this->belongsTo(Internamiento::class, 'internamiento_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
