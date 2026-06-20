<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $venta_id
 * @property string $fel_serie_id
 * @property int $tipo_comprobante
 * @property string $serie
 * @property int $correlativo
 * @property string $numero_completo
 * @property int $receptor_tipo_doc
 * @property string $receptor_num_doc
 * @property string $receptor_nombre
 * @property string $subtotal
 * @property string $igv_monto
 * @property string $total
 * @property string $moneda
 * @property string $estado
 * @property ?string $nubefact_id
 * @property ?string $url_pdf
 * @property ?string $url_xml
 * @property ?string $url_cdr
 * @property ?string $enlace_consulta
 * @property ?array $apisunat_payload
 * @property ?string $apisunat_mode
 * @property ?string $error_mensaje
 * @property ?Carbon $emitido_at
 */
class FelDocument extends Model
{
    use HasUuids;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_EMITIDO = 'emitido';

    public const ESTADO_RECHAZADO = 'rechazado';

    public const ESTADO_ANULADO = 'anulado';

    protected $table = 'fel_documents';

    protected $fillable = [
        'venta_id',
        'fel_serie_id',
        'tipo_comprobante',
        'serie',
        'correlativo',
        'numero_completo',
        'receptor_tipo_doc',
        'receptor_num_doc',
        'receptor_nombre',
        'subtotal',
        'igv_monto',
        'total',
        'moneda',
        'estado',
        'nubefact_id',
        'url_pdf',
        'url_xml',
        'url_cdr',
        'enlace_consulta',
        'apisunat_payload',
        'apisunat_mode',
        'error_mensaje',
        'emitido_at',
        'anulado_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo_comprobante' => 'integer',
            'correlativo' => 'integer',
            'receptor_tipo_doc' => 'integer',
            'subtotal' => 'decimal:2',
            'igv_monto' => 'decimal:2',
            'total' => 'decimal:2',
            'emitido_at' => 'datetime',
            'anulado_at' => 'datetime',
            'apisunat_payload' => 'array',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function felSerie(): BelongsTo
    {
        return $this->belongsTo(FelSerie::class, 'fel_serie_id');
    }
}
