<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalAviso extends Model
{
    use HasUuids;

    protected $table = 'portal_avisos';

    protected $fillable = [
        'portal_propietario_id',
        'paciente_id',
        'tipo',
        'titulo',
        'cuerpo',
        'url',
        'leido_at',
    ];

    protected function casts(): array
    {
        return [
            'leido_at' => 'datetime',
        ];
    }

    public function portal(): BelongsTo
    {
        return $this->belongsTo(PortalPropietario::class, 'portal_propietario_id');
    }
}
