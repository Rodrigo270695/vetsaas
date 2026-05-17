<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $propietario_id
 * @property string $nombre
 * @property ?string $foto_path
 * @property-read ?string $foto_url
 * @property ?string $especie
 * @property ?string $raza
 * @property ?string $sexo
 * @property ?\Illuminate\Support\Carbon $fecha_nacimiento
 * @property ?string $peso_kg
 * @property ?string $microchip
 * @property ?string $color
 * @property ?bool $esterilizado
 * @property ?string $notas
 * @property bool $activo
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class Paciente extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'pacientes';

    protected $appends = [
        'foto_url',
    ];

    protected $fillable = [
        'propietario_id',
        'nombre',
        'foto_path',
        'especie',
        'raza',
        'sexo',
        'fecha_nacimiento',
        'peso_kg',
        'microchip',
        'color',
        'esterilizado',
        'notas',
        'activo',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'esterilizado' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * URL pública de la foto (disco `public`, requiere `storage:link`).
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->foto_path
                ? asset('storage/'.ltrim($this->foto_path, '/'))
                : null,
        );
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'propietario_id');
    }

    public function historiaClinica(): HasOne
    {
        return $this->hasOne(HistoriaClinica::class, 'paciente_id');
    }

    public function vacunasAplicadas(): HasMany
    {
        return $this->hasMany(VacunaAplicada::class, 'paciente_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'paciente_id');
    }

    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class, 'paciente_id');
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
