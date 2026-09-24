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
 * @property ?string $tipo
 * @property ?string $solucion
 * @property ?string $via
 * @property ?string $volumen_ml
 * @property ?string $velocidad_ml_h
 * @property ?string $goteo_gtt_min
 * @property ?string $duracion_horas
 * @property ?string $aditivos
 */
class InternamientoFluido extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'internamiento_fluidos';

    protected $fillable = [
        'internamiento_id',
        'registrado_at',
        'tipo',
        'solucion',
        'via',
        'volumen_ml',
        'velocidad_ml_h',
        'goteo_gtt_min',
        'duracion_horas',
        'aditivos',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'registrado_at' => 'datetime',
            'volumen_ml' => 'decimal:1',
            'velocidad_ml_h' => 'decimal:2',
            'goteo_gtt_min' => 'decimal:1',
            'duracion_horas' => 'decimal:1',
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
