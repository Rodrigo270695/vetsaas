<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $nombre
 * @property ?string $categoria_id
 * @property string $precio_lista
 * @property string $moneda
 * @property ?int $duracion_minutos
 * @property bool $activo
 * @property int $orden
 */
class ServicioClinico extends Model
{
    use HasUuids;

    protected $table = 'servicios_clinicos';

    protected $fillable = [
        'nombre',
        'categoria_id',
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

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaServicioClinico::class, 'categoria_id');
    }
}
