<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $tipo_comprobante
 * @property string $serie
 * @property int $ultimo_correlativo
 * @property bool $activo
 */
class FelSerie extends Model
{
    use HasUuids;

    public const TIPO_FACTURA = 1;

    public const TIPO_BOLETA = 2;

    protected $table = 'fel_series';

    protected $fillable = [
        'tipo_comprobante',
        'serie',
        'ultimo_correlativo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo_comprobante' => 'integer',
            'ultimo_correlativo' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(FelDocument::class, 'fel_serie_id');
    }
}
