<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $nombre
 * @property bool $activo
 */
class CategoriaGrooming extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'categorias_grooming';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(GroomingServicio::class, 'categoria_id');
    }
}
