<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $nombre
 * @property ?string $imagen_path
 * @property list<string> $variantes
 * @property int $tope_diario
 * @property int $intervalo_minutos
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property string $estado
 * @property ?Carbon $last_sent_at
 * @property ?Carbon $started_at
 * @property ?Carbon $paused_at
 */
class WhatsAppCampana extends Model
{
    use HasUuids;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_ENVIANDO = 'enviando';

    public const ESTADO_PAUSADA = 'pausada';

    public const ESTADO_TERMINADA = 'terminada';

    protected $table = 'whatsapp_campanas';

    protected $fillable = [
        'nombre',
        'imagen_path',
        'variantes',
        'tope_diario',
        'intervalo_minutos',
        'hora_inicio',
        'hora_fin',
        'estado',
        'last_sent_at',
        'started_at',
        'paused_at',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'variantes' => 'array',
            'tope_diario' => 'integer',
            'intervalo_minutos' => 'integer',
            'last_sent_at' => 'datetime',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(WhatsAppCampanaDestinatario::class, 'campana_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return list<string>
     */
    public function variantesLimpias(): array
    {
        $raw = $this->variantes;
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }
            $text = trim($item);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return array_values($out);
    }

    public function imagenUrl(): ?string
    {
        $path = trim((string) $this->imagen_path);
        if ($path === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    public function enviadosHoy(): int
    {
        return $this->destinatarios()
            ->where('estado', WhatsAppCampanaDestinatario::ESTADO_ENVIADO)
            ->whereDate('enviado_at', now()->toDateString())
            ->count();
    }
}
