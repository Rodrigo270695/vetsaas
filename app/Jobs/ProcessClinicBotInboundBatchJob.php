<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ClinicSetting;
use App\Models\TenantWhatsAppSession;
use App\Services\ClinicBot\ClinicBotService;
use App\Services\OpenWa\TenantWhatsAppMessenger;
use App\Support\Audit\AuditActor;
use App\Support\ClinicBot\ClinicBotWebhookGuard;
use App\Support\WhatsApp\BotInboundDebouncer;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa un lote debounced del ClinicBot (asistente IA por tenant).
 */
final class ProcessClinicBotInboundBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $channelKey,
        public readonly string $generation,
        public readonly string $tenantSlug,
        public readonly string $openWaSessionId,
        public readonly string $waChatId,
        public readonly string $phone,
        public readonly string $clientName,
    ) {}

    public function handle(
        ClinicBotService $botService,
        TenantWhatsAppMessenger $messenger,
        ClinicBotWebhookGuard $guard,
        TenantManager $tenants,
    ): void {
        $debouncer = BotInboundDebouncer::clinic();

        $waSession = TenantWhatsAppSession::query()
            ->with('tenant')
            ->where('openwa_session_id', $this->openWaSessionId)
            ->first();

        if ($waSession === null || ! $waSession->isReady()) {
            return;
        }

        $tenants->runForSlug($this->tenantSlug, function () use (
            $botService,
            $messenger,
            $guard,
            $debouncer,
            $waSession,
        ): void {
            if (! ClinicSetting::current()->isBotIaResponding()) {
                return;
            }

            $debouncer->withReplyLock($this->channelKey, function () use (
                $botService,
                $messenger,
                $guard,
                $debouncer,
                $waSession,
            ): void {
                $joined = $debouncer->claim($this->channelKey, $this->generation);
                if ($joined === null || $joined === '') {
                    return;
                }

                $conversation = $botService->findOrCreateConversation(
                    $this->phone,
                    $this->waChatId,
                    $this->clientName !== '' ? $this->clientName : null,
                );
                $botService->syncContactMetadata(
                    $conversation,
                    $this->phone,
                    $this->waChatId,
                    $this->clientName !== '' ? $this->clientName : null,
                );

                if (! $conversation->bot_active) {
                    if ($conversation->isManuallyPaused()) {
                        return;
                    }
                    $conversation->resumeBot();
                }

                try {
                    $reply = AuditActor::runAsBotIa(
                        $this->phone,
                        fn (): string => $botService->reply($conversation, $joined),
                    );
                } catch (Throwable $e) {
                    Log::error('ClinicBot batch reply error', [
                        'phone' => $this->phone,
                        'error' => $e->getMessage(),
                    ]);

                    if ($guard->shouldNotifyUserOfFailure($e)) {
                        try {
                            $errorReply = ClinicBotWebhookGuard::ERROR_REPLY;
                            $messenger->sendTextWithDeliveryFallback($waSession, $this->waChatId, $errorReply);
                            $guard->rememberOutbound($this->openWaSessionId, $this->waChatId, $errorReply);
                        } catch (Throwable) {
                            // ignore secondary failure
                        }
                    }

                    return;
                }

                try {
                    $messenger->sendTextWithDeliveryFallback($waSession, $this->waChatId, $reply);
                    $guard->rememberOutbound($this->openWaSessionId, $this->waChatId, $reply);
                    $guard->markReplied($this->openWaSessionId, $this->waChatId);
                    Log::info('ClinicBot responded', ['phone' => $this->phone]);
                } catch (Throwable $e) {
                    Log::error('ClinicBot batch send error', [
                        'phone' => $this->phone,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('ProcessClinicBotInboundBatchJob falló', [
            'phone' => $this->phone,
            'tenant' => $this->tenantSlug,
            'error' => $exception?->getMessage(),
        ]);
    }
}
