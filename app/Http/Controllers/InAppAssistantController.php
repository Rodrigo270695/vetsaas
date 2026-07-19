<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\InAppAssistant\InAppAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class InAppAssistantController extends Controller
{
    public function status(InAppAssistantService $assistant): JsonResponse
    {
        return response()->json([
            'enabled' => (bool) config('in-app-assistant.enabled', true),
            'configured' => $assistant->isConfigured(),
        ]);
    }

    public function chat(Request $request, InAppAssistantService $assistant): JsonResponse
    {
        abort_unless($request->user() !== null, 401);
        abort_unless((bool) config('in-app-assistant.enabled', true), 503);
        abort_unless($assistant->isConfigured(), 503, 'Asistente no configurado (falta OPENAI_API_KEY).');

        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:4000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        try {
            $result = $assistant->chat(
                (string) $data['message'],
                is_array($data['history'] ?? null) ? $data['history'] : [],
            );

            return response()->json([
                'reply' => $result['reply'],
                'used_tools' => $result['used_tools'],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'No pude responder ahora. Inténtalo de nuevo en unos segundos.',
            ], 422);
        }
    }
}
