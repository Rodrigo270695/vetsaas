<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductoLote extends Model
{
    use HasUuids;

    protected $table = 'producto_lotes';

    protected $fillable = [
        'producto_id',
        'sede_id',
        'numero_lote',
        'fecha_vencimiento',
        'cantidad',
        'compra_linea_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'cantidad' => 'decimal:3',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function compraLinea(): BelongsTo
    {
        return $this->belongsTo(CompraLinea::class, 'compra_linea_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'producto_lote_id');
    }
}
