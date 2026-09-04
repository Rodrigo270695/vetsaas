<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicSetting;
use App\Models\TenantWhatsAppSession;
use App\Services\Agenda\AgendaOwnerRsvpService;
use App\Services\ClinicBot\ClinicBotService;
use App\Services\OpenWa\TenantWhatsAppMessenger;
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
        private readonly WhatsAppContactResolver $contactResolver,
        private readonly TenantManager $tenants,
        private readonly ClinicBotWebhookGuard $guard,
        private readonly ClinicBotWebhookTrafficGuard $traffic,
        private readonly AgendaOwnerRsvpService $agendaRsvp,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('bot-ia.webhook_secret', '');
        if ($secret === '') {
            Log::error('ClinicBot webhook rechazado: BOT_IA_WEBHOOK_SECRET no configurado.');

            return response()->json(['error' => 'Webhook secret not configured'], 503);
        }

        if (! $this->verifyWebhookSecret($request, $secret)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $event = (string) ($payload['event'] ?? $payload['type'] ?? '');
        if ($this->guard->isOutgoingEvent($event)) {
            $this->traffic->recordSkipped();

            return response()->json(['ok' => true, 'skipped' => 'outgoing_event']);
        }

        // Filtrar ANTES de DB/tenancy: OpenWA dispara presence/typing/ack a granel.
        // En prod llegó a ~90% del access.log y saturó PHP-FPM (load ~34).
        $esEventoMensaje = in_array($event, ['message.received', 'onMessage', 'message'], true);
        if (! $esEventoMensaje) {
            $this->traffic->recordSkipped();

            return response()->json(['ok' => true, 'skipped' => 'not_message_event']);
        }

        $fromMe = (bool) ($data['fromMe'] ?? $data['from_me'] ?? false);
        $type = (string) ($data['type'] ?? 'chat');
        $body = trim((string) ($data['body'] ?? $data['content'] ?? $data['text'] ?? ''));

        $openWaSessionId = (string) ($payload['sessionId'] ?? $data['sessionId'] ?? '');

        $waSession = TenantWhatsAppSession::query()
            ->with('tenant')
            ->where('openwa_session_id', $openWaSessionId)
            ->first();

        if ($waSession === null || ! $waSession->isReady()) {
            $this->traffic->recordSkipped();

            return response()->json(['ok' => true, 'skipped' => 'unknown_or_not_ready_session']);
        }

        $tenant = $waSession->tenant;
        if ($tenant === null) {
            $this->traffic->recordSkipped();

            return response()->json(['ok' => true, 'skipped' => 'tenant_missing']);
        }

        if ($this->guard->isLikelyOutgoingMessage($data, $fromMe)) {
            return $this->handleOutgoingMessage($data, $openWaSessionId, $fromMe);
        }

        $contact = $this->contactResolver->resolve(
            $data,
            $openWaSessionId !== '' ? $openWaSessionId : null,
            forOutgoing: false,
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
                $this->traffic->recordSkipped();

                return response()->json(['ok' => true, 'skipped' => 'clinic-bot disabled']);
            }
            if (! SubscriptionBotIaAddon::isActive($subscription)) {
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
                $this->traffic->recordSkipped();

                return response()->json(['ok' => true, 'skipped' => 'assistant_globally_off']);
            }

            $conversation = $this->botService->findOrCreateConversation($phone, $waChatId, $clientName);
            $this->botService->syncContactMetadata($conversation, $phone, $waChatId, $clientName);

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
            $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

            return hash_equals($expected, $signatureToVerify);
        }

        if ($legacySecret !== '') {
            return hash_equals($secret, $legacySecret);
        }

        return false;
    }
}
