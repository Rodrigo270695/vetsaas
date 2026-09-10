<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolanteRutaParada extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'volante_ruta_paradas';

    protected $fillable = [
        'ruta_id',
        'prospecto_id',
        'orden',
        'nombre',
        'direccion',
        'distrito',
        'telefono',
        'lat',
        'lng',
        'km_desde_anterior',
        'visitado_at',
        'resultado',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'km_desde_anterior' => 'float',
            'visitado_at' => 'datetime',
        ];
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(VolanteRuta::class, 'ruta_id');
    }

    public function prospecto(): BelongsTo
    {
        return $this->belongsTo(VeterinariaProspecto::class, 'prospecto_id');
    }
}
