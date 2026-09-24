<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $internamiento_id
 * @property Carbon $registrado_at
 * @property ?string $mucosas
 * @property ?string $glucemia_mg_dl
 * @property ?string $orina_ml
 * @property ?string $vomito
 * @property ?string $diarrea
 * @property ?string $heces
 * @property ?int $bristol
 * @property ?string $alimento
 * @property ?string $agua
 * @property ?string $notas
 */
class InternamientoSignoClinico extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'internamiento_signos_clinicos';

    protected $fillable = [
        'internamiento_id',
        'registrado_at',
        'mucosas',
        'glucemia_mg_dl',
        'orina_ml',
        'vomito',
        'diarrea',
        'heces',
        'bristol',
        'alimento',
        'agua',
        'notas',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'registrado_at' => 'datetime',
            'glucemia_mg_dl' => 'decimal:1',
            'orina_ml' => 'decimal:1',
            'bristol' => 'integer',
        ];
    }

    public function internamiento(): BelongsTo
    {
        return $this->belongsTo(Internamiento::class, 'internamiento_id');
    }
}
