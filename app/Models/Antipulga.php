<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $paciente_id
 * @property \Illuminate\Support\Carbon $atendido_at
 * @property ?string $anamnesis
 * @property ?string $apetito
 * @property ?string $ingesta_agua
 * @property ?string $vomitos_frecuencia
 * @property ?string $vomitos_descripcion
 * @property ?string $heces_frecuencia
 * @property ?string $heces_descripcion
 * @property ?string $orina_frecuencia
 * @property ?string $orina_color
 * @property ?string $orina_olor
 * @property ?\Illuminate\Support\Carbon $ultimo_celo
 * @property ?string $peso_kg
 * @property ?string $temperatura_c
 * @property ?int $fc_lpm
 * @property ?int $fr_rpm
 * @property ?string $tlc
 * @property ?string $pa
 * @property ?string $hidratacion
 * @property list<array<string, mixed>>|null $aplicados
 * @property list<array<string, mixed>>|null $receta
 * @property ?string $comentarios
 * @property ?string $veterinario_id
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class Antipulga extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'antipulgas';

    protected $fillable = [
        'paciente_id',
        'atendido_at',
        'anamnesis',
        'apetito',
        'ingesta_agua',
        'vomitos_frecuencia',
        'vomitos_descripcion',
        'heces_frecuencia',
        'heces_descripcion',
        'orina_frecuencia',
        'orina_color',
        'orina_olor',
        'ultimo_celo',
        'peso_kg',
        'temperatura_c',
        'fc_lpm',
        'fr_rpm',
        'tlc',
        'pa',
        'hidratacion',
        'aplicados',
        'receta',
        'comentarios',
        'veterinario_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'atendido_at' => 'datetime',
            'ultimo_celo' => 'date',
            'peso_kg' => 'decimal:3',
            'temperatura_c' => 'decimal:2',
            'fc_lpm' => 'integer',
            'fr_rpm' => 'integer',
            'aplicados' => 'array',
            'receta' => 'array',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class, 'antipulga_id');
    }

    public function veterinario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinario_id');
    }

    public function cargo(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ConsultaCargo::class, 'antipulga_id')
            ->whereNull('venta_id');
    }

    public function permiteCargosPreCuenta(): bool
    {
        return true;
    }
}
