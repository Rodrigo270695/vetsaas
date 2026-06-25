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
 * @property int         $reactivation_count  veces que se ha enviado un mensaje de reactivación
 * @property \Illuminate\Support\Carbon|null $last_reactivation_at último mensaje de reactivación enviado
 * @property bool        $converted           true = lead convirtió (no reactivar más)
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
        'reactivation_count',
        'last_reactivation_at',
        'converted',
    ];

    protected function casts(): array
    {
        return [
            'messages'             => 'array',
            'turn_count'           => 'integer',
            'bot_active'           => 'boolean',
            'last_message_at'      => 'datetime',
            'reactivation_count'   => 'integer',
            'last_reactivation_at' => 'datetime',
            'converted'            => 'boolean',
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

    /**
     * Marca la conversación como convertida para que no sea
     * incluida en futuras campañas de reactivación.
     */
    public function markConverted(): void
    {
        $this->converted  = true;
        $this->bot_active = false;
        $this->save();
    }

    /**
     * Comprueba si este lead califica para recibir un mensaje
     * de reactivación:
     *  - No está convertido.
     *  - No ha escrito en más de $inactiveDays días.
     *  - El número de intentos de reactivación está dentro del límite.
     *  - Han pasado al menos 3 días desde el último intento.
     */
    public function isEligibleForReactivation(int $inactiveDays = 3, int $maxAttempts = 2): bool
    {
        if ($this->converted) {
            return false;
        }

        if (($this->reactivation_count ?? 0) >= $maxAttempts) {
            return false;
        }

        $lastActivity = $this->last_message_at ?? $this->created_at;

        if ($lastActivity->diffInDays(now()) < $inactiveDays) {
            return false;
        }

        // Evita enviar más de un intento cada 3 días.
        if ($this->last_reactivation_at && $this->last_reactivation_at->diffInDays(now()) < 3) {
            return false;
        }

        return true;
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
