<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $paciente_id
 * @property Carbon $atendido_at
 * @property ?string $peso_kg
 * @property ?string $temperatura_c
 * @property ?int $fc_lpm
 * @property ?int $fr_rpm
 * @property ?string $tlc
 * @property ?string $pa
 * @property ?string $hidratacion
 * @property ?array<string, list<string>> $hallazgos
 * @property ?list<string> $recomendaciones
 * @property ?string $comentarios
 * @property ?string $veterinario_id
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class Triaje extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'triajes';

    protected $fillable = [
        'paciente_id',
        'atendido_at',
        'peso_kg',
        'temperatura_c',
        'fc_lpm',
        'fr_rpm',
        'tlc',
        'pa',
        'hidratacion',
        'hallazgos',
        'recomendaciones',
        'comentarios',
        'veterinario_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'atendido_at' => 'datetime',
            'hallazgos' => 'array',
            'recomendaciones' => 'array',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function veterinario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinario_id');
    }
}
