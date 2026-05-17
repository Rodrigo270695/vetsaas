<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $consulta_id
 * @property ?string $fecha_inicio
 * @property ?string $fecha_fin
 * @property ?string $indicaciones
 * @property string $estado
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class ConsultaPlanTratamiento extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'consulta_planes_tratamiento';

    protected $fillable = [
        'consulta_id',
        'fecha_inicio',
        'fecha_fin',
        'indicaciones',
        'estado',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class, 'consulta_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(ConsultaPlanTratamientoLinea::class, 'plan_id')->orderBy('sort_order');
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(ConsultaPlanTratamientoSeguimiento::class, 'plan_id')->orderByDesc('registrado_at');
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
