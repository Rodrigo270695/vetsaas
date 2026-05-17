<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $paciente_id
 * @property ?string $consulta_id
 * @property ?string $veterinario_id
 * @property ?string $sede_id
 * @property \Illuminate\Support\Carbon $ingreso_at
 * @property ?\Illuminate\Support\Carbon $alta_at
 * @property string $estado
 * @property string $motivo_ingreso
 * @property ?string $ubicacion
 * @property ?string $diagnostico_ingreso
 * @property ?string $notas
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class Internamiento extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_ALTA = 'alta';

    public const ESTADO_CANCELADO = 'cancelado';

    /** @var list<string> */
    public const ESTADOS = [
        self::ESTADO_ACTIVO,
        self::ESTADO_ALTA,
        self::ESTADO_CANCELADO,
    ];

    /** @var list<string> */
    public const ESTADOS_CREACION = [
        self::ESTADO_ACTIVO,
    ];

    protected $table = 'internamientos';

    protected $fillable = [
        'paciente_id',
        'consulta_id',
        'veterinario_id',
        'sede_id',
        'ingreso_at',
        'alta_at',
        'estado',
        'motivo_ingreso',
        'ubicacion',
        'diagnostico_ingreso',
        'notas',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'ingreso_at' => 'datetime',
            'alta_at' => 'datetime',
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

    public function evoluciones(): HasMany
    {
        return $this->hasMany(InternamientoEvolucion::class, 'internamiento_id');
    }

    public function cargo(): HasOne
    {
        return $this->hasOne(ConsultaCargo::class, 'internamiento_id');
    }
}
