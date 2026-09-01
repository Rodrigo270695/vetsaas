<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SendClosingQueueWhatsAppJob;
use App\Services\Platform\ClosingQueueAlreadySentException;
use App\Services\Platform\ClosingQueueService;
use App\Services\Platform\ClosingQueueWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PlataformaClosingQueueController extends Controller
{
    public function index(
        Request $request,
        ClosingQueueService $queue,
        ClosingQueueWhatsAppService $whatsapp,
    ): Response {
        $payload = $queue->paginate(
            trim((string) $request->string('search', '')),
            (string) $request->string('scope', 'hoy'),
            (int) $request->integer('per_page', 15),
            max(1, (int) $request->integer('page', 1)),
        );
        $payload['wa_ready'] = $whatsapp->isReady();
        $payload['wa_from_phone'] = $whatsapp->connectedPhone();
        $payload['wa_bulk_max'] = ClosingQueueWhatsAppService::MAX_BULK;
        $payload['wa_delay_min'] = ClosingQueueWhatsAppService::DELAY_MIN_SECONDS;
        $payload['wa_delay_max'] = ClosingQueueWhatsAppService::DELAY_MAX_SECONDS;

        return Inertia::render('plataforma/cola-cierre/index', $payload);
    }

    public function send(
        Request $request,
        ClosingQueueWhatsAppService $whatsapp,
    ): JsonResponse {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:80'],
            'force' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $whatsapp->sendById(
                (string) $data['id'],
                (bool) ($data['force'] ?? false),
            );
        } catch (ClosingQueueAlreadySentException $e) {
            return response()->json([
                'ok' => false,
                'already_sent' => true,
                'sent_at' => $e->sentAtIso,
                'message' => $e->getMessage(),
            ], 409);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'WhatsApp enviado a '.$result['name'].'.',
            'from_phone' => $result['from_phone'],
        ]);
    }

    public function sendBulk(
        Request $request,
        ClosingQueueService $queue,
        ClosingQueueWhatsAppService $whatsapp,
    ): JsonResponse {
        $max = ClosingQueueWhatsAppService::MAX_BULK;
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.$max],
            'ids.*' => ['string', 'max:80'],
        ]);

        if (! $whatsapp->isReady()) {
            return response()->json([
                'ok' => false,
                'message' => 'WhatsApp de plataforma no está conectado.',
            ], 422);
        }

        $ids = [];
        foreach ($queue->rowsByIds(array_values($data['ids'])) as $row) {
            $phone = preg_replace('/\D+/', '', (string) ($row['phone'] ?? '')) ?? '';
            if ($phone === '' || trim((string) ($row['script'] ?? '')) === '') {
                continue;
            }
            if (trim((string) ($row['last_sent_at'] ?? '')) !== '') {
                continue;
            }
            $ids[] = (string) $row['id'];
            if (count($ids) >= $max) {
                break;
            }
        }

        if ($ids === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Ninguna fila pendiente: las seleccionadas ya tienen envío o no tienen celular.',
            ], 422);
        }

        SendClosingQueueWhatsAppJob::dispatch($ids);

        $count = count($ids);
        $minutes = (int) ceil((($count - 1) * ClosingQueueWhatsAppService::DELAY_MIN_SECONDS) / 60);

        return response()->json([
            'ok' => true,
            'queued' => $count,
            'message' => $count === 1
                ? 'Se encoló 1 WhatsApp.'
                : "Se encolaron {$count} WhatsApp. Van a salir con ~1 minuto entre cada uno (unos {$minutes} min en total).",
        ]);
    }
}
