<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $paciente_id
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class HistoriaClinica extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'historias_clinicas';

    protected $fillable = [
        'paciente_id',
        'created_by_id',
        'updated_by_id',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function consultas(): HasMany
    {
        return $this->hasMany(Consulta::class, 'historia_clinica_id');
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
