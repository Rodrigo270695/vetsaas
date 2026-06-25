<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Sales\SalesBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recibe mensajes entrantes de WhatsApp desde OpenWA y responde
 * automáticamente usando el bot de ventas IA.
 *
 * OpenWA llama a este endpoint (POST /api/webhooks/sales-bot) cada vez
 * que llega un mensaje nuevo a la sesión de plataforma.
 *
 * Configuración en el panel de OpenWA (wa-admin.vetsaas.orvae.pe):
 *   Webhook URL  : https://app.vetsaas.orvae.pe/api/webhooks/sales-bot
 *   Header       : X-Webhook-Secret: <valor de SALESBOT_WEBHOOK_SECRET>
 *   Events       : onMessage
 *
 * Payload esperado de OpenWA:
 * {
 *   "event": "onMessage",
 *   "sessionId": "vetsaas-platform",
 *   "data": {
 *     "id": "...",
 *     "body": "Hola quiero información",
 *     "from": "51988497089@c.us",
 *     "fromMe": false,
 *     "type": "chat",
 *     "sender": { "pushname": "José Rosales" }
 *   }
 * }
 */
final class SalesBotWebhookController extends Controller
{
    public function __construct(
        private readonly SalesBotService $botService,
        private readonly PlatformWhatsAppMessenger $messenger,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        // ── 1. Verificar firma del webhook ────────────────────────────────
        // OpenWA firma el body con HMAC-SHA256 usando el "secret" del webhook
        // y lo envía en el header "X-Webhook-Signature".
        // También soportamos el header "X-Webhook-Secret" por compatibilidad.
        $secret = (string) config('salesbot.webhook_secret', '');

        if ($secret !== '') {
            $signature = (string) $request->header('X-Webhook-Signature', '');
            $legacySecret = (string) $request->header('X-Webhook-Secret', '');

            if ($signature !== '') {
                // Verificar HMAC-SHA256
                $rawBody  = (string) $request->getContent();
                $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);
                if (! hash_equals($expected, $signature)) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }
            } elseif ($legacySecret !== '') {
                // Fallback: comparación directa del secret como header
                if (! hash_equals($secret, $legacySecret)) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }
            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        // ── 2. Verificar que el bot está habilitado ───────────────────────
        if (! config('salesbot.enabled')) {
            return response()->json(['ok' => false, 'reason' => 'salesbot disabled'], 200);
        }

        // ── 3. Extraer datos del payload ──────────────────────────────────
        $payload = $request->all();

        // Soporta payload directo { body, from, ... } o anidado { data: { ... } }
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        // OpenWA envía el evento como "event" o "type" según la versión.
        $event   = (string) ($payload['event'] ?? $payload['type'] ?? '');
        $fromMe  = (bool) ($data['fromMe'] ?? $data['from_me'] ?? false);
        $type    = (string) ($data['type'] ?? 'chat');
        $waChatId = (string) ($data['from'] ?? $data['chatId'] ?? $data['chat_id'] ?? '');
        $body    = trim((string) ($data['body'] ?? $data['content'] ?? $data['text'] ?? ''));

        // Aceptar tanto "message.received" (esta versión OpenWA) como "onMessage" (versiones antiguas).
        $esEventoMensaje = in_array($event, ['message.received', 'onMessage', 'message'], true);

        $isAudio = in_array($type, ['ptt', 'audio'], true);

        // Saltar si: es mensaje propio, no es evento de mensaje,
        // o está vacío Y no es un audio que podamos transcribir.
        if ($fromMe || ! $esEventoMensaje || ($body === '' && ! $isAudio)) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        // Ignorar grupos (@g.us).
        if (str_ends_with($waChatId, '@g.us')) {
            return response()->json(['ok' => true, 'skipped' => 'group']);
        }

        // Extraer número limpio (sin @c.us).
        $phone = str_replace('@c.us', '', $waChatId);
        if ($phone === '') {
            return response()->json(['ok' => false, 'reason' => 'no phone'], 422);
        }

        // Nombre del prospecto (si WhatsApp lo envía).
        $senderData   = is_array($data['sender'] ?? null) ? $data['sender'] : [];
        $prospectName = ($senderData['pushname'] ?? null) !== null
            ? (string) $senderData['pushname']
            : null;

