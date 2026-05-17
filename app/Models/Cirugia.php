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
 * @property ?string $consulta_id
 * @property ?string $veterinario_id
 * @property ?string $sede_id
 * @property \Illuminate\Support\Carbon $programada_at
 * @property string $estado
 * @property string $nombre_procedimiento
 * @property ?string $tipo_anestesia
 * @property ?string $observaciones
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class Cirugia extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_PROGRAMADA = 'programada';

    public const ESTADO_EN_PROCESO = 'en_proceso';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_CANCELADA = 'cancelada';

    /** @var list<string> */
    public const ESTADOS = [
        self::ESTADO_BORRADOR,
        self::ESTADO_PROGRAMADA,
        self::ESTADO_EN_PROCESO,
        self::ESTADO_COMPLETADA,
        self::ESTADO_CANCELADA,
    ];

    /** @var list<string> */
    public const ESTADOS_CREACION = [
        self::ESTADO_BORRADOR,
        self::ESTADO_PROGRAMADA,
    ];

    protected $table = 'cirugias';

    protected $fillable = [
        'paciente_id',
        'consulta_id',
        'veterinario_id',
        'sede_id',
        'programada_at',
        'estado',
        'nombre_procedimiento',
        'tipo_anestesia',
        'observaciones',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'programada_at' => 'datetime',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class, 'consulta_id');
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
