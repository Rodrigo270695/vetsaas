<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Tarifa por tipo de servicio de grooming (precio lista sugerido en caja).
 *
 * @property string $id
 * @property string $servicio
 * @property string $precio_lista
 * @property string $moneda
 * @property bool $activo
 */
class GroomingServicioTarifa extends Model
{
    use HasUuids;

    protected $table = 'grooming_servicio_tarifas';

    protected $fillable = [
        'servicio',
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
