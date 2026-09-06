<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Turno de caja (apertura / cierre) por sede y usuario.
 *
 * @property string $id
 * @property string $sede_id
 * @property string $estado
 * @property string $moneda
 * @property string $saldo_apertura
 * @property ?array $saldos_apertura_json
 * @property ?string $saldo_cierre_efectivo
 * @property ?array $saldos_cierre_json
 * @property ?array $arqueo_json
 * @property Carbon $opened_at
 * @property ?Carbon $closed_at
 * @property ?string $notas
 * @property string $opened_by_id
 * @property ?string $closed_by_id
 */
class CajaSesion extends Model
{
    use HasUuids;

    public const ESTADO_ABIERTA = 'abierta';

    public const ESTADO_CERRADA = 'cerrada';

    /** Horas máximas tras el cierre para volver a abrir la misma sesión. */
    public const REABRIR_HORAS = 24;

    protected $table = 'caja_sesiones';

    protected $fillable = [
        'sede_id',
        'estado',
        'moneda',
        'saldo_apertura',
        'saldos_apertura_json',
        'saldo_cierre_efectivo',
        'saldos_cierre_json',
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
            'saldos_apertura_json' => 'array',
            'saldo_cierre_efectivo' => 'decimal:2',
            'saldos_cierre_json' => 'array',
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

    public function egresos(): HasMany
    {
        return $this->hasMany(CajaEgreso::class, 'caja_sesion_id');
    }

    public function estaAbierta(): bool
    {
        return $this->estado === self::ESTADO_ABIERTA;
    }

    /**
     * Solo sesiones cerradas cuyo cierre fue hace menos de 24 horas.
     * En el límite exacto de 24 h ya no se permite reaperturar.
     */
    public function estaDentroDeVentanaReabrir(?Carbon $now = null): bool
    {
        return self::estaCierreDentroDeVentanaReabrir(
            (string) ($this->attributes['estado'] ?? ''),
            $this->attributes['closed_at'] ?? null,
            $now,
        );
    }

    public static function estaCierreDentroDeVentanaReabrir(string $estado, mixed $closedAt, ?Carbon $now = null): bool
    {
        if ($estado !== self::ESTADO_CERRADA || $closedAt === null || $closedAt === '') {
            return false;
        }

        $cierre = $closedAt instanceof \DateTimeInterface
            ? Carbon::instance(\DateTimeImmutable::createFromInterface($closedAt))
            : Carbon::parse((string) $closedAt);

        $now ??= now();

        return $now->lt($cierre->copy()->addHours(self::REABRIR_HORAS));
    }
}
