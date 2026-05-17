<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Tarifa por tipo de estancia hotel/guardería (precio lista **por noche** sugerido en caja).
 *
 * @property string $id
 * @property string $tipo_estancia
 * @property string $precio_lista
 * @property string $moneda
 * @property bool $activo
 */
class HotelEstanciaTarifa extends Model
{
    use HasUuids;

    protected $table = 'hotel_estancia_tarifas';

    protected $fillable = [
        'tipo_estancia',
        'precio_lista',
        'moneda',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_lista' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }
}
