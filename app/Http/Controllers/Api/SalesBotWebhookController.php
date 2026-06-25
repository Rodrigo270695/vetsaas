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
        // ── 1. Verificar secreto del webhook ─────────────────────────────
        $secret = (string) config('salesbot.webhook_secret', '');

        if ($secret !== '') {
            $provided = (string) $request->header('X-Webhook-Secret', '');
            if (! hash_equals($secret, $provided)) {
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

        $event   = (string) ($payload['event'] ?? 'onMessage');
        $fromMe  = (bool) ($data['fromMe'] ?? false);
        $type    = (string) ($data['type'] ?? 'chat');
        $waChatId = (string) ($data['from'] ?? $data['chatId'] ?? '');
        $body    = trim((string) ($data['body'] ?? ''));

        // Solo procesar mensajes de chat de texto que no sean nuestros.
        if ($fromMe || $event !== 'onMessage' || $type !== 'chat' || $body === '') {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        // Ignorar mensajes de grupos (@g.us).
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

        // ── 4. Obtener / crear conversación ───────────────────────────────
        $conversation = $this->botService->findOrCreateConversation(
            phone: $phone,
            waChatId: $waChatId,
            prospectName: $prospectName,
        );

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
