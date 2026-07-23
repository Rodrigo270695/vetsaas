<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de caja (apertura / cierre) por sede y usuario.
 *
 * @property string $id
 * @property string $sede_id
 * @property string $estado
 * @property string $moneda
 * @property string $saldo_apertura
 * @property ?string $saldo_cierre_efectivo
 * @property ?array $arqueo_json
 * @property \Illuminate\Support\Carbon $opened_at
 * @property ?\Illuminate\Support\Carbon $closed_at
 * @property ?string $notas
 * @property string $opened_by_id
 * @property ?string $closed_by_id
 */
class CajaSesion extends Model
{
    use HasUuids;

    public const ESTADO_ABIERTA = 'abierta';

    public const ESTADO_CERRADA = 'cerrada';

    protected $table = 'caja_sesiones';

    protected $fillable = [
        'sede_id',
        'estado',
        'moneda',
        'saldo_apertura',
        'saldo_cierre_efectivo',
        'arqueo_json',
        'opened_at',
        'closed_at',
        'notas',
        'opened_by_id',
        'closed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'saldo_apertura' => 'decimal:2',
            'saldo_cierre_efectivo' => 'decimal:2',
            'arqueo_json' => 'array',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function abiertaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_id');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'caja_sesion_id');
    }

    public function estaAbierta(): bool
    {
        return $this->estado === self::ESTADO_ABIERTA;
    }
}
