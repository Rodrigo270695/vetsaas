<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración general de la clínica (única fila por tenant).
 *
 * Vive en `cfg_clinic_settings` dentro del schema del tenant activo.
 * La migración garantiza que solo puede existir UNA fila por schema vía
 * el índice único `CREATE UNIQUE INDEX … ON ((TRUE))`. Por eso este
 * modelo se opera como singleton mediante {@see self::current()}.
 *
 * Alcance: SOLO datos del cliente (lo que cada clínica configura para
 * sí misma). Las credenciales de proveedores compartidos por todas las
 * clínicas (Twilio WhatsApp, Brevo) viven en {@see PlatformSetting},
 * gestionadas por el `superadmin`. Aquí solo guardamos:
 *
 *   • Identidad fiscal/comercial (RUC, razón social, etc.).
 *   • Branding (logo, colores).
 *   • Contacto y operación (horarios, recordatorios, facturación).
 *   • Nubefact (única integración del cliente: cada RUC tiene el suyo).
 *   • "Remitente comercial visible": nombre/correo de respuesta y
 *     número de WhatsApp mostrado en mensajes (no autentica nada,
 *     solo personaliza la firma).
 *
 * Requisitos:
 *   - La request DEBE entrar por un subdominio de tenant para que
 *     `ResolveTenant` haya aplicado el `search_path`. En el host
 *     central la tabla no existe en `public`.
 *
 * @property string $id
 * @property ?string $ruc
 * @property ?string $razon_social
 * @property ?string $nombre_comercial
 * @property ?string $direccion_fiscal
 * @property ?int $distrito_id
 * @property ?string $logo_path
 * @property-read ?string $logo_url
 * @property ?string $email_institucional
 * @property ?string $telefono_principal
 * @property ?string $web_url
 * @property int $duracion_cita_default_min
 * @property int $intervalo_agenda_min
 * @property array<string, mixed> $horario_atencion
 * @property int $dias_anticipacion_cita
 * @property bool $recordatorio_48h_activo
 * @property bool $recordatorio_2h_activo
 * @property bool $recordatorio_vacuna_activo
 * @property int $recordatorio_vacuna_dias_antes
 * @property bool $recordatorio_cumple_activo
 * @property bool $bot_ia_respuestas_activo El tenant puede apagar respuestas automáticas del asistente IA.
 * @property ?string $nubefact_token_enc
 * @property ?string $nubefact_ruc
 * @property ?string $nubefact_api_ruta
 * @property bool $nubefact_configurado
 * @property ?string $whatsapp_display_number
 * @property ?string $email_from
 * @property ?string $email_from_nombre
 * @property string $moneda
 * @property string $igv_porcentaje
 * @property bool $precio_incluye_igv
 * @property bool $emite_comprobantes_sunat La clínica desea emitir comprobantes SUNAT (sujeto al plan y a Nubefact).
 * @property int $horas_min_cancelacion
 * @property ?string $color_primario
 * @property ?string $color_secundario
 * @property ?string $updated_by_id
 * @property-read ?User $actualizadoPor
 * @property-read ?Distrito $distritoModel
 */
class ClinicSetting extends Model
{
    use HasUuids;

    public const DEFAULT_AGENDA_HORA_INICIO = '07:00';

    public const DEFAULT_AGENDA_HORA_FIN = '20:00';

    protected $table = 'cfg_clinic_settings';

    protected $fillable = [
        'ruc',
        'razon_social',
        'nombre_comercial',
        'direccion_fiscal',
        'distrito_id',
        'logo_path',
        'email_institucional',
        'telefono_principal',
        'web_url',
        'duracion_cita_default_min',
        'intervalo_agenda_min',
        'horario_atencion',
        'dias_anticipacion_cita',
        'recordatorio_48h_activo',
        'recordatorio_2h_activo',
        'recordatorio_vacuna_activo',
        'recordatorio_vacuna_dias_antes',
        'recordatorio_cumple_activo',
        'bot_ia_respuestas_activo',
        'apisunat_token_enc',
        'apisunat_mode',
        'apisunat_configurado',
        'whatsapp_display_number',
        'email_from',
        'email_from_nombre',
        'moneda',
        'igv_porcentaje',
        'precio_incluye_igv',
        'ticket_ancho_mm',
        'emite_comprobantes_sunat',
        'grooming_catalogo_personalizado',
        'hotel_catalogo_personalizado',
        'horas_min_cancelacion',
        'color_primario',
        'color_secundario',
        'updated_by_id',
    ];

