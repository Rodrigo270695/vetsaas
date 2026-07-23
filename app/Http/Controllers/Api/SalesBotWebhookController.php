<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Sales\SalesBotService;
use App\Support\WhatsApp\WhatsAppContactResolver;
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
        private readonly WhatsAppContactResolver $contactResolver,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        // ── 1. Verificar firma del webhook (fail-closed si no hay secret) ─
        // OpenWA firma el body con HMAC-SHA256 usando el "secret" del webhook
        // y lo envía en el header "X-Webhook-Signature".
        // También soportamos el header "X-Webhook-Secret" por compatibilidad.
        $secret = (string) config('salesbot.webhook_secret', '');

        if ($secret === '') {
            Log::error('SalesBot webhook rechazado: SALESBOT_WEBHOOK_SECRET no configurado.');

            return response()->json(['error' => 'Webhook secret not configured'], 503);
        }

        $signature = (string) $request->header('X-Webhook-Signature', '');
        $legacySecret = (string) $request->header('X-Webhook-Secret', '');

        if ($signature !== '') {
            $rawBody = (string) $request->getContent();
            $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);
            if (! hash_equals($expected, $signature)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        } elseif ($legacySecret !== '') {
            if (! hash_equals($secret, $legacySecret)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ── 2. Verificar que el bot está habilitado ───────────────────────
        if (! config('salesbot.enabled')) {
            return response()->json(['ok' => false, 'reason' => 'salesbot disabled'], 200);
        }

        // ── 3. Deduplicar — responder inmediatamente para que OpenWA no reintente ──
        // OpenWA reintenta el webhook si no recibe respuesta en ~10s.
        // Como el procesamiento de audio puede tomar 20-30s, cerramos la
        // conexión HTTP de inmediato y seguimos procesando en background.
        // Ver: https://laravel.com/docs/http-client#making-asynchronous-requests

        // ── 3. Extraer datos del payload ──────────────────────────────────
        $payload = $request->all();

        // Soporta payload directo { body, from, ... } o anidado { data: { ... } }
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        // OpenWA envía el evento como "event" o "type" según la versión.
        $event   = (string) ($payload['event'] ?? $payload['type'] ?? '');
        $fromMe  = (bool) ($data['fromMe'] ?? $data['from_me'] ?? false);
        $type    = (string) ($data['type'] ?? 'chat');
        $body    = trim((string) ($data['body'] ?? $data['content'] ?? $data['text'] ?? ''));

        // Aceptar tanto "message.received" (esta versión OpenWA) como "onMessage" (versiones antiguas).
        $esEventoMensaje = in_array($event, ['message.received', 'onMessage', 'message'], true);

        $isAudio   = in_array($type, ['ptt', 'audio'], true);
        $messageId = (string) ($data['id'] ?? '');

        // Mensaje saliente (fromMe): saludo de Facebook → armar bot; otro mensaje → pausar.
        if ($fromMe && $esEventoMensaje) {
            $openWaSessionId = (string) ($payload['sessionId'] ?? $data['sessionId'] ?? '');
            $sessionArg      = $openWaSessionId !== '' ? $openWaSessionId : null;
            $contact         = $this->contactResolver->resolve($data, $sessionArg, forOutgoing: true);

            if ($contact['phone'] === '' || str_ends_with($contact['wa_chat_id'], '@g.us')) {
                return response()->json(['ok' => true, 'skipped' => 'fromMe']);
            }

            if ($this->botService->isFacebookWelcomeMessage($body)) {
                $this->botService->armConversationFromWelcome(
                    phone: $contact['phone'],
                    waChatId: $contact['wa_chat_id'],
                    prospectName: $contact['prospect_name'],
                    welcomeBody: $body,
                );
                Log::info('SalesBot armed from Facebook welcome', [
                    'phone' => $contact['phone'],
                    'name'  => $contact['prospect_name'],
                ]);

                return response()->json(['ok' => true, 'armed' => 'facebook:welcome']);
            }

            $conversation = $this->botService->findExistingConversation($contact['phone'], $contact['wa_chat_id']);

            if ($conversation !== null && $conversation->bot_active) {
                $conversation->pauseBotAuto();
                $conversation->activation_trigger = 'auto-pausa:humano';
                $conversation->save();
                Log::info('SalesBot auto-paused: mensaje manual de Rodrigo', [
                    'phone' => $contact['phone'],
                ]);
            }

            return response()->json(['ok' => true, 'skipped' => 'fromMe']);
        }

        // Saltar si: no es evento de mensaje, o está vacío Y no es audio transcribible.
        if (! $esEventoMensaje || ($body === '' && ! $isAudio)) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        // Resolver contacto: número real, nombre y chat ID para responder.
        $openWaSessionId = (string) ($payload['sessionId'] ?? $data['sessionId'] ?? '');
        $contact         = $this->contactResolver->resolve($data, $openWaSessionId !== '' ? $openWaSessionId : null);

        $waChatId     = $contact['wa_chat_id'];
        $phone        = $contact['phone'];
        $prospectName = $contact['prospect_name'];

        // Ignorar grupos (@g.us).
        if (str_ends_with($waChatId, '@g.us')) {
            return response()->json(['ok' => true, 'skipped' => 'group']);
        }

        if ($phone === '') {
            return response()->json(['ok' => false, 'reason' => 'no phone'], 422);
        }

        // Cliente habla con Rodrigo o envía datos de proyecto → no intervenir.
        $conversationForHandoff = $this->botService->findExistingConversation($phone, $waChatId);
        $handoffProduct         = $conversationForHandoff !== null
            ? $this->botService->resolveConversationProduct($conversationForHandoff)
            : $this->botService->resolveProductFromTrigger(
                (string) ($this->botService->detectSalesTrigger($body) ?? ''),
            );

        if ($this->botService->isHumanHandoffMessage($body, $handoffProduct)) {
            $conversation = $this->botService->findExistingConversation($phone, $waChatId);

            if ($conversation !== null && $conversation->bot_active) {
                $conversation->pauseBotAuto();
                $conversation->activation_trigger = 'auto-pausa:humano-cliente';
                $conversation->save();
                Log::info('SalesBot auto-paused: conversación manual detectada', ['phone' => $phone]);
            }

            return response()->json(['ok' => true, 'skipped' => 'human_handoff']);
        }

        // ── Deduplicación por message ID ──────────────────────────────────
        if ($messageId !== '') {
            $cacheKey = 'salesbot_msg_'.md5($messageId);
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                return response()->json(['ok' => true, 'skipped' => 'duplicate']);
            }
            \Illuminate\Support\Facades\Cache::put($cacheKey, 1, 60);
        }

        // ── Soporte de audios (Whisper) ────────────────────────────────────
        // OpenWA envía los audios como base64 en data.media.data
        // (mimetype: audio/ogg; codecs=opus).
        if ($body === '' && $isAudio && config('salesbot.audio_enabled')) {
            $media     = is_array($data['media'] ?? null) ? $data['media'] : [];
            $b64data   = (string) ($media['data'] ?? '');
            $mimetype  = (string) ($media['mimetype'] ?? 'audio/ogg');

            // Determinar extensión a partir del mimetype.
            $ext = str_contains($mimetype, 'ogg') ? 'ogg'
                : (str_contains($mimetype, 'mp4') ? 'mp4'
                : (str_contains($mimetype, 'webm') ? 'webm' : 'ogg'));

            if ($b64data !== '') {
                try {
                    $audioContent = base64_decode($b64data, strict: false);
                    if ($audioContent === false || strlen($audioContent) < 100) {
                        throw new \RuntimeException('Base64 decode falló o archivo demasiado pequeño.');
                    }
                    $body = $this->botService->transcribeAudio($audioContent, "audio.{$ext}");
                    Log::info('SalesBot audio transcribed', [
                        'phone' => $phone,
                        'text'  => substr($body, 0, 100),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('SalesBot Whisper failed', [
                        'phone' => $phone,
                        'error' => $e->getMessage(),
                    ]);
                    if ($this->messenger->isReady()) {
                        $this->messenger->sendText(
                            $waChatId,
                            '¡Hola! 👋 Recibí tu audio pero tuve un problema para procesarlo. ¿Me puedes escribir tu consulta? 😊',
                        );
                    }
                    return response()->json(['ok' => true, 'skipped' => 'audio_transcription_failed']);
                }
            }
        }

        // ── 4. Lógica de activación del bot ───────────────────────────────
        //
        // REGLA: el bot interviene si:
        //   A) Conversación activa (bot_active = true) — sigue el funnel
        //   B) Lead vino del anuncio de Facebook (saludo armó la conversación)
        //   C) Lead nuevo con palabras clave de VetSaaS en su primer mensaje
        //
        // Si Rodrigo pausó manualmente → silencio hasta que vuelva a preguntar por VetSaaS
        // o sea un lead de Facebook Ads ya armado.

        // Si Rodrigo pausó manualmente → silencio total hasta que pulse Reanudar en el panel.
        // Si fue pausa automática → puede reactivarse con trigger de VetSaaS o lead de Facebook.

        $conversation = $this->botService->findExistingConversation($phone, $waChatId);

        if ($conversation !== null) {
            $this->botService->syncContactMetadata($conversation, $phone, $waChatId, $prospectName);
            $this->botService->syncProductFromMessage($conversation, $body);

            if (! $conversation->bot_active) {
                if ($conversation->isManuallyPaused()) {
                    return response()->json(['ok' => true, 'skipped' => 'paused_manual']);
                }

                $trigger        = $this->botService->detectSalesTrigger($body);
                $isFacebookLead = $this->botService->isFacebookLeadConversation($conversation);

                if ($trigger !== null || $isFacebookLead) {
                    $conversation->resumeBot();
                    if ($trigger !== null) {
                        $conversation->activation_trigger = "reactivado:{$trigger}";
                        $conversation->product            = $this->botService->resolveProductFromTrigger($trigger);
                    }
                    $conversation->save();
                } else {
                    return response()->json(['ok' => true, 'skipped' => 'paused']);
                }
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
                product: $this->botService->resolveProductFromTrigger($trigger),
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
            $reply = "Hola 👋 Gracias por escribir. Dame un momento y te respondo enseguida.";
        }

        $product = $this->botService->resolveConversationProduct($conversation);

        // ── 5b. Detectar auto-pausa por preguntas fuera de tema ───────────
        // Si el bot respondió con la frase de despedida (3+ preguntas off-topic),
        // pausamos el bot automáticamente para no seguir consumiendo tokens.
        $offTopicSignal = 'Parece que no es el mejor momento';
        if ($product === SalesBotService::PRODUCT_VETSAAS && str_contains($reply, $offTopicSignal)) {
            $conversation->pauseBotAuto();
            $conversation->activation_trigger = 'auto-pausa:off-topic';
            $conversation->save();
            Log::info('SalesBot auto-paused: off-topic', ['phone' => $phone]);
        }

        // ── 5c. Handoff a administrador (páginas web) ─────────────────────
        if ($this->botService->shouldPauseForAdminHandoff($reply, $product)) {
            $conversation->pauseBotManually();
            $conversation->activation_trigger = 'handoff:admin';
            $conversation->save();
            Log::info('SalesBot paused for admin handoff', ['phone' => $phone, 'product' => $product]);
        }

        // ── 6. Enviar respuesta por WhatsApp ──────────────────────────────
        // Si el lead mandó audio → responder con nota de voz (TTS).
        // Si mandó texto → responder con texto.
        try {
            if ($this->messenger->isReady()) {
                $respondedWithVoice = false;

                if ($isAudio && config('salesbot.tts_enabled') && config('salesbot.audio_enabled')) {
                    try {
                        $audioReply = $this->botService->textToSpeech($reply);
                        $this->messenger->sendVoice($waChatId, $audioReply);
                        $respondedWithVoice = true;
                        Log::info('SalesBot responded with voice', ['phone' => $phone]);
                    } catch (\Throwable $ttsError) {
                        // Si TTS falla, caer de vuelta a texto para no dejar al lead sin respuesta.
                        Log::warning('SalesBot TTS failed, falling back to text', [
                            'phone' => $phone,
                            'error' => $ttsError->getMessage(),
                        ]);
                    }
                }

                if (! $respondedWithVoice) {
                    $this->messenger->sendText($waChatId, $reply);
                }
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
