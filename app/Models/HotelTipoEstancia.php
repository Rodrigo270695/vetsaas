<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $nombre
 * @property ?string $categoria
 * @property ?string $categoria_id
 * @property ?string $codigo_legacy
 * @property string $precio_lista
 * @property string $moneda
 * @property bool $activo
 * @property int $orden
 */
class HotelTipoEstancia extends Model
{
    use HasUuids;

    protected $table = 'hotel_tipos_estancia';

    protected $fillable = [
        'nombre',
        'categoria',
        'categoria_id',
        'codigo_legacy',
        'precio_lista',
        'moneda',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'precio_lista' => 'decimal:2',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function categoriaCatalogo(): BelongsTo
    {
        return $this->belongsTo(CategoriaHotel::class, 'categoria_id');
    }

    public function estancias(): HasMany
    {
        return $this->hasMany(HotelEstancia::class, 'hotel_tipo_id');
    }
}
