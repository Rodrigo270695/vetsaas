<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantWhatsAppSession;
use App\Services\ClinicBot\ClinicBotService;
use App\Services\OpenWa\TenantWhatsAppMessenger;
use App\Support\Subscriptions\SubscriptionBotIaAddon;
use App\Support\WhatsApp\WhatsAppContactResolver;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $contact = $this->contactResolver->resolve(
            $data,
            $openWaSessionId !== '' ? $openWaSessionId : null,
            forOutgoing: $fromMe,
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

        return $this->tenants->runForSlug((string) $tenant->slug, function () use (
            $fromMe,
            $body,
            $type,
            $data,
            $waSession,
            $waChatId,
            $phone,
            $clientName,
        ): JsonResponse {
            if ($fromMe) {
                $conversation = $this->botService->findConversation($phone, $waChatId);
                if ($conversation !== null && $conversation->bot_active) {
                    $conversation->pauseBotAuto();
                    Log::info('ClinicBot auto-paused: mensaje manual de la clínica', [
                        'phone' => $phone,
                    ]);
                }

                return response()->json(['ok' => true, 'skipped' => 'fromMe']);
            }

            if ($body === '' && ! in_array($type, ['ptt', 'audio'], true)) {
                return response()->json(['ok' => true, 'skipped' => 'empty_body']);
            }

            if ($body === '' && in_array($type, ['ptt', 'audio'], true)) {
                $this->messenger->sendText(
                    $waSession,
                    $waChatId,
                    'Hola 👋 Por ahora respondo mejor por texto. ¿Puedes escribir tu consulta?',
                );

                return response()->json(['ok' => true, 'skipped' => 'audio_not_supported']);
            }

            $messageId = (string) ($data['id'] ?? '');
            if ($messageId !== '') {
                $cacheKey = 'clinicbot_msg_'.md5($messageId);
                if (Cache::has($cacheKey)) {
                    return response()->json(['ok' => true, 'skipped' => 'duplicate']);
                }
                Cache::put($cacheKey, 1, 60);
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
                $reply = $this->botService->reply($conversation, $body);
                $this->messenger->sendText($waSession, $waChatId, $reply);

                Log::info('ClinicBot responded', ['phone' => $phone]);

                return response()->json(['ok' => true, 'replied' => true]);
            } catch (\Throwable $e) {
                Log::error('ClinicBot reply error', [
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);

                try {
                    $this->messenger->sendText(
                        $waSession,
                        $waChatId,
                        'Disculpa, tuve un problema al procesar tu mensaje. Un asistente de la clínica te ayudará pronto.',
                    );
                } catch (\Throwable) {
                    // ignore secondary failure
                }

                return response()->json(['ok' => false, 'error' => 'reply_failed'], 500);
            }
        });
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
