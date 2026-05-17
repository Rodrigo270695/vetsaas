<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property ?string $tipo_documento
 * @property ?string $numero_documento
 * @property string $nombres
 * @property ?string $apellidos
 * @property ?string $razon_social
 * @property ?string $email
 * @property ?string $telefono
 * @property ?string $telefono_alt
 * @property ?string $direccion
 * @property ?int $distrito_id
 * @property ?string $distrito
 * @property ?string $provincia
 * @property ?string $departamento
 * @property ?string $notas
 * @property bool $activo
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class Propietario extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'propietarios';

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'razon_social',
        'email',
        'telefono',
        'telefono_alt',
        'direccion',
        'distrito_id',
        'distrito',
        'provincia',
        'departamento',
        'notas',
        'activo',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function distritoModel(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class, 'propietario_id');
    }

    /**
     * Nombre para mostrar en listas (persona natural o razón social).
     */
    public function displayName(): string
    {
        if ($this->razon_social) {
            return (string) $this->razon_social;
        }

        $parts = array_filter([$this->nombres, $this->apellidos]);

        return implode(' ', $parts);
    }
}
