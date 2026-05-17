<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Compra extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'compras';

    protected $fillable = [
        'proveedor_id',
        'sede_id',
        'fecha_documento',
        'numero_documento',
        'serie',
        'moneda',
        'total',
        'notas',
        'factura_path',
        'factura_original_name',
        'created_by_id',
        'updated_by_id',
        'anulada_at',
        'anulada_por_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_documento' => 'date',
            'total' => 'decimal:2',
            'anulada_at' => 'datetime',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(CompraLinea::class, 'compra_id')->orderBy('orden');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por_id');
    }
}
