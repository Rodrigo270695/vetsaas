<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salida de efectivo registrada en una sesión de caja abierta.
 *
 * @property string $id
 * @property string $caja_sesion_id
 * @property string $monto
 * @property string $motivo
 * @property ?string $notas
 * @property string $created_by_id
 */
class CajaEgreso extends Model
{
    use HasUuids;

    public const MOTIVO_INSUMOS = 'insumos';

    public const MOTIVO_DELIVERY = 'delivery';

    public const MOTIVO_SERVICIOS = 'servicios';

    public const MOTIVO_PERSONAL = 'personal';

    public const MOTIVO_CAMBIO = 'cambio';

    public const MOTIVO_OTROS = 'otros';

    public const MOTIVOS = [
        self::MOTIVO_INSUMOS,
        self::MOTIVO_DELIVERY,
        self::MOTIVO_SERVICIOS,
        self::MOTIVO_PERSONAL,
        self::MOTIVO_CAMBIO,
        self::MOTIVO_OTROS,
    ];

    protected $table = 'caja_egresos';

    protected $fillable = [
        'caja_sesion_id',
        'monto',
        'motivo',
        'notas',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class, 'caja_sesion_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public static function labelMotivo(string $motivo): string
    {
        return match ($motivo) {
            self::MOTIVO_INSUMOS => 'Insumos / compras menores',
            self::MOTIVO_DELIVERY => 'Delivery / mensajería',
            self::MOTIVO_SERVICIOS => 'Servicios / pagos operativos',
            self::MOTIVO_PERSONAL => 'Personal / anticipos',
            self::MOTIVO_CAMBIO => 'Cambio / vuelto',
            default => 'Otros',
        };
    }
}
