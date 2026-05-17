<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo público de países.
 *
 * Nivel 1 del catálogo geográfico jerárquico:
 *   Pais → Departamento → Provincia → Distrito
 */
class Pais extends Model
{
    protected $table = 'paises';

    protected $fillable = [
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function departamentos(): HasMany
    {
        return $this->hasMany(Departamento::class);
    }
}