    /**
     * Las credenciales `*_enc` se ocultan por defecto del JSON serializado:
     * nunca deben viajar al frontend en claro. El controller las reemplaza
     * por un flag booleano `*_configurado`.
     */
    protected $hidden = [
        'apisunat_token_enc',
    ];

    /**
     * Atributos virtuales (no son columnas, se derivan): la URL pública
     * del logo se calcula desde `logo_path` y se incluye en el JSON para
     * que el frontend pueda mostrar la imagen sin lógica extra.
     */
    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return [
            'duracion_cita_default_min' => 'integer',
            'intervalo_agenda_min' => 'integer',
            'horario_atencion' => 'array',
            'dias_anticipacion_cita' => 'integer',
            'recordatorio_48h_activo' => 'boolean',
            'recordatorio_2h_activo' => 'boolean',
            'recordatorio_vacuna_activo' => 'boolean',
            'recordatorio_vacuna_dias_antes' => 'integer',
            'recordatorio_cumple_activo' => 'boolean',
            'bot_ia_respuestas_activo' => 'boolean',
            'nubefact_configurado' => 'boolean',
            'precio_incluye_igv' => 'boolean',
            'emite_comprobantes_sunat' => 'boolean',
            'grooming_catalogo_personalizado' => 'boolean',
            'hotel_catalogo_personalizado' => 'boolean',
            'horas_min_cancelacion' => 'integer',
            'igv_porcentaje' => 'decimal:2',
        ];
    }

    /**
     * URL pública del logo derivada de `logo_path`. Devuelve `null` si
     * todavía no se subió. Usa el disco `public` (que requiere haber
     * ejecutado `php artisan storage:link` para servirse desde
     * `/storage/...`).
     *
     * Construimos la URL manualmente con `asset()` en vez de delegar en
     * `Storage::disk('public')->url(...)` para esquivar la firma del
     * contrato base de Filesystem (que no declara `url()`, aunque
     * `FilesystemAdapter` sí lo implemente). Esto evita warnings de
     * análisis estático sin perder funcionalidad.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->logo_path
                ? asset('storage/'.ltrim($this->logo_path, '/'))
                : null,
        );
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * Distrito vinculado al catálogo global (vive en `public`, no en el
     * schema del tenant). Se expone para mostrar el path geográfico
     * completo (departamento → provincia → distrito) sin denormalizar.
     */
    public function distritoModel(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'distrito_id');
    }

    /**
     * Devuelve la (única) fila de configuración del tenant activo,
     * creándola con valores por defecto si todavía no existe.
     *
     * Patrón singleton-por-schema: usa `firstOrCreate([])` porque el
     * índice único sobre `((TRUE))` garantiza a nivel BD que solo
     * puede existir UNA fila por schema.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function isBotIaResponding(): bool
    {
        return (bool) ($this->bot_ia_respuestas_activo ?? true);
    }

    public function agendaHoraInicio(): string
    {
        return $this->agendaHour(
            'agenda_hora_inicio',
            self::DEFAULT_AGENDA_HORA_INICIO,
        );
    }

    public function agendaHoraFin(): string
    {
        return $this->agendaHour(
            'agenda_hora_fin',
            self::DEFAULT_AGENDA_HORA_FIN,
        );
    }

    private function agendaHour(string $key, string $fallback): string
    {
        $schedule = is_array($this->horario_atencion)
            ? $this->horario_atencion
            : [];
        $value = $schedule[$key] ?? null;

        return is_string($value)
            && preg_match('/^(?:[01]\d|2[0-3]):00$/', $value) === 1
                ? $value
                : $fallback;
    }
}
