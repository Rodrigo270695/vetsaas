<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Examen complementario vinculado a una consulta (nombre desde servicio clínico).
 *
 * @property string $id
 * @property string $consulta_id
 * @property ?string $servicio_clinico_id
 * @property string $nombre
 * @property int $orden
 */
class ConsultaExamen extends Model
{
    use HasUuids;

    protected $table = 'consulta_examenes';

    protected $fillable = [
        'consulta_id',
        'servicio_clinico_id',
        'nombre',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class, 'consulta_id');
    }

    public function servicioClinico(): BelongsTo
    {
        return $this->belongsTo(ServicioClinico::class, 'servicio_clinico_id');
    }
}
