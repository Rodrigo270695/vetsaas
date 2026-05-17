<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $venta_id
 * @property ?string $producto_id
 * @property ?string $tipo_linea
 * @property ?string $consulta_cargo_linea_id
 * @property string $descripcion_snapshot
 * @property string $igv_tipo_snapshot
 * @property string $cantidad
 * @property string $precio_unitario
 * @property string $descuento_pct
 * @property string $subtotal
 */
class VentaLinea extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'venta_lineas';

    protected $fillable = [
        'venta_id',
        'tipo_linea',
        'producto_id',
        'consulta_cargo_linea_id',
        'descripcion_snapshot',
        'igv_tipo_snapshot',
        'cantidad',
        'precio_unitario',
        'descuento_pct',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:4',
            'descuento_pct' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
