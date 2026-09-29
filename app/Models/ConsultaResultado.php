<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Archivo de resultado externo adjunto a una consulta (PDF o imagen).
 *
 * @property string $id
 * @property string $consulta_id
 * @property string $archivo_path
 * @property string $original_name
 * @property string $mime
 * @property ?int $bytes
 * @property int $orden
 * @property ?string $created_by_id
 */
class ConsultaResultado extends Model
{
    use HasUuids;

    public const MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    protected $table = 'consulta_resultados';

    protected $fillable = [
        'consulta_id',
        'archivo_path',
        'original_name',
        'mime',
        'bytes',
        'orden',
        'created_by_id',
    ];

    protected $hidden = [
        'archivo_path',
    ];

    protected $appends = [
        'url',
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'orden' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ConsultaResultado $resultado): void {
            self::deleteArchivoFromDisk($resultado);
        });
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->archivo_path === '') {
            return null;
        }

        return route('clinica.historias-clinicas.consultas.resultados.show', [
            'consulta' => $this->consulta_id,
            'resultado' => $this->id,
        ]);
    }

    public static function deleteArchivoFromDisk(self $resultado): void
    {
        $path = $resultado->archivo_path;
        if ($path === '') {
            return;
        }

        $tid = tenant_id();
        if (! is_string($tid) || $tid === '') {
            return;
        }

        $expectedPrefix = 'consultas/'.$tid.'/';
        if (! str_starts_with($path, $expectedPrefix)) {
            return;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class, 'consulta_id');
    }
}
