<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $pedido_laboratorio_id
 * @property string $nombre_examen
 * @property ?string $indicaciones
 * @property ?string $resultado
 * @property ?\Illuminate\Support\Carbon $resultado_at
 * @property int $orden
 */
class PedidoLaboratorioLinea extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'pedido_laboratorio_lineas';

    protected $fillable = [
        'pedido_laboratorio_id',
        'nombre_examen',
        'indicaciones',
        'resultado',
        'resultado_at',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'resultado_at' => 'datetime',
        ];
    }

    public function pedidoLaboratorio(): BelongsTo
    {
        return $this->belongsTo(PedidoLaboratorio::class, 'pedido_laboratorio_id');
    }
}
