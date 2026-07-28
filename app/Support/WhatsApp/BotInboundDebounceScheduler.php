<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

use App\Jobs\ProcessClinicBotInboundBatchJob;
use App\Jobs\ProcessSalesBotInboundBatchJob;

/**
 * Agenda el procesamiento debounced del lote (SalesBot / ClinicBot).
 *
 * Siempre usa afterResponse + sleep (no la cola database/redis): el chatbot
 * de WhatsApp no debe depender de `queue:work`. Si el worker está caído,
 * OpenWA seguiría “conectado” pero el bot nunca respondería.
 *
 * afterResponse permite devolver 200 a OpenWA de inmediato (evita retries)
 * y el sleep agrupa líneas rápidas del cliente en un solo reply.
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

        self::dispatchAfterResponse($job, $delaySeconds);
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

        self::dispatchAfterResponse($job, $delaySeconds);
    }

    private static function dispatchAfterResponse(object $job, int $delaySeconds): void
    {
        $delay = max(1, $delaySeconds);

        dispatch(function () use ($job, $delay): void {
            sleep($delay);
            dispatch_sync($job);
        })->afterResponse();
    }
}
