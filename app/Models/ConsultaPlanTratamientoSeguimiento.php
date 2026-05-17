<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $plan_id
 * @property \Illuminate\Support\Carbon $registrado_at
 * @property string $nota
 * @property ?string $created_by_id
 */
class ConsultaPlanTratamientoSeguimiento extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'consulta_plan_tratamiento_seguimientos';

    protected $fillable = [
        'plan_id',
        'registrado_at',
        'nota',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'registrado_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ConsultaPlanTratamiento::class, 'plan_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
