<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicSchema;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración global de la plataforma (singleton, schema `public`).
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
 * @property int $in_app_assistant_daily_limit
 * @property bool $in_app_assistant_announcement_active
 * @property int $in_app_assistant_announcement_version
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
        'in_app_assistant_daily_limit',
        'in_app_assistant_announcement_active',
        'in_app_assistant_announcement_version',
        'updated_by_id',
    ];

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
            'in_app_assistant_daily_limit' => 'integer',
            'in_app_assistant_announcement_active' => 'boolean',
            'in_app_assistant_announcement_version' => 'integer',
        ];
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function assistantDailyLimit(): int
    {
        $limit = (int) ($this->in_app_assistant_daily_limit ?: 0);
        if ($limit < 1) {
            $limit = (int) config('in-app-assistant.daily_message_limit', 40);
        }

        return max(1, min(1000, $limit));
    }

    /**
     * @return array{active: bool, version: int}|null
     */
    public function assistantAnnouncementPayload(): ?array
    {
        if (! $this->in_app_assistant_announcement_active) {
            return null;
        }

        $version = (int) $this->in_app_assistant_announcement_version;
        if ($version < 1) {
            return null;
        }

        return [
            'active' => true,
            'version' => $version,
        ];
    }
}
