<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SalesConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $query->where('bot_active', true);
        } elseif ($estado === 'pausado') {
            $query->where('bot_active', false);
        }

        $query->orderBy($sort, $direction);

        $conversations = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(static function (SalesConversation $c): array {
                $messages = $c->messages ?? [];
                $lastMsg  = count($messages) > 0 ? end($messages) : null;

                return [
                    'id'                 => $c->id,
                    'phone'              => $c->phone,
                    'prospect_name'      => $c->prospect_name,
                    'turn_count'         => $c->turn_count,
                    'bot_active'         => $c->bot_active,
                    'activation_trigger' => $c->activation_trigger,
                    'last_message_at'    => $c->last_message_at?->toIso8601String(),
                    'last_message_body'  => $lastMsg ? (string) ($lastMsg['content'] ?? '') : null,
                    'last_message_role'  => $lastMsg ? (string) ($lastMsg['role'] ?? '') : null,
                    'created_at'         => $c->created_at->toIso8601String(),
                ];
            });

        $stats = [
            'total'    => SalesConversation::query()->count(),
            'activos'  => SalesConversation::query()->where('bot_active', true)->count(),
            'pausados' => SalesConversation::query()->where('bot_active', false)->count(),
            'hoy'      => SalesConversation::query()->whereDate('created_at', today())->count(),
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
}
