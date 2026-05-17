<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $plan_id
 * @property ?string $producto_id
 * @property ?string $cantidad
 * @property string $medicamento
 */
class ConsultaPlanTratamientoLinea extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'consulta_plan_tratamiento_lineas';

    protected $fillable = [
        'plan_id',
        'producto_id',
        'cantidad',
        'medicamento',
        'dosis',
        'unidad',
        'via',
        'frecuencia',
        'lote',
        'notas',
        'anadido_en',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anadido_en' => 'date',
            'cantidad' => 'decimal:3',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ConsultaPlanTratamiento::class, 'plan_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
