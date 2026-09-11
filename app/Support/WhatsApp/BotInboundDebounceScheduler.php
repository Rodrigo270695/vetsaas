<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

use App\Jobs\ProcessClinicBotInboundBatchJob;
use App\Jobs\ProcessSalesBotInboundBatchJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Agenda el procesamiento debounced del lote (SalesBot / ClinicBot).
 *
 * En HTTP devolvemos 200 a OpenWA de inmediato. El reply se ejecuta al
 * terminar el request (terminating + sleep) y, de respaldo, en la cola
 * con delay. El claim() del debounce evita doble respuesta.
 */
final class BotInboundDebounceScheduler
{
    public static function scheduleSales(
        string $channelKey,
        string $generation,
        string $conversationId,
        string $waChatId,
        string $phone,
        bool $preferVoiceReply,
        int $delaySeconds,
    ): void {
        $job = new ProcessSalesBotInboundBatchJob(
            channelKey: $channelKey,
            generation: $generation,
            conversationId: $conversationId,
            waChatId: $waChatId,
            phone: $phone,
            preferVoiceReply: $preferVoiceReply,
        );

        self::dispatchDebounced($job, $delaySeconds);
    }

    public static function scheduleClinic(
        string $channelKey,
        string $generation,
        string $tenantSlug,
        string $openWaSessionId,
        string $waChatId,
        string $phone,
        string $clientName,
        int $delaySeconds,
    ): void {
        $job = new ProcessClinicBotInboundBatchJob(
            channelKey: $channelKey,
            generation: $generation,
            tenantSlug: $tenantSlug,
            openWaSessionId: $openWaSessionId,
            waChatId: $waChatId,
            phone: $phone,
            clientName: $clientName,
        );

        self::dispatchDebounced($job, $delaySeconds);
    }

    private static function dispatchDebounced(object $job, int $delaySeconds): void
    {
        if (app()->environment('testing')) {
            dispatch_sync($job);

            return;
        }

        $delay = max(1, $delaySeconds);

        try {
            dispatch($job)->delay(now()->addSeconds($delay));
        } catch (Throwable $e) {
            Log::warning('BotInboundDebounceScheduler: no se pudo encolar el lote', [
                'error' => $e->getMessage(),
            ]);
        }

        app()->terminating(function () use ($job, $delay): void {
            sleep($delay);
            try {
                dispatch_sync($job);
            } catch (Throwable $e) {
                Log::error('BotInboundDebounceScheduler: fallo al procesar lote al terminar el request', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
