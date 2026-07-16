<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicSetting;
use App\Models\TenantWhatsAppSession;
use App\Services\ClinicBot\ClinicBotService;
use App\Services\OpenWa\TenantWhatsAppMessenger;
use App\Support\Audit\AuditActor;
use App\Support\ClinicBot\ClinicBotWebhookGuard;
use App\Support\Subscriptions\SubscriptionBotIaAddon;
use App\Support\WhatsApp\WhatsAppContactResolver;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifyWebhookSecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! (bool) config('bot-ia.enabled', true)) {
            return response()->json(['ok' => false, 'reason' => 'clinic-bot disabled']);
        }

        $payload = $request->all();
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $event = (string) ($payload['event'] ?? $payload['type'] ?? '');
        if ($this->guard->isOutgoingEvent($event)) {
            return response()->json(['ok' => true, 'skipped' => 'outgoing_event']);
        }

        $fromMe = (bool) ($data['fromMe'] ?? $data['from_me'] ?? false);
        $type = (string) ($data['type'] ?? 'chat');
        $body = trim((string) ($data['body'] ?? $data['content'] ?? $data['text'] ?? ''));

        $esEventoMensaje = in_array($event, ['message.received', 'onMessage', 'message'], true);

        $openWaSessionId = (string) ($payload['sessionId'] ?? $data['sessionId'] ?? '');

        $waSession = TenantWhatsAppSession::query()
            ->with('tenant')
            ->where('openwa_session_id', $openWaSessionId)
            ->first();

        if ($waSession === null || ! $waSession->isReady()) {
            return response()->json(['ok' => true, 'skipped' => 'unknown_or_not_ready_session']);
        }

        $tenant = $waSession->tenant;
        if ($tenant === null) {
            return response()->json(['ok' => true, 'skipped' => 'tenant_missing']);
        }

        $subscription = $tenant->subscriptions()->orderByDesc('created_at')->first();
        if (! SubscriptionBotIaAddon::isActive($subscription)) {
            return response()->json(['ok' => true, 'skipped' => 'bot_ia_inactive']);
        }

        if (! $esEventoMensaje) {
            return response()->json(['ok' => true, 'skipped' => 'not_message_event']);
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
            return response()->json(['ok' => true, 'skipped' => 'group']);
        }

        if ($phone === '') {
            return response()->json(['ok' => false, 'reason' => 'no phone'], 422);
        }

        $messageId = (string) ($data['id'] ?? '');

        if ($this->guard->isDuplicateInbound($openWaSessionId, $messageId, $waChatId, $body)) {
            return response()->json(['ok' => true, 'skipped' => 'duplicate']);
        }

        if ($this->guard->shouldSkipOutboundEcho($openWaSessionId, $waChatId, $body)) {
            return response()->json(['ok' => true, 'skipped' => 'outbound_echo']);
        }

        if ($this->guard->isBotGeneratedIncomingText($body)) {
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
        ): JsonResponse {
            if ($body === '' && ! in_array($type, ['ptt', 'audio'], true)) {
                return response()->json(['ok' => true, 'skipped' => 'empty_body']);
            }

            if ($body === '' && in_array($type, ['ptt', 'audio'], true)) {
                if (! ClinicSetting::current()->isBotIaResponding()) {
                    return response()->json(['ok' => true, 'skipped' => 'assistant_globally_off']);
                }

                $audioReply = ClinicBotWebhookGuard::AUDIO_UNSUPPORTED_REPLY;
                $this->messenger->sendTextWithDeliveryFallback($waSession, $waChatId, $audioReply);
                $this->guard->rememberOutbound($openWaSessionId, $waChatId, $audioReply);
                $this->guard->markReplied($openWaSessionId, $waChatId);
                $this->guard->rememberInbound($openWaSessionId, $messageId, $waChatId, $body);

                return response()->json(['ok' => true, 'skipped' => 'audio_not_supported']);
            }

            $this->guard->rememberInbound($openWaSessionId, $messageId, $waChatId, $body);

            if (! ClinicSetting::current()->isBotIaResponding()) {
                return response()->json(['ok' => true, 'skipped' => 'assistant_globally_off']);
            }

            if ($this->guard->isRateLimited($openWaSessionId, $waChatId)) {
                return response()->json(['ok' => true, 'skipped' => 'rate_limited']);
            }

            $conversation = $this->botService->findOrCreateConversation($phone, $waChatId, $clientName);
            $this->botService->syncContactMetadata($conversation, $phone, $waChatId, $clientName);

            if (! $conversation->bot_active) {
                if ($conversation->isManuallyPaused()) {
                    return response()->json(['ok' => true, 'skipped' => 'paused_manual']);
                }

                $conversation->resumeBot();
            }

            try {
                $reply = AuditActor::runAsBotIa(
                    $phone,
                    fn (): string => $this->botService->reply($conversation, $body),
                );
            } catch (Throwable $e) {
                Log::error('ClinicBot reply error', [
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);

                // Solo se disculpa cuando la IA no pudo generar respuesta.
                if ($this->guard->shouldNotifyUserOfFailure($e)) {
                    try {
                        $errorReply = ClinicBotWebhookGuard::ERROR_REPLY;
                        $this->messenger->sendTextWithDeliveryFallback($waSession, $waChatId, $errorReply);
                        $this->guard->rememberOutbound($openWaSessionId, $waChatId, $errorReply);
                    } catch (Throwable) {
                        // ignore secondary failure
                    }
                }

                return response()->json(['ok' => false, 'error' => 'reply_failed']);
            }

            try {
                // Con fallback: si OpenWA responde tarde (timeout / 5xx tardío)
                // la respuesta normalmente ya salió. Y si el envío falla de
                // verdad, NO se manda el mensaje de disculpa: llegaría junto a
                // una respuesta que sí se entregó y confunde al cliente.
                $this->messenger->sendTextWithDeliveryFallback($waSession, $waChatId, $reply);
                $this->guard->rememberOutbound($openWaSessionId, $waChatId, $reply);
                $this->guard->markReplied($openWaSessionId, $waChatId);

                Log::info('ClinicBot responded', ['phone' => $phone]);

                return response()->json(['ok' => true, 'replied' => true]);
            } catch (Throwable $e) {
                Log::error('ClinicBot send error', [
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['ok' => false, 'error' => 'reply_failed']);
            }
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

        return response()->json(['ok' => true, 'skipped' => 'fromMe']);
    }

    private function verifyWebhookSecret(Request $request): bool
    {
        $secret = (string) config('bot-ia.webhook_secret', '');

        if ($secret === '') {
            return true;
        }

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
