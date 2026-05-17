<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property ?string $consulta_id
 * @property ?string $internamiento_id
 * @property ?string $grooming_turno_id
 * @property ?string $hotel_estancia_id
 * @property string $estado
 * @property string $moneda
 * @property ?string $notas
 * @property string $subtotal_sin_igv
 * @property string $igv_importe
 * @property string $total
 * @property ?string $venta_id
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class ConsultaCargo extends Model
{
    use HasFactory;
    use HasUuids;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_CONFIRMADO = 'confirmado';

    protected $table = 'consulta_cargos';

    protected $fillable = [
        'consulta_id',
        'internamiento_id',
        'grooming_turno_id',
        'hotel_estancia_id',
        'estado',
        'moneda',
        'notas',
        'subtotal_sin_igv',
        'igv_importe',
        'total',
        'venta_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_sin_igv' => 'decimal:2',
            'igv_importe' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class, 'consulta_id');
    }

    public function internamiento(): BelongsTo
    {
        return $this->belongsTo(Internamiento::class, 'internamiento_id');
    }

    public function groomingTurno(): BelongsTo
    {
        return $this->belongsTo(GroomingTurno::class, 'grooming_turno_id');
    }

    public function hotelEstancia(): BelongsTo
    {
        return $this->belongsTo(HotelEstancia::class, 'hotel_estancia_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(ConsultaCargoLinea::class, 'consulta_cargo_id')->orderBy('orden');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }
}
