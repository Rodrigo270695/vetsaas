<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $nombre
 * @property ?string $descripcion
 * @property string $cuerpo
 * @property bool $activo
 */
class DocumentoAutorizacionPlantilla extends Model
{
    use HasUuids;

    protected $table = 'documento_autorizacion_plantillas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'cuerpo',
        'activo',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function envios(): HasMany
    {
        return $this->hasMany(DocumentoAutorizacionEnvio::class, 'plantilla_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
