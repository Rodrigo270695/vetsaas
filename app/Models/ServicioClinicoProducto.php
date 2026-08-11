<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Producto de inventario incluido en un servicio clínico (p. ej. paquete vacuna).
 *
 * @property string $id
 * @property string $servicio_clinico_id
 * @property string $producto_id
 * @property string $cantidad
 * @property int $orden
 */
class ServicioClinicoProducto extends Model
{
    use HasUuids;

    protected $table = 'servicio_clinico_productos';

    protected $fillable = [
        'servicio_clinico_id',
        'producto_id',
        'cantidad',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'orden' => 'integer',
        ];
    }

    public function servicioClinico(): BelongsTo
    {
        return $this->belongsTo(ServicioClinico::class, 'servicio_clinico_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
