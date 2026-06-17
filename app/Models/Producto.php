<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'categoria_id',
        'nombre',
        'slug',
        'descripcion',
        'sku',
        'codigo_barras',
        'unidad',
        'precio_venta',
        'precio_compra',
        'medicamento',
        'activo',
        'stock_minimo',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'medicamento' => 'boolean',
            'activo' => 'boolean',
            'precio_venta' => 'decimal:2',
            'precio_compra' => 'decimal:2',
            'stock_minimo' => 'decimal:3',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function existenciasSede(): HasMany
    {
        return $this->hasMany(ExistenciaSede::class, 'producto_id');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'producto_id');
    }

    public function lineasPlanTratamiento(): HasMany
    {
        return $this->hasMany(ConsultaPlanTratamientoLinea::class, 'producto_id');
    }

    public function vacunasAplicadas(): HasMany
    {
        return $this->hasMany(VacunaAplicada::class, 'producto_id');
    }

    public function recetaLineas(): HasMany
    {
        return $this->hasMany(RecetaLinea::class, 'producto_id');
    }
}
