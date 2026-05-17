<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $receta_id
 * @property ?string $producto_id
 * @property string $nombre_medicamento
 * @property ?string $posologia
 * @property ?int $duracion_dias
 * @property ?string $instrucciones
 * @property int $orden
 */
class RecetaLinea extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'receta_lineas';

    protected $fillable = [
        'receta_id',
        'producto_id',
        'nombre_medicamento',
        'posologia',
        'duracion_dias',
        'instrucciones',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'duracion_dias' => 'integer',
            'orden' => 'integer',
        ];
    }

    public function receta(): BelongsTo
    {
        return $this->belongsTo(Receta::class, 'receta_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
