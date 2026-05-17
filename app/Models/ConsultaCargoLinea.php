<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $consulta_cargo_id
 * @property string $tipo_linea
 * @property ?string $producto_id
 * @property string $concepto
 * @property string $cantidad
 * @property string $precio_unitario
 * @property string $descuento_importe
 * @property int $orden
 */
class ConsultaCargoLinea extends Model
{
    use HasFactory;
    use HasUuids;

    public const TIPO_SERVICIO = 'servicio';

    public const TIPO_PRODUCTO = 'producto';

    public const TIPO_OTRO = 'otro';

    protected $table = 'consulta_cargo_lineas';

    protected $fillable = [
        'consulta_cargo_id',
        'tipo_linea',
        'producto_id',
        'concepto',
        'cantidad',
        'precio_unitario',
        'descuento_importe',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'descuento_importe' => 'decimal:2',
            'orden' => 'integer',
        ];
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(ConsultaCargo::class, 'consulta_cargo_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
