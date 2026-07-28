<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SalesConversation;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Sales\SalesBotService;
use App\Support\WhatsApp\BotInboundDebouncer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa un lote debounced del SalesBot (plataforma / superadmin).
 */
final class ProcessSalesBotInboundBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $channelKey,
        public readonly string $generation,
        public readonly string $conversationId,
        public readonly string $waChatId,
        public readonly string $phone,
        public readonly bool $preferVoiceReply,
    ) {}

    public function handle(
        SalesBotService $botService,
        PlatformWhatsAppMessenger $messenger,
    ): void {
        $debouncer = BotInboundDebouncer::sales();

        $debouncer->withReplyLock($this->channelKey, function () use (
            $botService,
            $messenger,
            $debouncer,
        ): void {
            $joined = $debouncer->claim($this->channelKey, $this->generation);
            if ($joined === null || $joined === '') {
                return;
            }

            $conversation = SalesConversation::query()->find($this->conversationId);
            if ($conversation === null || ! $conversation->bot_active) {
                return;
            }

            try {
                $reply = $botService->reply($conversation, $joined);
            } catch (Throwable $e) {
                Log::error('SalesBot batch reply error', [
                    'phone' => $this->phone,
                    'message' => $e->getMessage(),
                ]);
                $reply = 'Hola 👋 Gracias por escribir. Dame un momento y te respondo enseguida.';
            }

            $product = $botService->resolveConversationProduct($conversation);

            $offTopicSignal = 'Parece que no es el mejor momento';
            if ($product === SalesBotService::PRODUCT_VETSAAS && str_contains($reply, $offTopicSignal)) {
                $conversation->pauseBotAuto();
                $conversation->activation_trigger = 'auto-pausa:off-topic';
                $conversation->save();
                Log::info('SalesBot auto-paused: off-topic', ['phone' => $this->phone]);
            }

            if ($botService->shouldPauseForAdminHandoff($reply, $product)) {
                $conversation->pauseBotManually();
                $conversation->activation_trigger = 'handoff:admin';
                $conversation->save();
                Log::info('SalesBot paused for admin handoff', [
                    'phone' => $this->phone,
                    'product' => $product,
                ]);
            }

            try {
                if (! $messenger->isReady()) {
                    Log::warning('SalesBot: messenger no está listo, respuesta no enviada.', [
                        'phone' => $this->phone,
                    ]);

                    return;
                }

                $respondedWithVoice = false;
                if ($this->preferVoiceReply
                    && config('salesbot.tts_enabled')
                    && config('salesbot.audio_enabled')) {
                    try {
                        $audioReply = $botService->textToSpeech($reply);
                        $messenger->sendVoice($this->waChatId, $audioReply);
                        $respondedWithVoice = true;
                        Log::info('SalesBot responded with voice', ['phone' => $this->phone]);
                    } catch (Throwable $ttsError) {
                        Log::warning('SalesBot TTS failed, falling back to text', [
                            'phone' => $this->phone,
                            'error' => $ttsError->getMessage(),
                        ]);
                    }
                }

                if (! $respondedWithVoice) {
                    $messenger->sendText($this->waChatId, $reply);
                }
            } catch (Throwable $e) {
                Log::error('SalesBot batch send error', [
                    'phone' => $this->phone,
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('ProcessSalesBotInboundBatchJob falló', [
            'phone' => $this->phone,
            'error' => $exception?->getMessage(),
        ]);
    }
}
