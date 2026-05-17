<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property ?string $consulta_id
 * @property ?string $producto_id
 * @property string $nombre_vacuna
 * @property \Illuminate\Support\Carbon $aplicada_at
 * @property ?int $numero_dosis
 * @property ?string $lote
 * @property string $categoria_registro
 * @property ?string $esquema_antigenos
 * @property ?\Illuminate\Support\Carbon $fecha_proxima_sugerida
 * @property ?string $notas
 * @property ?string $veterinario_id
 * @property ?string $sede_id
 * @property ?string $movimiento_inventario_id
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class VacunaAplicada extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const CATEGORIA_VACUNA = 'vacuna';

    public const CATEGORIA_DESPARASITACION = 'desparasitacion';

    public const CATEGORIA_OTRO = 'otro';

    /** @var list<string> */
    public const CATEGORIAS_REGISTRO = [
        self::CATEGORIA_VACUNA,
        self::CATEGORIA_DESPARASITACION,
        self::CATEGORIA_OTRO,
    ];

    protected $table = 'vacunas_aplicadas';

    protected $fillable = [
        'paciente_id',
        'consulta_id',
        'producto_id',
        'nombre_vacuna',
        'aplicada_at',
        'numero_dosis',
        'lote',
        'categoria_registro',
        'esquema_antigenos',
        'fecha_proxima_sugerida',
        'notas',
        'veterinario_id',
        'sede_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'aplicada_at' => 'datetime',
            'numero_dosis' => 'integer',
            'fecha_proxima_sugerida' => 'date',
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

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function veterinario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinario_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function movimientoInventario(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_inventario_id');
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
