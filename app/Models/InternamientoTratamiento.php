<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $internamiento_id
 * @property ?string $servicio_clinico_id
 * @property Carbon $registrado_at
 * @property ?string $detalle
 */
class InternamientoTratamiento extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'internamiento_tratamientos';

    protected $fillable = [
        'internamiento_id',
        'servicio_clinico_id',
        'registrado_at',
        'detalle',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'registrado_at' => 'datetime',
        ];
    }

    public function internamiento(): BelongsTo
    {
        return $this->belongsTo(Internamiento::class, 'internamiento_id');
    }

    public function servicioClinico(): BelongsTo
    {
        return $this->belongsTo(ServicioClinico::class, 'servicio_clinico_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
