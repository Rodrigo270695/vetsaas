<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración (singleton) del envío automático de mensajes de primer
 * contacto (IA + WhatsApp) hacia `VeterinariaProspecto`.
 *
 * @property string $id
 * @property bool $automatico_activo
 * @property int $mensajes_por_corrida
 * @property string $hora_envio formato "HH:MM" (America/Lima)
 * @property bool $enviar_con_imagen
 * @property ?string $imagen_path archivo en disco public (null = imagen por defecto de VetSaaS)
 * @property ?\Illuminate\Support\Carbon $ultima_corrida_at
 * @property ?string $actualizado_por_id
 */
class VeterinariaProspectoOutreachSetting extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'veterinaria_prospecto_outreach_settings';

    public const MIN_MENSAJES_POR_CORRIDA = 1;

    public const MAX_MENSAJES_POR_CORRIDA = 20;

    protected $fillable = [
        'automatico_activo',
        'mensajes_por_corrida',
        'hora_envio',
        'enviar_con_imagen',
        'imagen_path',
        'ultima_corrida_at',
        'actualizado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'automatico_activo' => 'boolean',
            'mensajes_por_corrida' => 'integer',
            'enviar_con_imagen' => 'boolean',
            'ultima_corrida_at' => 'datetime',
        ];
    }

    /**
     * URL http(s) absoluta para que OpenWA descargue la imagen.
     */
    public function imagenPublicaUrl(): string
    {
        if ($this->imagen_path !== null && $this->imagen_path !== '') {
            $url = Storage::disk('public')->url($this->imagen_path);

            if (str_starts_with($url, 'http')) {
                return $url;
            }

            return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        $default = ltrim((string) config('prospectos.outreach_imagen_default', 'images/vetsaas-hero-pets.png'), '/');

        return rtrim((string) config('app.url'), '/').'/'.$default;
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * True si la hora actual (America/Lima) cae dentro de la misma hora
     * configurada en `hora_envio` (comparación solo de HH, no de minutos,
     * para que el chequeo horario del scheduler no dependa de correr
     * exactamente al minuto).
     */
    public function esHoraDeCorrida(): bool
    {
        $horaConfigurada = substr($this->hora_envio, 0, 2);
        $horaActual = now('America/Lima')->format('H');

        return $horaConfigurada === $horaActual;
    }
}
