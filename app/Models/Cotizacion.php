<?php

declare(strict_types=1);

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
 * @property ?string $sede_id
 * @property ?string $propietario_id
 * @property ?string $paciente_id
 * @property string $cliente_nombre
 * @property ?string $cliente_documento
 * @property ?string $cliente_telefono
 * @property ?string $cliente_email
 * @property \Illuminate\Support\Carbon $fecha
 * @property \Illuminate\Support\Carbon $vigencia_hasta
 * @property string $estado
 * @property string $moneda
 * @property string $igv_porcentaje
 * @property bool $precio_incluye_igv
 * @property string $subtotal
 * @property string $igv_monto
 * @property string $descuento_monto
 * @property string $total
 * @property ?string $notas
 * @property ?\Illuminate\Support\Carbon $enviada_whatsapp_at
 * @property ?\Illuminate\Support\Carbon $enviada_email_at
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class Cotizacion extends Model
{
    use HasUuids;
    use SoftDeletes;

    public const ESTADO_EMITIDA = 'emitida';

    public const ESTADO_ACEPTADA = 'aceptada';

    public const ESTADO_RECHAZADA = 'rechazada';

    public const ESTADOS = [
        self::ESTADO_EMITIDA,
        self::ESTADO_ACEPTADA,
        self::ESTADO_RECHAZADA,
    ];

    protected $table = 'cotizaciones';

    protected $fillable = [
        'numero',
        'anio',
        'correlativo',
        'sede_id',
        'propietario_id',
        'paciente_id',
        'cliente_nombre',
        'cliente_documento',
        'cliente_telefono',
        'cliente_email',
        'fecha',
        'vigencia_hasta',
        'estado',
        'moneda',
        'igv_porcentaje',
        'precio_incluye_igv',
        'subtotal',
        'igv_monto',
        'descuento_monto',
        'total',
        'notas',
        'enviada_whatsapp_at',
        'enviada_email_at',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'correlativo' => 'integer',
            'fecha' => 'date',
            'vigencia_hasta' => 'date',
            'igv_porcentaje' => 'decimal:2',
            'precio_incluye_igv' => 'boolean',
            'subtotal' => 'decimal:2',
            'igv_monto' => 'decimal:2',
            'descuento_monto' => 'decimal:2',
            'total' => 'decimal:2',
            'enviada_whatsapp_at' => 'datetime',
            'enviada_email_at' => 'datetime',
        ];
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(CotizacionLinea::class, 'cotizacion_id')->orderBy('orden');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'propietario_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function estaVencida(): bool
    {
        return $this->estado === self::ESTADO_EMITIDA
            && $this->vigencia_hasta->lt(now(config('app.timezone'))->startOfDay());
    }
}
