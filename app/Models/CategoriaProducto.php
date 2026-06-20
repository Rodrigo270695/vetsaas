<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoriaProducto extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'categorias_productos';

    protected $fillable = [
        'parent_id',
        'nombre',
        'slug',
        'descripcion',
        'orden',
        'activo',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Siguiente orden dentro del mismo nivel (misma categoría padre).
     * Incrementos de 10 para alinear con el catálogo sembrado.
     */
    public static function generateNextOrden(?string $parentId): int
    {
        $max = self::query()
            ->where('parent_id', $parentId)
            ->max('orden');

        return ((int) ($max ?? 0)) + 10;
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
