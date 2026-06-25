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
        $csv = "\xEF\xBB\xBF".implode("\r\n", [
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
        try {
            $request->validate([
                'file' => ['required', 'file', 'max:5120'],
                'days' => ['nullable', 'integer', 'min:1', 'max:30'],
            ]);

            $uploaded = $request->file('file');
            if ($uploaded === null) {
                return response()->json(['ok' => false, 'error' => 'No se recibió el archivo.'], 422);
            }

            $extension = strtolower($uploaded->getClientOriginalExtension());
            if (! in_array($extension, ['csv', 'txt'], true)) {
                return response()->json([
                    'ok'     => false,
                    'error'  => 'El archivo debe ser .csv o .txt',
                    'errors' => ['Formato no válido. Usa un archivo CSV.'],
                ], 422);
            }

            $path = $uploaded->getRealPath();
            if ($path === false || ! is_readable($path)) {
                return response()->json(['ok' => false, 'error' => 'No se pudo leer el archivo.'], 422);
            }

            $days = (int) $request->input('days', 5);

            $handle = fopen($path, 'r');
            if ($handle === false) {
                return response()->json(['ok' => false, 'error' => 'No se pudo abrir el archivo.'], 422);
            }

            $headers     = null;
            $imported    = 0;
            $duplicates  = [];
            $errors      = [];
            $seenInFile  = [];

            while (($row = fgetcsv($handle)) !== false) {
                if ($headers === null) {
                    $headers = array_map(function (string $header): string {
                        return strtolower(trim($this->stripBom($header)));
                    }, $row);
                    continue;
                }

                if (count($row) === 0 || (count($row) === 1 && trim($row[0]) === '')) {
                    continue;
                }

                $data = [];
                foreach ($headers as $i => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $data[$header] = $this->sanitizeUtf8((string) ($row[$i] ?? ''));
                }

                $rawPhone = $data['phone'] ?? '';
                $phone    = $this->botService->normalizeLeadPhone($rawPhone);
                $name     = ($data['name'] ?? '') !== '' ? $data['name'] : null;
                $note     = ($data['note'] ?? '') !== '' ? $data['note'] : null;

                if ($phone === '' || strlen($phone) < 8) {
                    $errors[] = "Teléfono inválido: '{$rawPhone}'";
                    continue;
                }

                if (isset($seenInFile[$phone])) {
                    $duplicates[] = [
                        'phone'  => $phone,
                        'name'   => $name,
                        'reason' => 'repetido_en_csv',
                    ];
                    continue;
                }
                $seenInFile[$phone] = true;

                if (SalesConversation::query()->where('phone', $phone)->exists()) {
                    $duplicates[] = [
                        'phone'  => $phone,
                        'name'   => $name,
                        'reason' => 'ya_registrado',
                    ];
                    continue;
                }

                $messages = $note !== null ? [['role' => 'user', 'content' => $note]] : [];

                SalesConversation::query()->create([
                    'phone'              => $phone,
                    'wa_chat_id'         => $phone.'@c.us',
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
                'ok'         => true,
                'imported'   => $imported,
                'skipped'    => count($duplicates),
                'duplicates' => $duplicates,
                'errors'     => $errors,
                'message'    => "{$imported} leads importados, ".count($duplicates).' duplicados omitidos.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('SalesBot importCsv failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok'         => false,
                'error'      => $e->getMessage(),
                'imported'   => 0,
                'skipped'    => 0,
                'duplicates' => [],
                'errors'     => ['Error del servidor: '.$e->getMessage()],
            ], 500);
        }
    }

    private function stripBom(string $value): string
    {
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            return substr($value, 3);
        }

        return $value;
    }

    /**
     * Limpia texto del CSV para guardarlo en JSON (PostgreSQL / Laravel).
     * Excel en Windows suele exportar Latin-1 y rompe tildes en UTF-8.
     */
    private function sanitizeUtf8(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            $converted = mb_convert_encoding($value, 'UTF-8', 'Windows-1252, ISO-8859-1');
            if (is_string($converted)) {
                $value = $converted;
            }
        }

        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $clean !== false ? $clean : '';
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

    /**
     * Fuerza al bot a responder a un lead existente (desde el panel web).
     */
    public function engage(Request $request, SalesConversation $conversation): JsonResponse
    {
        $message = trim((string) $request->input('message', ''));

        try {
            $result = $this->botService->engageConversation($conversation, $message);

            return response()->json([
                'ok'           => true,
                'reply'        => $result['reply'],
                'sent'         => $result['sent'],
                'bot_active'   => true,
                'turn_count'   => $result['conversation']->turn_count,
                'message_sent' => $result['reply'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activa el bot para un número que aún no está en la lista (lead de Facebook sin registrar).
     */
    public function engagePhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'   => ['required', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:2000'],
            'name'    => ['nullable', 'string', 'max:120'],
        ]);

        $phone   = (string) $validated['phone'];
        $message = trim((string) ($validated['message'] ?? ''));
        $name    = isset($validated['name']) ? trim((string) $validated['name']) : null;
        $name    = $name !== '' ? $name : null;

        try {
            $result = $this->botService->engagePhone($phone, $message, $name);

            $conversation = $result['conversation'];

            return response()->json([
                'ok'             => true,
                'reply'          => $result['reply'],
                'sent'           => $result['sent'],
                'conversation_id' => $conversation->id,
                'phone'          => $conversation->phone,
                'message_sent'   => $result['reply'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
