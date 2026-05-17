<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $paciente_id
 * @property ?string $consulta_id
 * @property ?string $veterinario_id
 * @property ?string $sede_id
 * @property \Illuminate\Support\Carbon $emitida_at
 * @property string $estado
 * @property ?string $observaciones
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class Receta extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_EMITIDA = 'emitida';

    public const ESTADO_ANULADA = 'anulada';

    /** @var list<string> */
    public const ESTADOS = [
        self::ESTADO_BORRADOR,
        self::ESTADO_EMITIDA,
        self::ESTADO_ANULADA,
    ];

    /** @var list<string> */
    public const ESTADOS_CREACION = [
        self::ESTADO_BORRADOR,
        self::ESTADO_EMITIDA,
    ];

    protected $table = 'recetas';

    protected $fillable = [
        'paciente_id',
        'consulta_id',
        'veterinario_id',
        'sede_id',
        'emitida_at',
        'estado',
        'observaciones',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'emitida_at' => 'datetime',
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

    public function lineas(): HasMany
    {
        return $this->hasMany(RecetaLinea::class, 'receta_id')->orderBy('orden');
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
