<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $cotizacion_id
 * @property string $tipo_linea
 * @property ?string $producto_id
 * @property string $descripcion
 * @property ?string $unidad
 * @property string $cantidad
 * @property string $precio_unitario
 * @property string $descuento_pct
 * @property string $total
 * @property int $orden
 */
class CotizacionLinea extends Model
{
    use HasUuids;

    public const TIPO_PRODUCTO = 'producto';

    public const TIPO_SERVICIO = 'servicio';

    public $timestamps = false;

    protected $table = 'cotizacion_lineas';

    protected $fillable = [
        'cotizacion_id',
        'tipo_linea',
        'producto_id',
        'descripcion',
        'unidad',
        'cantidad',
        'precio_unitario',
        'descuento_pct',
        'total',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:4',
            'descuento_pct' => 'decimal:2',
            'total' => 'decimal:2',
            'orden' => 'integer',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