        // ── Soporte de audios (Whisper) ────────────────────────────────────
        // Si el mensaje es de tipo ptt (push-to-talk) o audio, intentamos
        // transcribirlo. Si falla, le avisamos al prospecto amablemente.
        if ($body === '' && $isAudio && config('salesbot.audio_enabled')) {
            $mediaUrl = (string) ($data['mediaUrl'] ?? $data['body'] ?? '');
            if ($mediaUrl !== '') {
                try {
                    $audioContent = $this->messenger->getClient()->downloadMedia($mediaUrl);
                    $body         = $this->botService->transcribeAudio($audioContent, 'audio.ogg');
                    Log::info('SalesBot audio transcribed', ['phone' => $phone, 'text' => substr($body, 0, 100)]);
                } catch (\Throwable $e) {
                    Log::warning('SalesBot Whisper failed', ['phone' => $phone, 'error' => $e->getMessage()]);
                    // No podemos transcribir — le decimos al lead que escriba.
                    if ($this->messenger->isReady()) {
                        $this->messenger->sendText($waChatId, '¡Hola! 👋 Recibí tu audio pero por el momento solo puedo responder mensajes de texto. ¿Me puedes escribir tu consulta? 😊');
                    }
                    return response()->json(['ok' => true, 'skipped' => 'audio_transcription_failed']);
                }
            }
        }

        // ── 4. Lógica de activación del bot ───────────────────────────────
        //
        // REGLA: el bot solo interviene en dos casos:
        //   A) La conversación YA existe y bot_active = true
        //      (el prospecto ya está en el funnel, bot sigue respondiendo)
        //   B) La conversación NO existe y el mensaje contiene palabras
        //      clave de ventas de VetSaaS (viene del anuncio de Facebook)
        //
        // Si "Pepito" escribe "hola Rodrigo" sin palabras clave → silencio.
        // Si el usuario toma el control manualmente (bot_active=false) → silencio.

        $conversation = $this->botService->findExistingConversation($phone);

        if ($conversation !== null) {
            // Conversación existente con bot pausado: verificar si el cliente
            // vuelve a preguntar por VetSaaS. Si es así, reactivar el bot
            // automáticamente para no perder el lead.
            if (! $conversation->bot_active) {
                $trigger = $this->botService->detectSalesTrigger($body);
                if ($trigger !== null) {
                    $conversation->resumeBot();
                    $conversation->activation_trigger = "reactivado:{$trigger}";
                    $conversation->save();
                    // El bot continúa respondiendo abajo.
                } else {
                    // El cliente escribe algo que no es de ventas (ej: "ok gracias")
                    // mientras Rodrigo maneja la conversación. No interrumpir.
                    return response()->json(['ok' => true, 'skipped' => 'paused']);
                }
            }
            // Actualizar nombre si llegó uno nuevo.
            if ($prospectName !== null && $conversation->prospect_name === null) {
                $conversation->prospect_name = $prospectName;
                $conversation->save();
            }
        } else {
            // Conversación nueva: solo activar si hay palabras clave de ventas.
            $trigger = $this->botService->detectSalesTrigger($body);

            if ($trigger === null) {
                // No es un prospecto de VetSaaS — ignorar completamente.
                return response()->json(['ok' => true, 'skipped' => 'no_trigger']);
            }

            // Crear conversación con el trigger detectado.
            $conversation = $this->botService->createConversation(
                phone: $phone,
                waChatId: $waChatId,
                prospectName: $prospectName,
                trigger: $trigger,
            );
        }

        // ── 5. Generar respuesta con IA ───────────────────────────────────
        try {
            $reply = $this->botService->reply($conversation, $body);
        } catch (\Throwable $e) {
            Log::error('SalesBot reply error', [
                'phone'   => $phone,
                'message' => $e->getMessage(),
            ]);

            // Fallback amigable si OpenAI falla: no dejar al prospecto sin respuesta.
            $reply = "Hola 👋 Gracias por escribir sobre VetSaaS. Dame un momento y te respondo enseguida.";
        }

        // ── 6. Enviar respuesta por WhatsApp ──────────────────────────────
        try {
            if ($this->messenger->isReady()) {
                $this->messenger->sendText($waChatId, $reply);
            } else {
                Log::warning('SalesBot: messenger no está listo, respuesta no enviada.', ['phone' => $phone]);
            }
        } catch (\Throwable $e) {
            Log::error('SalesBot send error', [
                'phone'   => $phone,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
