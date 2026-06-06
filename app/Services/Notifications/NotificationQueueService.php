<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationQueue;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

final class NotificationQueueService
{
    /**
     * @return NotificationQueue|null null si ya existía (dedupe)
     */
    public function enqueue(
        string $tipo,
        string $destinatario,
        string $cuerpo,
        CarbonInterface $enviarAt,
        ?string $destinatarioNombre = null,
        ?string $referenciaTipo = null,
        ?string $referenciaId = null,
        ?string $dedupeKey = null,
        int $prioridad = 5,
    ): ?NotificationQueue {
        try {
            return NotificationQueue::query()->create([
                'tipo' => $tipo,
                'canal' => NotificationQueue::CANAL_WHATSAPP,
                'destinatario' => $destinatario,
                'destinatario_nombre' => $destinatarioNombre,
                'cuerpo' => $cuerpo,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
                'dedupe_key' => $dedupeKey,
                'enviar_at' => $enviarAt,
                'prioridad' => $prioridad,
                'estado' => NotificationQueue::ESTADO_PENDIENTE,
                'max_intentos' => (int) config('openwa.max_attempts', 3),
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicateKey($e)) {
                return null;
            }

            throw $e;
        }
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'uq_notifications_queue_dedupe')
            || str_contains($message, 'duplicate key')
            || $e->getCode() === '23505';
    }
}
