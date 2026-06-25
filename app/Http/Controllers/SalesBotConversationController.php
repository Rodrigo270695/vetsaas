<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SalesConversation;
use App\Services\Sales\SalesBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de conversaciones del bot de ventas.
 *
 * Desde aquí Rodrigo puede:
 *   - Ver todos los leads que el bot ha atendido
 *   - Pausar el bot para tomar el control manualmente (desde el celular)
 *   - Reactivar el bot cuando termina la conversación manual
 *   - Ver el historial de mensajes de cada lead
 */
final class SalesBotConversationController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(
        private readonly SalesBotService $botService,
    ) {}

    public function index(Request $request): Response
    {
        $search    = $request->input('search', '');
        $estado    = $request->input('estado', 'todos');
        $sort      = $request->input('sort', 'last_message_at');
        $direction = $request->input('direction', 'desc');
        $perPage   = (int) $request->input('per_page', 15);

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $query = SalesConversation::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('phone', 'ilike', "%{$search}%")
                  ->orWhere('prospect_name', 'ilike', "%{$search}%");
            });
        }

        if ($estado === 'activo') {
            $query->where('bot_active', true)->where('converted', false);
        } elseif ($estado === 'pausado') {
            $query->where('bot_active', false)->where('converted', false);
        } elseif ($estado === 'convertido') {
            $query->where('converted', true);
        } elseif ($estado === 'frio') {
            $query->where('converted', false)
                ->whereNull('lost_at')
                ->where(function ($q): void {
                    $q->where('turn_count', '>', 0)
                        ->orWhere('activation_trigger', 'like', 'manual:%');
                })
                ->whereRaw("EXTRACT(EPOCH FROM (NOW() - COALESCE(last_message_at, created_at)))/86400 >= 3");
        } elseif ($estado === 'perdido') {
            $query->whereNotNull('lost_at');
        }

        $query->orderBy($sort, $direction);

        $conversations = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (SalesConversation $c): array {
                $messages = $c->messages ?? [];
                $lastMsg  = count($messages) > 0 ? end($messages) : null;

                return [
                    'id'                   => $c->id,
                    'phone'                => $c->phone,
                    'prospect_name'        => $c->prospect_name,
                    'turn_count'           => $c->turn_count,
                    'bot_active'           => $c->bot_active,
                    'converted'            => $c->converted,
                    'activation_trigger'   => $c->activation_trigger,
                    'reactivation_count'   => $c->reactivation_count ?? 0,
                    'last_reactivation_at' => $c->last_reactivation_at?->toIso8601String(),
                    'lost_at'              => $c->lost_at?->toIso8601String(),
                    'last_message_at'      => $c->last_message_at?->toIso8601String(),
                    'last_message_body'    => $lastMsg ? (string) ($lastMsg['content'] ?? '') : null,
                    'last_message_role'    => $lastMsg ? (string) ($lastMsg['role'] ?? '') : null,
                    'created_at'           => $c->created_at->toIso8601String(),
                ];
            });

        $stats = [
            'total'        => SalesConversation::query()->count(),
            'activos'      => SalesConversation::query()->where('bot_active', true)->where('converted', false)->count(),
            'pausados'     => SalesConversation::query()->where('bot_active', false)->where('converted', false)->count(),
            'convertidos'  => SalesConversation::query()->where('converted', true)->count(),
            'frios'        => SalesConversation::query()
                ->where('converted', false)
                ->whereNull('lost_at')
                ->where(function ($q): void {
                    $q->where('turn_count', '>', 0)
                        ->orWhere('activation_trigger', 'like', 'manual:%');
                })
                ->whereRaw("EXTRACT(EPOCH FROM (NOW() - COALESCE(last_message_at, created_at)))/86400 >= 3")
                ->count(),
            'perdidos'     => SalesConversation::query()->whereNotNull('lost_at')->count(),
            'hoy'          => SalesConversation::query()->whereDate('created_at', today())->count(),
            'coincidencias' => $conversations->total(),
        ];

        return Inertia::render('plataforma/salesbot-conversations/index', [
            'conversations' => $conversations,
            'filters'       => [
                'search'    => $search,
                'estado'    => $estado,
                'sort'      => $sort,
                'direction' => $direction,
                'per_page'  => $perPage,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Pausa el bot para esta conversación.
     * Rodrigo toma el control manual desde WhatsApp.
     */
    public function pause(SalesConversation $conversation): JsonResponse
    {
        $conversation->pauseBot();

        return response()->json([
            'ok'         => true,
            'bot_active' => false,
            'message'    => "Bot pausado para {$conversation->phone}. Ahora puedes escribir manualmente.",
        ]);
    }

    /**
     * Reactiva el bot para esta conversación.
     */
    public function resume(SalesConversation $conversation): JsonResponse
    {
        $conversation->resumeBot();

        return response()->json([
            'ok'         => true,
            'bot_active' => true,
            'message'    => "Bot reactivado para {$conversation->phone}.",
        ]);
    }

    /**
     * Elimina la conversación (resetea el lead — el bot lo tratará como nuevo).
     */
    public function destroy(SalesConversation $conversation): JsonResponse
    {
        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Marca el lead como convertido (registró o pagó).
     * Ya no recibirá mensajes de reactivación automática.
     */
    public function convert(SalesConversation $conversation): JsonResponse
    {
        $conversation->markConverted();

        return response()->json([
            'ok'        => true,
            'converted' => true,
            'message'   => "Lead marcado como convertido.",
        ]);
    }

    /**
     * Descarga la plantilla CSV para importar leads fríos.
     */
    public function csvTemplate(): HttpResponse
    {
        $csv = implode("\r\n", [
            'phone,name,note',
            '51987654321,José Rosales,Preguntó por precio del plan Starter',
            '51993897841,Ana Torres,',
            '51961343351,,Mandó mensaje de voz preguntando por VetSaaS',
        ]);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="leads_template.csv"',
        ]);
    }

    /**
     * Importa leads fríos desde un CSV subido por el usuario.
     * Retorna un resumen JSON con importados, duplicados y errores.
     */
    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $file   = $request->file('file');
        $path   = $file->getRealPath();
        $days   = (int) $request->input('days', 5);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return response()->json(['ok' => false, 'error' => 'No se pudo leer el archivo.'], 422);
        }

        $headers  = null;
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map('strtolower', array_map('trim', $row));
                continue;
            }

            if (count($row) === 0 || (count($row) === 1 && trim($row[0]) === '')) {
                continue;
            }

            $data = [];
            foreach ($headers as $i => $header) {
                $data[$header] = trim($row[$i] ?? '');
            }

            $phone = preg_replace('/\D/', '', $data['phone'] ?? '');

            if ($phone === '' || strlen($phone) < 8) {
                $errors[] = "Teléfono inválido: '{$data['phone']}'";
                continue;
            }

            if (SalesConversation::query()->where('phone', $phone)->exists()) {
                $skipped++;
                continue;
            }

            $name = ($data['name'] ?? '') !== '' ? $data['name'] : null;
            $note = ($data['note'] ?? '') !== '' ? $data['note'] : null;

            $messages = $note !== null ? [['role' => 'user', 'content' => $note]] : [];

            SalesConversation::query()->create([
                'phone'              => $phone,
                'wa_chat_id'         => $phone . '@c.us',
                'prospect_name'      => $name,
                'messages'           => $messages,
                'turn_count'         => count($messages),
                'bot_active'         => false,
                'activation_trigger' => 'manual:csv-import',
                'last_message_at'    => now()->subDays($days),
                'reactivation_count' => 0,
                'converted'          => false,
            ]);

            $imported++;
        }

        fclose($handle);

        return response()->json([
            'ok'       => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'message'  => "{$imported} leads importados, {$skipped} duplicados omitidos.",
        ]);
    }

    /**
     * Envía un mensaje de reactivación manual inmediato a un lead frío.
     * Útil para probar o para reactivar manualmente sin esperar el scheduler.
     */
    public function reactivate(SalesConversation $conversation): JsonResponse
    {
        try {
            $message = $this->botService->sendReactivationMessage($conversation);

            return response()->json([
                'ok'                 => true,
                'reactivation_count' => $conversation->reactivation_count,
                'message_sent'       => $message,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
