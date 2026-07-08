<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivote servicio de grooming ↔ insumo, con el precio del insumo para ese
 * servicio en particular (por tenant).
 *
 * @property string $id
 * @property string $grooming_servicio_id
 * @property string $grooming_insumo_id
 * @property string $precio
 */
class GroomingServicioInsumo extends Model
{
    use HasUuids;

    protected $table = 'grooming_servicio_insumo';

    protected $fillable = [
        'grooming_servicio_id',
        'grooming_insumo_id',
        'precio',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
        ];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(GroomingServicio::class, 'grooming_servicio_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(GroomingInsumo::class, 'grooming_insumo_id');
    }
}
