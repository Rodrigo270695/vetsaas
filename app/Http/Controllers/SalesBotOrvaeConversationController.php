<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Sales\OrvaeSalesHubClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Conversaciones de ventas sobre VetSaaS que ahora atiende el bot de ORVAE (solo lectura).
 * El historial antiguo sigue en SalesBotConversationController.
 */
final class SalesBotOrvaeConversationController extends Controller
{
    public function index(Request $request, OrvaeSalesHubClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'ORVAE no está configurado (ORVAE_KNOWLEDGE_REFRESH_URL y ORVAE_PROVISION_HMAC_SECRET).'], 503);
        }

        $data = $client->conversations([
            'search' => mb_substr((string) $request->query('search', ''), 0, 80),
            'estado' => (string) $request->query('estado', 'todos'),
            'page' => max(1, (int) $request->query('page', 1)),
            'per_page' => 20,
        ]);

        if ($data === null) {
            return response()->json(['ok' => false, 'error' => 'ORVAE no respondió. Intenta de nuevo en unos segundos.'], 502);
        }

        return response()->json(['ok' => true, ...$data]);
    }

    public function show(string $conversation, OrvaeSalesHubClient $client): JsonResponse
    {
        $data = $client->conversation($conversation);

        if ($data === null) {
            return response()->json(['ok' => false, 'error' => 'No se encontró la conversación en ORVAE.'], 404);
        }

        return response()->json(['ok' => true, ...$data]);
    }
}
