<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicaAsesorada extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'clinicas_asesoradas';

    protected $fillable = [
        'nombre',
        'ruc',
        'direccion',
        'distrito_id',
        'distrito',
        'provincia',
        'departamento',
        'activo',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'distrito_id' => 'integer',
        ];
    }

    public function distritoModel(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class, 'clinica_asesorada_id');
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
