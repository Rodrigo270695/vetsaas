<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $paciente_id
 * @property ?string $veterinario_id
 * @property ?string $sede_id
 * @property \Illuminate\Support\Carbon $inicio_at
 * @property int $duracion_minutos
 * @property string $estado
 * @property ?string $motivo
 * @property ?string $notas
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class Cita extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const ESTADO_PROGRAMADA = 'programada';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_CANCELADA = 'cancelada';

    public const ESTADO_NO_ASISTIO = 'no_asistio';

    /** @var list<string> */
    public const ESTADOS = [
        self::ESTADO_PROGRAMADA,
        self::ESTADO_CONFIRMADA,
        self::ESTADO_COMPLETADA,
        self::ESTADO_CANCELADA,
        self::ESTADO_NO_ASISTIO,
    ];

    protected $table = 'citas';

    protected $fillable = [
        'paciente_id',
        'veterinario_id',
        'sede_id',
        'inicio_at',
        'duracion_minutos',
        'estado',
        'motivo',
        'notas',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'inicio_at' => 'datetime',
            'duracion_minutos' => 'integer',
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

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
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
