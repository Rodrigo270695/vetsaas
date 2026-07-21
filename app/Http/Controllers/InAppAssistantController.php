<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InAppAssistant\InAppAssistantService;
use App\Services\InAppAssistant\InAppAssistantUsageLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class InAppAssistantController extends Controller
{
    public function status(
        Request $request,
        InAppAssistantService $assistant,
        InAppAssistantUsageLimiter $limiter,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canUseAssistant($user), 403);

        $usage = $limiter->snapshot($user);

        return response()->json([
            'enabled' => (bool) config('in-app-assistant.enabled', true),
            'configured' => $assistant->isConfigured(),
            'scope' => $this->resolveScope($user),
            'usage' => $usage,
        ]);
    }

    public function chat(
        Request $request,
        InAppAssistantService $assistant,
        InAppAssistantUsageLimiter $limiter,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($this->canUseAssistant($user), 403);
        abort_unless((bool) config('in-app-assistant.enabled', true), 503);
        abort_unless($assistant->isConfigured(), 503, 'Asistente no configurado (falta OPENAI_API_KEY).');

        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:4000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
            'context' => ['nullable', 'array'],
            'context.url' => ['nullable', 'string', 'max:500'],
            'context.component' => ['nullable', 'string', 'max:200'],
            'context.paciente_id' => ['nullable', 'string', 'max:64'],
        ]);

        if (! $limiter->reserve($user)) {
            $usage = $limiter->snapshot($user);

            return response()->json([
                'message' => 'Alcanzaste el límite diario del asistente ('.$usage['limit'].' mensajes). Vuelve mañana.',
                'usage' => $usage,
            ], 429);
        }

        $scope = $this->resolveScope($user);

        $pageContext = [
            'scope' => $scope,
        ];
        if (is_array($data['context'] ?? null)) {
            foreach (['url', 'component', 'paciente_id'] as $key) {
                if (isset($data['context'][$key]) && is_string($data['context'][$key]) && $data['context'][$key] !== '') {
                    $pageContext[$key] = (string) $data['context'][$key];
                }
            }
        }

        try {
            $result = $assistant->chat(
                (string) $data['message'],
                $user,
                is_array($data['history'] ?? null) ? $data['history'] : [],
                $pageContext,
            );

            $usage = $limiter->snapshot($user);

            return response()->json([
                'reply' => $result['reply'],
                'used_tools' => $result['used_tools'],
                'actions' => $result['actions'] ?? [],
                'scope' => $scope,
                'usage' => $usage,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'No pude responder ahora. Inténtalo de nuevo en unos segundos.',
            ], 422);
        }
    }

    private function canUseAssistant(User $user): bool
    {
        if (tenant_id() !== null) {
            return $user->can('in-app-assistant.use');
        }

        return $user->isPlatformSuperadmin();
    }

    /**
     * @return 'platform'|'clinic'
     */
    private function resolveScope(User $user): string
    {
        if (tenant_id() === null && $user->isPlatformSuperadmin()) {
            return 'platform';
        }

        return 'clinic';
    }
}
