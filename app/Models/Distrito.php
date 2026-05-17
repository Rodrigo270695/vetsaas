<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Distritos / municipios. Hoja del catálogo geográfico.
 *
 * Nivel 4:
 *   Pais → Departamento → Provincia → [Distrito]
 *
 * Este es el nivel al que apuntan las FKs de negocio
 * (`sedes.distrito_id`, `tenants.distrito_id`, etc.).
 */
class Distrito extends Model
{
    protected $table = 'distritos';

    protected $fillable = [
        'provincia_id',
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'provincia_id' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    /**
     * Devuelve el departamento de este distrito a través de la provincia.
     * Útil para denormalizar nombres en formularios y reportes.
     */
    public function departamento(): BelongsTo
    {
        return $this->provincia->departamento();
    }
}
