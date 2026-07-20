<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $paciente_id
 * @property ?string $veterinario_id
 * @property ?string $sede_id
 * @property Carbon $inicio_at
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

    /** Paciente con el doctor / consulta abierta. */
    public const ESTADO_EN_ATENCION = 'en_atencion';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_CANCELADA = 'cancelada';

    public const ESTADO_NO_ASISTIO = 'no_asistio';

    /** @var list<string> */
    public const ESTADOS = [
        self::ESTADO_PROGRAMADA,
        self::ESTADO_CONFIRMADA,
        self::ESTADO_EN_ATENCION,
        self::ESTADO_COMPLETADA,
        self::ESTADO_CANCELADA,
        self::ESTADO_NO_ASISTIO,
    ];

    /** Estados en cola (aún no atendidos). */
    public const ESTADOS_EN_ESPERA = [
        self::ESTADO_PROGRAMADA,
        self::ESTADO_CONFIRMADA,
    ];

    public function puedeAperturarse(): bool
    {
        return in_array($this->estado, self::ESTADOS_EN_ESPERA, true);
    }

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

    /**
     * Relaciones para listados y detalle (incluye paciente/propietario con soft delete).
     *
     * @return array<int|string, mixed>
     */
    public static function eagerLoadRelations(bool $withAudit = false): array
    {
        $relations = [
            'paciente' => static function (BelongsTo $query): void {
                $query->withTrashed()->with([
                    'propietario' => static function (BelongsTo $propQuery): void {
                        $propQuery->withTrashed();
                    },
                ]);
            },
            'veterinario:id,name',
            'sede:id,nombre,codigo',
        ];

        if ($withAudit) {
            $relations[] = 'creadoPor:id,name,email';
            $relations[] = 'actualizadoPor:id,name,email';
        }

        return $relations;
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
