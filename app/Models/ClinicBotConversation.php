<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Historial de chat del asistente IA con un cliente (schema tenant).
 *
 * @property string $id
 * @property string $phone
 * @property string $wa_chat_id
 * @property string|null $client_name
 * @property array<int, array{role: string, content: string}>|null $messages
 * @property int $turn_count
 * @property bool $bot_active
 * @property bool $bot_paused_manually
 */
final class ClinicBotConversation extends Model
{
    use HasUuids;

    protected $table = 'clinic_bot_conversations';

    protected $fillable = [
        'phone',
        'wa_chat_id',
        'client_name',
        'messages',
        'turn_count',
        'bot_active',
        'bot_paused_manually',
        'last_message_at',
    ];

    public function scopeWithAiResponses(Builder $query): Builder
    {
        if (DB::getDriverName() === 'pgsql') {
            return $query->whereRaw(
                "EXISTS (
                    SELECT 1
                    FROM json_array_elements(COALESCE(messages, '[]'::json)) AS msg
                    WHERE msg->>'role' = 'assistant'
                )"
            );
        }

        return $query->where('turn_count', '>', 0);
    }

    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'turn_count' => 'integer',
            'bot_active' => 'boolean',
            'bot_paused_manually' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    public function pauseBotManually(): void
    {
        $this->bot_active = false;
        $this->bot_paused_manually = true;
        $this->save();
    }

    public function pauseBotAuto(): void
    {
        $this->bot_active = false;
        $this->save();
    }

    public function resumeBot(): void
    {
        $this->bot_active = true;
        $this->bot_paused_manually = false;
        $this->save();
    }

    public function isManuallyPaused(): bool
    {
        return (bool) $this->bot_paused_manually;
    }

    /**
     * @param  'user'|'assistant'  $role
     */
    public function pushMessage(string $role, string $content): void
    {
        $history = $this->messages ?? [];

        if (! is_array($history)) {
            $history = [];
        }

        $history[] = ['role' => $role, 'content' => $content];

        if (count($history) > 40) {
            $history = array_slice($history, -40);
        }

        $this->messages = $history;
        $this->turn_count = ($this->turn_count ?? 0) + 1;
        $this->last_message_at = now();
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function getOpenAiMessages(): array
    {
        $messages = $this->messages ?? [];

        return is_array($messages) ? $messages : [];
    }
}
