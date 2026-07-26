<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de plan terapéutico en la consulta (fármaco + dosis/volumen).
 *
 * @property string $id
 * @property string $consulta_id
 * @property ?string $farmaco_id
 * @property string $farmaco_nombre
 * @property ?string $dosis_volumen
 * @property int $orden
 */
class ConsultaTerapiaLinea extends Model
{
    use HasUuids;

    protected $table = 'consulta_terapia_lineas';

    protected $fillable = [
        'consulta_id',
        'farmaco_id',
        'farmaco_nombre',
        'dosis_volumen',
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

    public function farmaco(): BelongsTo
    {
        return $this->belongsTo(Farmaco::class, 'farmaco_id');
    }
}
