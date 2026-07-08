<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de insumos de grooming, por clínica (tenant).
 *
 * @property string $id
 * @property string $nombre
 * @property bool $activo
 */
class GroomingInsumo extends Model
{
    use HasUuids;

    protected $table = 'grooming_insumos';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(GroomingServicioInsumo::class, 'grooming_insumo_id');
    }
}
