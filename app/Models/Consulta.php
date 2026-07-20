<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $historia_clinica_id
 * @property ?string $cita_id
 * @property \Illuminate\Support\Carbon $atendido_at
 * @property ?string $motivo
 * @property ?string $subjetivo
 * @property ?string $objetivo
 * @property ?string $analisis
 * @property ?string $plan
 * @property ?string $peso_kg
 * @property ?string $temperatura_c
 * @property ?int $fc_lpm
 * @property ?int $fr_rpm
 * @property ?\Illuminate\Support\Carbon $cerrada_at
 * @property ?string $cerrada_por_id
 * @property ?string $veterinario_id
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class Consulta extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'consultas';

    protected $fillable = [
        'historia_clinica_id',
        'cita_id',
        'atendido_at',
        'motivo',
        'subjetivo',
        'objetivo',
        'analisis',
        'plan',
        'peso_kg',
        'temperatura_c',
        'fc_lpm',
        'fr_rpm',
        'cerrada_at',
        'cerrada_por_id',
        'veterinario_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'atendido_at' => 'datetime',
            'cerrada_at' => 'datetime',
            'temperatura_c' => 'decimal:1',
            'fc_lpm' => 'integer',
            'fr_rpm' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Consulta $consulta): void {
            $plan = $consulta->planTratamiento;
            if ($plan === null) {
                return;
            }
            $plan->lineas()->delete();
            $plan->seguimientos()->delete();
            $plan->delete();
        });
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class, 'historia_clinica_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function veterinario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinario_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por_id');
    }

    public function vacunasAplicadas(): HasMany
    {
        return $this->hasMany(VacunaAplicada::class, 'consulta_id');
    }

    public function planTratamiento(): HasOne
    {
        return $this->hasOne(ConsultaPlanTratamiento::class, 'consulta_id');
    }

    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class, 'consulta_id');
    }

    public function pedidosLaboratorio(): HasMany
    {
        return $this->hasMany(PedidoLaboratorio::class, 'consulta_id');
    }

    public function cirugias(): HasMany
    {
        return $this->hasMany(Cirugia::class, 'consulta_id');
    }

    public function internamientos(): HasMany
    {
        return $this->hasMany(Internamiento::class, 'consulta_id');
    }

    public function cargo(): HasOne
    {
        return $this->hasOne(ConsultaCargo::class, 'consulta_id');
    }
}
