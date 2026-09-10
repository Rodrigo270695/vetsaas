<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolanteRuta extends Model
{
    use HasUuids, UsesPublicSchema;

    public const ABIERTA = 'abierta';

    public const COMPLETADA = 'completada';

    public const CANCELADA = 'cancelada';

    protected $table = 'volante_rutas';

    protected $fillable = [
        'departamento',
        'estado',
        'origin_lat',
        'origin_lng',
        'max_paradas',
        'paradas_count',
        'km',
        'minutos',
        'polyline',
        'creado_por_id',
        'iniciado_at',
        'completado_at',
    ];

    protected function casts(): array
    {
        return [
            'origin_lat' => 'float',
            'origin_lng' => 'float',
            'max_paradas' => 'integer',
            'paradas_count' => 'integer',
            'km' => 'float',
            'minutos' => 'integer',
            'polyline' => 'array',
            'iniciado_at' => 'datetime',
            'completado_at' => 'datetime',
        ];
    }

    public function paradas(): HasMany
    {
        return $this->hasMany(VolanteRutaParada::class, 'ruta_id')->orderBy('orden');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function visitadasCount(): int
    {
        return $this->paradas->whereNotNull('visitado_at')->count();
    }
}
