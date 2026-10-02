<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $paciente_id
 * @property Carbon $ocurrido_at
 * @property string $motivo
 * @property ?string $sitio
 * @property ?string $testigo
 * @property ?string $comentarios
 * @property ?string $veterinario_id
 * @property ?string $created_by_id
 * @property ?string $updated_by_id
 */
class Defuncion extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'defunciones';

    protected $fillable = [
        'paciente_id',
        'ocurrido_at',
        'motivo',
        'sitio',
        'testigo',
        'comentarios',
        'veterinario_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'ocurrido_at' => 'datetime',
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

    public function autorizaciones(): HasMany
    {
        return $this->hasMany(DocumentoAutorizacionEnvio::class, 'defuncion_id');
    }
}
