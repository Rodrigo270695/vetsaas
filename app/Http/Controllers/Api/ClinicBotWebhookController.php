<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicSetting;
use App\Models\TenantWhatsAppSession;
use App\Services\Agenda\AgendaOwnerRsvpService;
use App\Services\ClinicBot\ClinicBotService;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\OpenWa\TenantWhatsAppMessenger;
use App\Support\Agenda\AgendaRsvpFromInbound;
use App\Support\ClinicBot\ClinicBotWebhookGuard;
use App\Support\ClinicBot\ClinicBotWebhookTrafficGuard;
use App\Support\Subscriptions\SubscriptionBotIaAddon;
use App\Support\WhatsApp\BotInboundDebouncer;
use App\Support\WhatsApp\BotInboundDebounceScheduler;
use App\Support\WhatsApp\WhatsAppContactResolver;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook OpenWA para el asistente IA de clínicas (sesiones por tenant).
 *
 * POST /api/webhooks/clinic-bot
 * Header: X-Webhook-Secret = BOT_IA_WEBHOOK_SECRET
 */
final class ClinicBotWebhookController extends Controller
{
    public function __construct(
        private readonly ClinicBotService $botService,
        private readonly TenantWhatsAppMessenger $messenger,
        private readonly PlatformWhatsAppMessenger $platformMessenger,
        private readonly WhatsAppContactResolver $contactResolver,
        private readonly TenantManager $tenants,
        private readonly ClinicBotWebhookGuard $guard,
        private readonly ClinicBotWebhookTrafficGuard $traffic,
        private readonly AgendaOwnerRsvpService $agendaRsvp,
        private readonly AgendaRsvpFromInbound $agendaRsvpFromInbound,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('bot-ia.webhook_secret', '');
        if ($secret === '') {
            Log::error('ClinicBot webhook rechazado: BOT_IA_WEBHOOK_SECRET no configurado.');

            return response()->json(['error' => 'Webhook secret not configured'], 503);
        }

        if (! $this->verifyWebhookSecret($request, $secret)) {
            Log::warning('ClinicBot webhook 401: firma/secreto no coinciden', [
                'has_signature' => $request->header('X-Webhook-Signature') !== null
                    && $request->header('X-Webhook-Signature') !== '',
                'has_openwa_signature' => $request->header('X-OpenWA-Signature') !== null
                    && $request->header('X-OpenWA-Signature') !== '',
                'has_legacy_secret' => $request->header('X-Webhook-Secret') !== null
                    && $request->header('X-Webhook-Secret') !== '',
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $event = (string) ($payload['event'] ?? $payload['type'] ?? '');
        if ($this->guard->isOutgoingEvent($event)) {
            $this->traffic->recordSkipped();

            return response()->json(['ok' => true, 'skipped' => 'outgoing_event']);
        }

        $fromMe = (bool) ($data['fromMe'] ?? $data['from_me'] ?? false);
        $type = (string) ($data['type'] ?? 'chat');
        $body = $this->extractInboundBody($data);

        // Filtrar ANTES de DB/tenancy: OpenWA dispara presence/typing/ack a granel.
        // En prod llegó a ~90% del access.log y saturó PHP-FPM (load ~34).
        // SI/NO de agenda se acepta aunque el event name no sea message.received.
        $esEventoMensaje = \App\Support\OpenWa\OpenWaWebhookEvents::isInboundChat($event)
            || (\App\Support\Agenda\AgendaRsvpIntent::parse($body) !== null && ! $fromMe);
        if (! $esEventoMensaje) {
            $this->traffic->recordSkipped();

            return response()->json(['ok' => true, 'skipped' => 'not_message_event']);
        }

        $openWaSessionId = $this->extractOpenWaSessionId($payload, $data);

        $waSession = $this->findTenantWhatsAppSession($openWaSessionId, $data);
        if ($waSession !== null && $openWaSessionId === '') {
            $openWaSessionId = (string) $waSession->openwa_session_id;
        }

        $tenantReady = $waSession !== null && $waSession->tenant !== null;

        if (! $tenantReady) {
            if ($this->guard->isLikelyOutgoingMessage($data, $fromMe)) {
                $this->traffic->recordSkipped();

                return response()->json(['ok' => true, 'skipped' => 'unknown_or_not_ready_session']);
            }

            $contact = $this->contactResolver->resolve(
                $data,
                $openWaSessionId !== '' ? $openWaSessionId : null,
                forOutgoing: false,
                allowPlatformSessionFallback: false,
            );
            $rsvp = $body !== ''
                ? $this->agendaRsvpFromInbound->tryHandle(
                    $openWaSessionId,
                    $contact['phone'],
                    $contact['wa_chat_id'],
                    $body,
                )
                : null;
            if ($rsvp !== null) {
                Log::warning('Agenda RSVP: clinic-bot vía sesión de plataforma', [
                    'kind' => $rsvp['kind'],
                    'intent' => $rsvp['intent'],
                    'id' => $rsvp['id'],
                    'phone' => $contact['phone'],
                ]);
                if ($this->platformMessenger->isReady()) {
                    $this->platformMessenger->sendText($contact['wa_chat_id'], $rsvp['reply']);
                }
                $this->traffic->recordProcessed();

                return response()->json([
                    'ok' => true,
                    'rsvp' => true,
                    'kind' => $rsvp['kind'],
                    'intent' => $rsvp['intent'],
                    'via' => 'platform_session',
                ]);
            }

            $this->traffic->recordSkipped();
            Log::info('ClinicBot skipped: sesión de tenant no encontrada', [
                'openwa_session_id' => $openWaSessionId,
            ]);

            return response()->json(['ok' => true, 'skipped' => 'unknown_or_not_ready_session']);
        }

        $tenant = $waSession->tenant;

        if ($this->guard->isLikelyOutgoingMessage($data, $fromMe)) {
            return $this->handleOutgoingMessage($data, $openWaSessionId, $fromMe);
        }

        if (! $waSession->isReady()) {
            $waSession->forceFill([
                'status' => TenantWhatsAppSession::STATUS_READY,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        }

        $contact = $this->contactResolver->resolve(
            $data,
            $openWaSessionId !== '' ? $openWaSessionId : null,
            forOutgoing: false,
            allowPlatformSessionFallback: false,
        );

        $waChatId = $contact['wa_chat_id'];
        $phone = $contact['phone'];
        $clientName = $contact['prospect_name'];

        if (str_ends_with($waChatId, '@g.us')) {
            $this->traffic->recordSkipped();

            return response()->json(['ok' => true, 'skipped' => 'group']);
        }

        if ($phone === '') {
            $this->traffic->recordSkipped();

            return response()->json(['ok' => false, 'reason' => 'no phone'], 422);
        }

        $messageId = (string) ($data['id'] ?? '');

        if ($this->guard->isDuplicateInbound($openWaSessionId, $messageId, $waChatId, $body)) {
            $this->traffic->recordSkipped();

            return response()->json(['ok' => true, 'skipped' => 'duplicate']);
        }

        if ($this->guard->shouldSkipOutboundEcho($openWaSessionId, $waChatId, $body)) {
            $this->traffic->recordSkipped();

            return response()->json(['ok' => true, 'skipped' => 'outbound_echo']);
        }

        if ($this->guard->isBotGeneratedIncomingText($body)) {
            $this->traffic->recordSkipped();

            return response()->json(['ok' => true, 'skipped' => 'bot_echo']);
        }

        return $this->tenants->runForSlug((string) $tenant->slug, function () use (
            $body,
            $type,
            $waSession,
            $waChatId,
            $phone,
            $clientName,
            $openWaSessionId,
            $messageId,
            $tenant,
        ): JsonResponse {
            if ($body === '' && ! in_array($type, ['ptt', 'audio'], true)) {
                $this->traffic->recordSkipped();

                return response()->json(['ok' => true, 'skipped' => 'empty_body']);
            }

            $this->guard->rememberInbound($openWaSessionId, $messageId, $waChatId, $body);

            if ($body !== '') {
                $rsvp = $this->agendaRsvp->tryHandle($phone, $body, $waChatId);
                if ($rsvp !== null) {
                    Log::warning('Agenda RSVP: clinic-bot confirmó/canceló', [
                        'kind' => $rsvp['kind'],
                        'intent' => $rsvp['intent'],
                        'id' => $rsvp['id'],
                        'phone' => $phone,
                    ]);
                    $this->messenger->sendTextWithDeliveryFallback($waSession, $waChatId, $rsvp['reply']);
                    $this->guard->rememberOutbound($openWaSessionId, $waChatId, $rsvp['reply']);
                    $this->guard->markReplied($openWaSessionId, $waChatId);
                    $this->traffic->recordProcessed();

                    return response()->json([
                        'ok' => true,
                        'rsvp' => true,
                        'kind' => $rsvp['kind'],
                        'intent' => $rsvp['intent'],
                    ]);
                }
            }

            $subscription = $tenant->subscriptions()->orderByDesc('created_at')->first();
            if (! (bool) config('bot-ia.enabled', true)) {
                Log::info('ClinicBot skipped: clinic-bot disabled', ['slug' => $tenant->slug]);
                $this->traffic->recordSkipped();

                return response()->json(['ok' => true, 'skipped' => 'clinic-bot disabled']);
            }
            if (! SubscriptionBotIaAddon::isActive($subscription)) {
                Log::info('ClinicBot skipped: add-on inactivo', ['slug' => $tenant->slug]);
                $this->traffic->recordSkipped();

                return response()->json(['ok' => true, 'skipped' => 'bot_ia_inactive']);
            }

            if ($body === '' && in_array($type, ['ptt', 'audio'], true)) {
                if (! ClinicSetting::current()->isBotIaResponding()) {
                    $this->traffic->recordSkipped();

                    return response()->json(['ok' => true, 'skipped' => 'assistant_globally_off']);
                }

                $audioReply = ClinicBotWebhookGuard::AUDIO_UNSUPPORTED_REPLY;
                $this->messenger->sendTextWithDeliveryFallback($waSession, $waChatId, $audioReply);
                $this->guard->rememberOutbound($openWaSessionId, $waChatId, $audioReply);
                $this->guard->markReplied($openWaSessionId, $waChatId);
                $this->traffic->recordProcessed();

                return response()->json(['ok' => true, 'skipped' => 'audio_not_supported']);
            }

            if (! ClinicSetting::current()->isBotIaResponding()) {
                Log::info('ClinicBot skipped: asistente global apagado', ['slug' => $tenant->slug]);
                $this->traffic->recordSkipped();

                return response()->json(['ok' => true, 'skipped' => 'assistant_globally_off']);
            }

            $conversation = $this->botService->findOrCreateConversation($phone, $waChatId, $clientName);
            $this->botService->syncContactMetadata($conversation, $phone, $waChatId, $clientName);
            $conversation->forceFill(['last_message_at' => now()])->save();

            if (! $conversation->bot_active) {
                if ($conversation->isManuallyPaused()) {
                    $this->traffic->recordSkipped();

                    return response()->json(['ok' => true, 'skipped' => 'paused_manual']);
                }

                $conversation->resumeBot();
            }

            $channelKey = 'clinic|'.$tenant->slug.'|'.$openWaSessionId.'|'.$waChatId;
            $debounced = BotInboundDebouncer::clinic()->push(
                $channelKey,
                $body,
                $messageId !== '' ? $messageId : null,
            );

            BotInboundDebounceScheduler::scheduleClinic(
                channelKey: $channelKey,
                generation: $debounced['generation'],
                tenantSlug: (string) $tenant->slug,
                openWaSessionId: $openWaSessionId,
                waChatId: $waChatId,
                phone: $phone,
                clientName: $clientName,
                delaySeconds: $debounced['delay_seconds'],
            );

            $this->traffic->recordProcessed();

            if (app()->environment('testing')) {
                return response()->json(['ok' => true, 'replied' => true]);
            }

            return response()->json([
                'ok' => true,
                'queued' => true,
                'debounce_seconds' => $debounced['delay_seconds'],
                'buffered' => $debounced['count'],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleOutgoingMessage(array $data, string $openWaSessionId, bool $fromMe): JsonResponse
    {
        $contact = $this->contactResolver->resolve(
            $data,
            $openWaSessionId !== '' ? $openWaSessionId : null,
            forOutgoing: $fromMe,
            allowPlatformSessionFallback: false,
        );

        $phone = $contact['phone'];
        $waChatId = $contact['wa_chat_id'];

        if ($phone !== '' && ! str_ends_with($waChatId, '@g.us')) {
            $waSession = TenantWhatsAppSession::query()
                ->with('tenant')
                ->where('openwa_session_id', $openWaSessionId)
                ->first();

            $tenant = $waSession?->tenant;
            if ($tenant !== null) {
                $this->tenants->runForSlug((string) $tenant->slug, function () use ($phone, $waChatId): void {
                    $conversation = $this->botService->findConversation($phone, $waChatId);
                    if ($conversation !== null && $conversation->bot_active) {
                        $conversation->pauseBotAuto();
                        Log::info('ClinicBot auto-paused: mensaje manual de la clínica', [
                            'phone' => $phone,
                        ]);
                    }
                });
            }
        }

        $body = trim((string) ($data['body'] ?? $data['content'] ?? $data['text'] ?? ''));
        if ($body !== '' && $openWaSessionId !== '' && $waChatId !== '') {
            $this->guard->rememberOutbound($openWaSessionId, $waChatId, $body);
        }

        $this->traffic->recordSkipped();

        return response()->json(['ok' => true, 'skipped' => 'fromMe']);
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    private function extractOpenWaSessionId(array $payload, array $data): string
    {
        foreach (['sessionId', 'session_id'] as $key) {
            foreach ([$payload[$key] ?? null, $data[$key] ?? null] as $value) {
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        $session = $payload['session'] ?? $data['session'] ?? null;
        if (is_array($session)) {
            $id = $session['id'] ?? $session['sessionId'] ?? null;
            if (is_string($id) && trim($id) !== '') {
                return trim($id);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractInboundBody(array $data): string
    {
        foreach (['body', 'content', 'text', 'caption'] as $key) {
            $value = $data[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $message = $data['message'] ?? null;
        if (! is_array($message)) {
            return '';
        }

        $conversation = $message['conversation'] ?? null;
        if (is_string($conversation) && trim($conversation) !== '') {
            return trim($conversation);
        }

        $extended = $message['extendedTextMessage'] ?? null;
        if (is_array($extended)) {
            $text = $extended['text'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findTenantWhatsAppSession(string $openWaSessionId, array $data): ?TenantWhatsAppSession
    {
        if ($openWaSessionId !== '') {
            $byId = TenantWhatsAppSession::query()
                ->with('tenant')
                ->where('openwa_session_id', $openWaSessionId)
                ->first();
            if ($byId !== null) {
                return $byId;
            }
        }

        $to = (string) ($data['to'] ?? '');
        $digits = preg_replace('/\D/', '', preg_replace('/@.*$/', '', $to) ?? $to) ?? '';
        if (strlen($digits) < 8) {
            return null;
        }

        $tail = substr($digits, -9);

        return TenantWhatsAppSession::query()
            ->with('tenant')
            ->whereNotNull('phone')
            ->where('phone', 'like', '%'.$tail.'%')
            ->first();
    }
}
