<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $internamiento_id
 * @property \Illuminate\Support\Carbon $registrado_at
 * @property ?string $veterinario_id
 * @property ?string $peso_kg
 * @property ?string $temperatura_c
 * @property ?int $fc_lpm
 * @property ?int $fr_rpm
 * @property string $evolucion
 * @property ?string $tratamiento
 */
class InternamientoEvolucion extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'internamiento_evoluciones';

    protected $fillable = [
        'internamiento_id',
        'registrado_at',
        'veterinario_id',
        'peso_kg',
        'temperatura_c',
        'fc_lpm',
        'fr_rpm',
        'evolucion',
        'tratamiento',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'registrado_at' => 'datetime',
            'temperatura_c' => 'decimal:1',
            'fc_lpm' => 'integer',
            'fr_rpm' => 'integer',
        ];
    }

    public function internamiento(): BelongsTo
    {
        return $this->belongsTo(Internamiento::class, 'internamiento_id');
    }

    public function veterinario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinario_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
