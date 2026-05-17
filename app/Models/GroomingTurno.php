<?php

namespace App\Models;

use App\Grooming\GroomingCatalogoServicio;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $paciente_id
 * @property ?string $responsable_id
 * @property ?string $sede_id
 * @property \Illuminate\Support\Carbon $inicio_at
 * @property int $duracion_minutos
 * @property string $estado
 * @property string $servicio
 * @property ?string $servicio_detalle
 * @property ?string $notas
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 * @property ?string $venta_id
 */
class GroomingTurno extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const ESTADO_PROGRAMADA = 'programada';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_EN_PROCESO = 'en_proceso';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_CANCELADA = 'cancelada';

    public const ESTADO_NO_ASISTIO = 'no_asistio';

    /** @var list<string> */
    public const ESTADOS = [
        self::ESTADO_PROGRAMADA,
        self::ESTADO_CONFIRMADA,
        self::ESTADO_EN_PROCESO,
        self::ESTADO_COMPLETADA,
        self::ESTADO_CANCELADA,
        self::ESTADO_NO_ASISTIO,
    ];

    protected $table = 'grooming_turnos';

    protected $fillable = [
        'paciente_id',
        'responsable_id',
        'sede_id',
        'inicio_at',
        'duracion_minutos',
        'estado',
        'servicio',
        'servicio_detalle',
        'notas',
        'created_by_id',
        'updated_by_id',
        'venta_id',
    ];

    protected function casts(): array
    {
        return [
            'inicio_at' => 'datetime',
            'duracion_minutos' => 'integer',
        ];
    }

    /**
     * Texto de línea de venta (concepto) según el tipo de servicio del turno.
     */
    public function descripcionParaVenta(): string
    {
        if ($this->servicio === GroomingCatalogoServicio::OTRO_PERSONALIZADO) {
            $d = trim((string) ($this->servicio_detalle ?? ''));

            return $d !== '' ? mb_substr($d, 0, 300) : 'Grooming (personalizado)';
        }

        $slug = $this->servicio;
        $readable = mb_convert_case(str_replace('_', ' ', $slug), MB_CASE_TITLE, 'UTF-8');

        return mb_substr('Grooming · '.$readable, 0, 300);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
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
