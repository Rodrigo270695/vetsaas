<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prospecto comercial (clínica u hospital veterinario) capturado desde
 * directorios públicos (scraping) o registrado manualmente, para
 * prospección de venta de VetSaaS. Vive en `public` (no es multi-tenant).
 *
 * @property string $id
 * @property string $nombre
 * @property string $tipo
 * @property ?string $telefono_normalizado
 * @property ?string $telefono
 * @property ?string $correo
 * @property ?string $direccion
 * @property ?string $departamento
 * @property ?string $provincia
 * @property ?string $distrito
 * @property ?string $horario
 * @property bool $es_24_horas
 * @property ?string $fuente_sitio
 * @property ?string $fuente_url
 * @property ?string $ubicacion_slug
 * @property string $origen
 * @property string $estado
 * @property \Illuminate\Support\Carbon $capturado_at
 * @property ?string $creado_por_id
 * @property ?string $sales_conversation_id vincula con `sales_conversations` una vez contactado, así el
 *           chatbot IA existente (webhook + `plataforma/salesbot-conversations`) sigue la charla solo.
 * @property ?\Illuminate\Support\Carbon $mensaje_enviado_at fecha del primer mensaje de contacto enviado
 * @property ?string $mensaje_enviado_por_id quién lo disparó manualmente (null = automático/cron)
 * @property int $mensaje_intentos
 * @property ?string $mensaje_error último error al intentar contactar (si falló)
 */
class VeterinariaProspecto extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'veterinaria_prospectos';

    public const TIPO_CLINICA = 'clinica';

    public const TIPO_HOSPITAL = 'hospital';

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_SCRAPING = 'scraping_auto';

    public const ESTADOS = [
        'nuevo',
        'contactado',
        'conversando',
        'demo_agendada',
        'cliente',
        'no_interesado',
    ];

    protected $fillable = [
        'nombre',
        'tipo',
        'telefono_normalizado',
        'telefono',
        'correo',
        'direccion',
        'departamento',
        'provincia',
        'distrito',
        'horario',
        'es_24_horas',
        'fuente_sitio',
        'fuente_url',
        'ubicacion_slug',
        'origen',
        'estado',
        'sales_conversation_id',
        'mensaje_enviado_at',
        'mensaje_enviado_por_id',
        'mensaje_intentos',
        'mensaje_error',
        'capturado_at',
        'creado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'es_24_horas' => 'boolean',
            'capturado_at' => 'datetime',
            'mensaje_enviado_at' => 'datetime',
            'mensaje_intentos' => 'integer',
        ];
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function salesConversation(): BelongsTo
    {
        return $this->belongsTo(SalesConversation::class, 'sales_conversation_id');
    }

    public function mensajeEnviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mensaje_enviado_por_id');
    }

    /**
     * WhatsApp de celular Perú: +51 y 9 dígitos (el 9 inicial del móvil).
     * Fijos tipo +51 1 2727819 o +51 74 221172 se excluyen del outreach.
     */
    public static function esCelularPeruano(?string $telefono): bool
    {
        if ($telefono === null || $telefono === '') {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $telefono) ?? '';

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return true;
        }

        return strlen($digits) === 11 && str_starts_with($digits, '519');
    }

    /**
     * Elegible para recibir el primer mensaje de contacto (IA + WhatsApp):
     * nunca se le ha escrito, tiene celular (no fijo) y sigue como "nuevo".
     */
    public function esElegibleParaOutreach(): bool
    {
        return $this->mensaje_enviado_at === null
            && $this->estado === 'nuevo'
            && self::esCelularPeruano($this->telefono_normalizado);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeSoloCelulares($query)
    {
        return $query->where(function ($q): void {
            $q->where(function ($inner): void {
                $inner->whereRaw('LENGTH(telefono_normalizado) = 11')
                    ->where('telefono_normalizado', 'like', '519%');
            })->orWhere(function ($inner): void {
                $inner->whereRaw('LENGTH(telefono_normalizado) = 9')
                    ->where('telefono_normalizado', 'like', '9%');
            });
        });
    }

    /**
     * Prospectos nuevos, sin contactar, con celular WhatsApp.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeElegiblesParaOutreach($query)
    {
        return $query
            ->where('estado', 'nuevo')
            ->whereNull('mensaje_enviado_at')
            ->soloCelulares();
    }

    public static function normalizarTelefono(?string $telefono): ?string
    {
        if ($telefono === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $telefono) ?? '';

        if ($digits === '') {
            return null;
        }

        // Perú: si viene sin código de país y tiene 9 dígitos (celular) le
        // anteponemos 51 para uniformar el dedupe frente a números con +51.
        if (strlen($digits) === 9 && ! str_starts_with($digits, '51')) {
            $digits = '51'.$digits;
        }

        return $digits;
    }
}
