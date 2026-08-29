<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de cada corrida del scraper de prospectos veterinarios
 * (cron diario o disparo manual desde el panel de Plataforma).
 *
 * @property string $id
 * @property string $origen
 * @property \Illuminate\Support\Carbon $iniciado_at
 * @property ?\Illuminate\Support\Carbon $finalizado_at
 * @property string $estado
 * @property int $nuevos
 * @property int $duplicados
 * @property int $sin_datos
 * @property array<int, string> $ubicaciones_visitadas
 * @property array<int, string> $errores
 * @property ?string $iniciado_por_id
 */
class VeterinariaProspectoScrapeRun extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'veterinaria_prospecto_scrape_runs';

    protected $fillable = [
        'origen',
        'iniciado_at',
        'finalizado_at',
        'estado',
        'nuevos',
        'duplicados',
        'sin_datos',
        'ubicaciones_visitadas',
        'errores',
        'iniciado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'iniciado_at' => 'datetime',
            'finalizado_at' => 'datetime',
            'ubicaciones_visitadas' => 'array',
            'errores' => 'array',
        ];
    }

    public function iniciadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por_id');
    }
}
