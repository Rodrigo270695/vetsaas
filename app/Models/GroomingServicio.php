<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $nombre
 * @property ?string $categoria
 * @property ?string $codigo_legacy
 * @property string $precio_lista
 * @property string $moneda
 * @property int $duracion_minutos
 * @property bool $activo
 * @property int $orden
 */
class GroomingServicio extends Model
{
    use HasUuids;

    protected $table = 'grooming_servicios';

    protected $fillable = [
        'nombre',
        'categoria',
        'codigo_legacy',
        'precio_lista',
        'moneda',
        'duracion_minutos',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'precio_lista' => 'decimal:2',
            'duracion_minutos' => 'integer',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(GroomingTurno::class, 'grooming_servicio_id');
    }

    public function insumos(): HasMany
    {
        return $this->hasMany(GroomingServicioInsumo::class, 'grooming_servicio_id');
    }
}
