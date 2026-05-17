<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Departamentos / regiones del catálogo geográfico.
 *
 * Nivel 2:
 *   Pais → [Departamento] → Provincia → Distrito
 */
class Departamento extends Model
{
    protected $table = 'departamentos';

    protected $fillable = [
        'pais_id',
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'pais_id' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class);
    }
}
