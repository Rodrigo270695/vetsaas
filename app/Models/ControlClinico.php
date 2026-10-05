<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $paciente_id
 * @property \Illuminate\Support\Carbon $atendido_at
 * @property ?string $motivo
 * @property ?string $anamnesis
 * @property ?string $anamnesis_detalle
 * @property ?string $peso_kg
 * @property ?string $temperatura_c
 * @property ?int $fc_lpm
 * @property ?int $fr_rpm
 * @property ?string $tlc
 * @property ?string $pa
 * @property ?string $hidratacion
 * @property ?string $examen_clinico
 * @property ?string $examen_detalle
 * @property list<string>|null $diagnosticos
 * @property list<array<string, mixed>>|null $examenes
 * @property list<array<string, mixed>>|null $tratamiento
 * @property list<array<string, mixed>>|null $receta
 * @property ?string $comentarios
 * @property ?string $veterinario_id
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class ControlClinico extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'controles';

    protected $fillable = [
        'paciente_id',
        'atendido_at',
        'motivo',
        'anamnesis',
        'anamnesis_detalle',
        'peso_kg',
        'temperatura_c',
        'fc_lpm',
        'fr_rpm',
        'tlc',
        'pa',
        'hidratacion',
        'examen_clinico',
        'examen_detalle',
        'diagnosticos',
        'examenes',
        'tratamiento',
        'receta',
        'comentarios',
        'veterinario_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'atendido_at' => 'datetime',
            'peso_kg' => 'decimal:3',
            'temperatura_c' => 'decimal:2',
            'fc_lpm' => 'integer',
            'fr_rpm' => 'integer',
            'diagnosticos' => 'array',
            'examenes' => 'array',
            'tratamiento' => 'array',
            'receta' => 'array',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function veterinario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinario_id');
    }
}
