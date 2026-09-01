<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Platform\ClosingQueueWhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cadena de envíos de la cola de cierre: un mensaje por job y pausa
 * aleatoria antes del siguiente, para no parecer un robot.
 */
final class SendClosingQueueWhatsAppJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    /**
     * @param  list<string>  $rowIds
     */
    public function __construct(
        public readonly array $rowIds,
        public readonly int $index = 0,
    ) {}

    public function handle(ClosingQueueWhatsAppService $whatsapp): void
    {
        $id = $this->rowIds[$this->index] ?? null;
        if (! is_string($id) || $id === '') {
            return;
        }

        try {
            $whatsapp->sendById($id);
        } catch (Throwable $e) {
            Log::warning('Cola de cierre: falló un WhatsApp, se detiene el lote', [
                'row_id' => $id,
                'index' => $this->index,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $next = $this->index + 1;
        if (! isset($this->rowIds[$next])) {
            return;
        }

        $delay = random_int(
            ClosingQueueWhatsAppService::DELAY_MIN_SECONDS,
            ClosingQueueWhatsAppService::DELAY_MAX_SECONDS,
        );

        self::dispatch($this->rowIds, $next)->delay(now()->addSeconds($delay));
    }
}
