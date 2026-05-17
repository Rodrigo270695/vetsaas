<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $numero
 * @property int $anio
 * @property int $correlativo
 * @property string $propietario_id
 * @property ?string $paciente_id
 * @property ?string $consulta_id
 * @property ?string $consulta_cargo_id
 * @property string $caja_sesion_id
 * @property string $sede_id
 * @property string $moneda
 * @property string $estado
 * @property string $subtotal
 * @property string $igv_monto
 * @property string $descuento_monto
 * @property string $total
 * @property ?string $metodo_pago
 * @property ?string $monto_recibido
 * @property ?string $vuelto
 * @property ?\Illuminate\Support\Carbon $fecha_pago
 * @property ?string $notas
 * @property string $fel_estado
 * @property ?string $fel_document_id
 * @property ?string $created_by_id
 */
class Venta extends Model
{
    use HasUuids;
    use SoftDeletes;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PAGADO = 'pagado';

    public const ESTADO_PARCIAL = 'parcial';

    public const ESTADO_ANULADO = 'anulado';

    public const FEL_SIN_CPE = 'sin_cpe';

    public const FEL_PENDIENTE = 'pendiente_emision';

    public const FEL_EMITIDO = 'emitido';

    public const FEL_RECHAZADO = 'rechazado';

    public const FEL_ANULADO = 'anulado';

    protected $table = 'ventas';

    protected $fillable = [
        'numero',
        'anio',
        'correlativo',
        'propietario_id',
        'paciente_id',
        'consulta_id',
        'consulta_cargo_id',
        'caja_sesion_id',
        'sede_id',
        'moneda',
        'estado',
        'subtotal',
        'igv_monto',
        'descuento_monto',
        'total',
        'metodo_pago',
        'monto_recibido',
        'vuelto',
        'fecha_pago',
        'notas',
        'fel_estado',
        'fel_document_id',
        'created_by_id',
        'anulado_at',
        'anulado_por_id',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'correlativo' => 'integer',
            'subtotal' => 'decimal:2',
            'igv_monto' => 'decimal:2',
            'descuento_monto' => 'decimal:2',
            'total' => 'decimal:2',
            'monto_recibido' => 'decimal:2',
            'vuelto' => 'decimal:2',
            'fecha_pago' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    public function estaAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'propietario_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class, 'consulta_id');
    }

    public function consultaCargo(): BelongsTo
    {
        return $this->belongsTo(ConsultaCargo::class, 'consulta_cargo_id');
    }

    public function cajaSesion(): BelongsTo
    {
        return $this->belongsTo(CajaSesion::class, 'caja_sesion_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(VentaLinea::class, 'venta_id')->orderBy('id');
    }

    public function felDocument(): BelongsTo
    {
        return $this->belongsTo(FelDocument::class, 'fel_document_id');
    }
}
