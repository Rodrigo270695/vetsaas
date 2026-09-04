<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Sales\SalesBotService;
use App\Services\Subscriptions\SubscriptionWinBackService;
use App\Support\Agenda\AgendaRsvpFromInbound;
use App\Support\WhatsApp\BotInboundDebouncer;
use App\Support\WhatsApp\BotInboundDebounceScheduler;
use App\Support\WhatsApp\WhatsAppContactResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
 *   Auth         : X-Webhook-Signature / X-OpenWA-Signature (HMAC) o X-Webhook-Secret
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
        private readonly SubscriptionWinBackService $winBack,
        private readonly AgendaRsvpFromInbound $agendaRsvp,
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

        if (! $this->verifyWebhookSecret($request, $secret)) {
            Log::warning('SalesBot webhook 401: firma/secreto no coinciden', [
                'has_signature' => $request->header('X-Webhook-Signature') !== null
                    && $request->header('X-Webhook-Signature') !== '',
                'has_openwa_signature' => $request->header('X-OpenWA-Signature') !== null
                    && $request->header('X-OpenWA-Signature') !== '',
                'has_legacy_secret' => $request->header('X-Webhook-Secret') !== null
                    && $request->header('X-Webhook-Secret') !== '',
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ── 2. Extraer datos del payload ──────────────────────────────────
        // (salesbot.enabled se valida más abajo; el reenganche win-back
        //  debe funcionar aunque el bot de ventas esté pausado/desactivado.)
        $payload = $request->all();

        // Soporta payload directo { body, from, ... } o anidado { data: { ... } }
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        // OpenWA envía el evento como "event" o "type" según la versión.
        $event = (string) ($payload['event'] ?? $payload['type'] ?? '');
        $fromMe = (bool) ($data['fromMe'] ?? $data['from_me'] ?? false);
        $type = (string) ($data['type'] ?? 'chat');
        $body = trim((string) ($data['body'] ?? $data['content'] ?? $data['text'] ?? ''));

        $rsvpIntentEarly = \App\Support\Agenda\AgendaRsvpIntent::parse($body);
        // Aceptar message.received / onMessage / message, y SI/NO aunque el event name sea otro.
        $esEventoMensaje = \App\Support\OpenWa\OpenWaWebhookEvents::isInboundChat($event)
            || ($rsvpIntentEarly !== null && ! $fromMe);

        $isAudio = in_array($type, ['ptt', 'audio'], true);
        $messageId = (string) ($data['id'] ?? '');

        // Mensaje saliente (fromMe): saludo de Facebook → armar bot; otro mensaje → pausar.
        if ($fromMe && $esEventoMensaje) {
            $openWaSessionId = (string) ($payload['sessionId'] ?? $data['sessionId'] ?? '');
            $sessionArg = $openWaSessionId !== '' ? $openWaSessionId : null;
            $contact = $this->contactResolver->resolve($data, $sessionArg, forOutgoing: true);

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
                    'name' => $contact['prospect_name'],
                ]);

                return response()->json(['ok' => true, 'armed' => 'facebook:welcome']);
            }

            // Eco del propio bot (OpenWA reenvía fromMe al enviar texto/voz):
            // NO pausar — si no, el bot queda “pausado” tras cada respuesta automática.
            if ($this->botService->isBotOutgoingEcho($contact['phone'], $body)) {
                return response()->json(['ok' => true, 'skipped' => 'fromMe_bot_echo']);
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
            if ($body !== '') {
                Log::warning('SalesBot webhook ignoró evento', [
                    'event' => $event,
                    'body' => mb_substr($body, 0, 40),
                ]);
            }

            return response()->json(['ok' => true, 'skipped' => true]);
        }

        // Resolver contacto: número real, nombre y chat ID para responder.
        $openWaSessionId = (string) ($payload['sessionId'] ?? $data['sessionId'] ?? '');
        $contact = $this->contactResolver->resolve($data, $openWaSessionId !== '' ? $openWaSessionId : null);

        $waChatId = $contact['wa_chat_id'];
        $phone = $contact['phone'];
        $prospectName = $contact['prospect_name'];

        // Ignorar grupos (@g.us).
        if (str_ends_with($waChatId, '@g.us')) {
            return response()->json(['ok' => true, 'skipped' => 'group']);
        }

        if ($phone === '') {
            return response()->json(['ok' => false, 'reason' => 'no phone'], 422);
        }

        $rsvpIntent = \App\Support\Agenda\AgendaRsvpIntent::parse($body);
        if ($rsvpIntent !== null) {
            Log::warning('Agenda RSVP: inbound sales-bot', [
                'intent' => $rsvpIntent,
                'phone' => $phone,
                'wa_chat_id' => $waChatId,
                'session' => $openWaSessionId,
                'body' => mb_substr($body, 0, 40),
            ]);
        }

        $rsvp = $this->agendaRsvp->tryHandle($openWaSessionId, $phone, $waChatId, $body);
        if ($rsvp !== null) {
            Log::warning('Agenda RSVP: sales-bot confirmó/canceló', [
                'kind' => $rsvp['kind'],
                'intent' => $rsvp['intent'],
                'id' => $rsvp['id'],
                'phone' => $phone,
            ]);
            if ($this->messenger->isReady()) {
                $this->messenger->sendText($waChatId, $rsvp['reply']);
            }

            return response()->json([
                'ok' => true,
                'rsvp' => true,
                'kind' => $rsvp['kind'],
                'intent' => $rsvp['intent'],
            ]);
        }

        if ($rsvpIntent !== null) {
            Log::warning('Agenda RSVP: sales-bot no aplicó el SI/NO', [
                'phone' => $phone,
                'wa_chat_id' => $waChatId,
                'session' => $openWaSessionId,
            ]);
        }

        // Cliente habla con Rodrigo o envía datos de proyecto → no intervenir.
        $conversationForHandoff = $this->botService->findExistingConversation($phone, $waChatId);
        $handoffProduct = $conversationForHandoff !== null
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

        // ── Deduplicación atómica por message ID (evita retries OpenWA en paralelo) ─
        if ($messageId !== '') {
            $cacheKey = 'salesbot_msg_'.md5($messageId);
            if (! Cache::add($cacheKey, 1, 120)) {
                return response()->json(['ok' => true, 'skipped' => 'duplicate']);
            }
        }

        // ── Soporte de audios (Whisper) ────────────────────────────────────
        // OpenWA envía los audios como base64 en data.media.data
        // (mimetype: audio/ogg; codecs=opus).
        if ($body === '' && $isAudio && config('salesbot.audio_enabled')) {
            $media = is_array($data['media'] ?? null) ? $data['media'] : [];
            $b64data = (string) ($media['data'] ?? '');
            $mimetype = (string) ($media['mimetype'] ?? 'audio/ogg');

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
                        'text' => substr($body, 0, 100),
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

        // ── Reenganche Cobros: oferta pendiente → «Sí»/«Acepto» activa 1 mes ──
        $winBackResult = $this->winBack->tryHandleInbound($phone, $waChatId, $prospectName, $body);
        if ($winBackResult['handled']) {
            return response()->json([
                'ok' => true,
                'win_back' => $winBackResult['status'],
                'granted_days' => $winBackResult['granted_days'],
            ]);
        }

        // ── Bot de ventas habilitado ──────────────────────────────────────
        if (! config('salesbot.enabled')) {
            return response()->json(['ok' => false, 'reason' => 'salesbot disabled'], 200);
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

                $trigger = $this->botService->detectSalesTrigger($body);
                $isFacebookLead = $this->botService->isFacebookLeadConversation($conversation);

                if ($trigger !== null || $isFacebookLead) {
                    $conversation->resumeBot();
                    if ($trigger !== null) {
                        $conversation->activation_trigger = "reactivado:{$trigger}";
                        $conversation->product = $this->botService->resolveProductFromTrigger($trigger);
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

        // ── 5. Buffer + debounce: esperar a que el cliente termine de escribir ─
        // Varias líneas rápidas / retries OpenWA → un solo reply de IA.
        $channelKey = 'sales|'.$phone.'|'.$waChatId;
        $debounced = BotInboundDebouncer::sales()->push($channelKey, $body, $messageId !== '' ? $messageId : null);

        BotInboundDebounceScheduler::scheduleSales(
            channelKey: $channelKey,
            generation: $debounced['generation'],
            conversationId: (string) $conversation->id,
            waChatId: $waChatId,
            phone: $phone,
            preferVoiceReply: $isAudio,
            delaySeconds: $debounced['delay_seconds'],
        );

        return response()->json([
            'ok' => true,
            'queued' => true,
            'debounce_seconds' => $debounced['delay_seconds'],
            'buffered' => $debounced['count'],
        ]);
    }

    private function verifyWebhookSecret(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Webhook-Signature', '');
        $openWaSignature = (string) $request->header('X-OpenWA-Signature', '');
        $legacySecret = (string) $request->header('X-Webhook-Secret', '');

        $signatureToVerify = $signature !== '' ? $signature : $openWaSignature;

        if ($signatureToVerify !== '') {
            $rawBody = (string) $request->getContent();
            $hmac = hash_hmac('sha256', $rawBody, $secret);
            $expectedPrefixed = 'sha256='.$hmac;

            if (hash_equals($expectedPrefixed, $signatureToVerify)
                || hash_equals($hmac, $signatureToVerify)) {
                return true;
            }

            // Si la firma falla pero el header legacy coincide, aceptar
            // (OpenWA a veces firma con un secret viejo y aún manda el header).
            if ($legacySecret !== '' && hash_equals($secret, $legacySecret)) {
                return true;
            }

            return false;
        }

        if ($legacySecret !== '') {
            return hash_equals($secret, $legacySecret);
        }

        return false;
    }
}
