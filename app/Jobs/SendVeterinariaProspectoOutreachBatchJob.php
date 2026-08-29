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
 * Recibe la lista EXACTA de IDs a contactar (ya resuelta por el
 * controller respetando los filtros que el usuario tenía aplicados en
 * ese momento), no un simple límite — así el envío nunca "se escapa" a
 * prospectos fuera del filtro.
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

    /**
     * @param  list<string>  $prospectoIds
     */
    public function __construct(
        public readonly array $prospectoIds,
    ) {}

    public function handle(VeterinariaProspectoOutreachService $service): void
    {
        try {
            $resultado = $service->runForIds($this->prospectoIds, origen: 'manual');

            Log::info('SendVeterinariaProspectoOutreachBatchJob: corrida terminada', [
                'total_ids' => count($this->prospectoIds),
                ...$resultado,
            ]);
        } catch (Throwable $e) {
            Log::error('SendVeterinariaProspectoOutreachBatchJob: error', [
                'total_ids' => count($this->prospectoIds),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
