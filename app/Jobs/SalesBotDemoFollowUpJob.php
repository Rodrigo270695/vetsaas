<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SalesConversation;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Sales\SalesBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Seguimiento corto tras enviar la demo VetSaaS (~5–10 min).
 * Si el lead no respondió, pregunta qué le pareció / si tiene dudas.
 */
final class SalesBotDemoFollowUpJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $conversationId,
    ) {}

    public function handle(
        SalesBotService $botService,
        PlatformWhatsAppMessenger $messenger,
    ): void {
        $conversation = SalesConversation::query()->find($this->conversationId);

        if ($conversation === null) {
            return;
        }

        if (! $botService->shouldSendDemoFollowUp($conversation)) {
            return;
        }

        try {
            $message = $botService->buildDemoFollowUpMessage($conversation);

            if ($message === '' || ! $messenger->isReady()) {
                Log::warning('SalesBot demo follow-up no enviado', [
                    'conversation_id' => $this->conversationId,
                    'ready' => $messenger->isReady(),
                ]);

                return;
            }

            $messenger->sendText($conversation->wa_chat_id, $message);
            $botService->rememberOutgoingBotMessage((string) $conversation->phone, $message);

            $conversation->pushMessage('assistant', '[demo-followup] '.$message);
            $conversation->demo_followup_sent_at = now();
            $conversation->save();
        } catch (Throwable $e) {
            Log::error('SalesBot demo follow-up error', [
                'conversation_id' => $this->conversationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
