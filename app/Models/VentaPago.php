<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de pago de una venta (uno o varios métodos).
 *
 * @property string $id
 * @property string $venta_id
 * @property string $metodo
 * @property string $monto
 * @property ?string $monto_recibido
 * @property ?string $vuelto
 * @property int $orden
 */
class VentaPago extends Model
{
    use HasUuids;

    public const METODO_EFECTIVO = 'efectivo';

    public const METODO_YAPE = 'yape';

    public const METODO_PLIN = 'plin';

    public const METODO_TARJETA = 'tarjeta';

    public const METODO_TRANSFERENCIA = 'transferencia';

    /** @var list<string> */
    public const METODOS = [
        self::METODO_EFECTIVO,
        self::METODO_YAPE,
        self::METODO_PLIN,
        self::METODO_TARJETA,
        self::METODO_TRANSFERENCIA,
    ];

    protected $table = 'venta_pagos';

    protected $fillable = [
        'venta_id',
        'metodo',
        'monto',
        'monto_recibido',
        'vuelto',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'monto_recibido' => 'decimal:2',
            'vuelto' => 'decimal:2',
            'orden' => 'integer',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}
