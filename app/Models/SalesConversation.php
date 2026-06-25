<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Conversación de WhatsApp con un prospecto (pre-venta).
 *
 * @property string      $id
 * @property string      $phone               "51987654321"
 * @property string      $wa_chat_id          "51987654321@c.us"
 * @property string|null $prospect_name
 * @property array<int, array{role: string, content: string}> $messages
 * @property int         $turn_count
 * @property bool        $bot_active          true = bot responde | false = Rodrigo escribe manualmente
 * @property string|null $activation_trigger  qué palabra clave activó el bot
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 */
final class SalesConversation extends Model
{
    use HasUuids;

    protected $table = 'sales_conversations';

    protected $fillable = [
        'phone',
        'wa_chat_id',
        'prospect_name',
        'messages',
        'turn_count',
        'bot_active',
        'activation_trigger',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'messages'        => 'array',
            'turn_count'      => 'integer',
            'bot_active'      => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * Pausa el bot para esta conversación.
     * Rodrigo toma el control manualmente.
     * Para reactivar: $conversation->resumeBot();
     */
    public function pauseBot(): void
    {
        $this->bot_active = false;
        $this->save();
    }

    /**
     * Reactiva el bot para esta conversación.
     */
    public function resumeBot(): void
    {
        $this->bot_active = true;
        $this->save();
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Agrega un mensaje al historial y actualiza contadores.
     * No persiste — llama a save() después.
     *
     * @param  'user'|'assistant'  $role
     */
    public function pushMessage(string $role, string $content): void
    {
        $history = $this->messages ?? [];

        $history[] = ['role' => $role, 'content' => $content];

        // Limitar a los últimos 40 turnos para no exceder el context window.
        if (count($history) > 40) {
            $history = array_slice($history, -40);
        }

        $this->messages        = $history;
        $this->turn_count      = ($this->turn_count ?? 0) + 1;
        $this->last_message_at = now();
    }

    /**
     * Devuelve el historial listo para pasarle a la API de OpenAI.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getOpenAiMessages(): array
    {
        return $this->messages ?? [];
    }
}
