<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración global de la plataforma (singleton, schema `public`).
 *
 * A diferencia de {@see ClinicSetting} (que vive en el schema del tenant
 * y representa "lo del cliente"), este modelo guarda configuración del
 * **operador del SaaS**: credenciales de Twilio (WhatsApp) y Brevo
 * (correo), compartidas por todas las clínicas.
 *
 * Características clave:
 *   • Singleton: la migración crea `CREATE UNIQUE INDEX … ON ((TRUE))`
 *     que garantiza UNA sola fila en toda la instalación. El método
 *     {@see self::current()} la autoprovisiona la primera vez.
 *   • Solo accesible al `superadmin` desde el host central (no hay
 *     necesidad de scoping por tenant porque NO depende de tenant).
 *   • Las credenciales sensibles se ocultan del JSON (`$hidden`) y solo
 *     se expone un flag booleano `*_configurado`.
 *
 * @property string $id
 * @property ?string $twilio_sid_enc
 * @property ?string $twilio_token_enc
 * @property ?string $twilio_default_from
 * @property bool $twilio_configurado
 * @property ?string $brevo_api_key_enc
 * @property ?string $brevo_default_from_email
 * @property ?string $brevo_default_from_name
 * @property bool $brevo_configurado
 * @property ?string $updated_by_id
 * @property-read ?User $actualizadoPor
 */
class PlatformSetting extends Model
{
    use HasUuids, UsesPublicSchema;

    protected $table = 'platform_settings';

    protected $fillable = [
        'twilio_sid_enc',
        'twilio_token_enc',
        'twilio_default_from',
        'twilio_configurado',
        'brevo_api_key_enc',
        'brevo_default_from_email',
        'brevo_default_from_name',
        'brevo_configurado',
        'updated_by_id',
    ];

    /**
     * Las credenciales cifradas NUNCA viajan al frontend; el controller
     * las reemplaza por flags `*_configurado` para indicar visualmente
     * si la integración tiene credenciales guardadas.
     */
    protected $hidden = [
        'twilio_sid_enc',
        'twilio_token_enc',
        'brevo_api_key_enc',
    ];

    protected function casts(): array
    {
        return [
            'twilio_configurado' => 'boolean',
            'brevo_configurado' => 'boolean',
        ];
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * Devuelve la única fila de configuración global, creándola con
     * valores por defecto si todavía no existe.
     *
     * El índice único sobre `((TRUE))` garantiza a nivel de Postgres
     * que `firstOrCreate([])` solo puede crear una vez la fila; cualquier
     * intento posterior la recupera.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
