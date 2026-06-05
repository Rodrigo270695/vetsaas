<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $pedido_laboratorio_id
 * @property string $nombre_examen
 * @property ?string $indicaciones
 * @property ?string $resultado
 * @property ?\Illuminate\Support\Carbon $resultado_at
 * @property ?string $resultado_archivo_path
 * @property ?string $resultado_archivo_original_name
 * @property int $orden
 */
class PedidoLaboratorioLinea extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'pedido_laboratorio_lineas';

    protected $fillable = [
        'pedido_laboratorio_id',
        'nombre_examen',
        'indicaciones',
        'resultado',
        'resultado_at',
        'resultado_archivo_path',
        'resultado_archivo_original_name',
        'orden',
    ];

    protected $appends = [
        'resultado_archivo_url',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'resultado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (PedidoLaboratorioLinea $linea): void {
            self::deleteArchivoFromDisk($linea);
        });
    }

    public function getResultadoArchivoUrlAttribute(): ?string
    {
        if ($this->resultado_archivo_path === null || $this->resultado_archivo_path === '') {
            return null;
        }

        return route('clinica.laboratorio.lineas.resultado-archivo', ['linea' => $this->id]);
    }

    public static function deleteArchivoFromDisk(self $linea): void
    {
        $path = $linea->resultado_archivo_path;
        if ($path === null || $path === '') {
            return;
        }

        $tid = tenant_id();
        if ($tid === null) {
            return;
        }

        $expectedPrefix = 'laboratorio/'.$tid.'/';
        if (! str_starts_with($path, $expectedPrefix)) {
            return;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function pedidoLaboratorio(): BelongsTo
    {
        return $this->belongsTo(PedidoLaboratorio::class, 'pedido_laboratorio_id');
    }
}
