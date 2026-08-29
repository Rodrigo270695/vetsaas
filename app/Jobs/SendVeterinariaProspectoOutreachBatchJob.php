<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Prospectos\VeterinariaProspectoOutreachService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envío masivo ("Enviar ahora") de mensajes de contacto a prospectos
 * veterinarios, disparado a mano desde el panel.
 *
 * Se procesa en cola (no de forma síncrona en el request) porque el envío
 * espacía cada mensaje con un delay anti-bloqueo de ~40-60s: con varios
 * mensajes esto puede tardar varios minutos, más de lo que aguanta un
 * request HTTP normal.
 */
final class SendVeterinariaProspectoOutreachBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Hasta 20 mensajes x ~60s + margen de generación IA/WhatsApp. */
    public int $timeout = 1800;

    public function __construct(
        public readonly int $limit,
    ) {}

    public function handle(VeterinariaProspectoOutreachService $service): void
    {
        try {
            $resultado = $service->run($this->limit, origen: 'manual');

            Log::info('SendVeterinariaProspectoOutreachBatchJob: corrida terminada', [
                'limit' => $this->limit,
                ...$resultado,
            ]);
        } catch (Throwable $e) {
            Log::error('SendVeterinariaProspectoOutreachBatchJob: error', [
                'limit' => $this->limit,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
