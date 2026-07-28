<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

use App\Jobs\ProcessClinicBotInboundBatchJob;
use App\Jobs\ProcessSalesBotInboundBatchJob;
use Illuminate\Support\Facades\Config;

/**
 * Encola el procesamiento debounced del lote (SalesBot / ClinicBot).
 *
 * Con cola real (database/redis) usa delay nativo.
 * Con driver sync, usa afterResponse + sleep para no depender de un worker
 * y aún así agrupar líneas rápidas (y responder 200 a OpenWA de inmediato).
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

        self::dispatch($job, $delaySeconds);
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

        self::dispatch($job, $delaySeconds);
    }

    private static function dispatch(object $job, int $delaySeconds): void
    {
        $delay = max(1, $delaySeconds);
        $connection = (string) Config::get('queue.default', 'sync');

        if ($connection === 'sync') {
            dispatch(function () use ($job, $delay): void {
                sleep($delay);
                dispatch_sync($job);
            })->afterResponse();

            return;
        }

        dispatch($job)->delay(now()->addSeconds($delay));
    }
}
