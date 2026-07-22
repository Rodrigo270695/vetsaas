<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPageViewLog;
use App\Support\Platform\PresencePathResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Heartbeat de presencia: last_seen_at + vista/módulo actual.
 */
class PresenceHeartbeatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return response()->json(['ok' => false], 401);
        }

        $validated = $request->validate([
            'path' => ['nullable', 'string', 'max:512'],
            'component' => ['nullable', 'string', 'max:255'],
        ]);

        $resolved = PresencePathResolver::resolve($validated['path'] ?? null);
        $path = $resolved['path'];
        $module = $resolved['module'];
        $component = isset($validated['component']) && is_string($validated['component'])
            ? mb_substr($validated['component'], 0, 255)
            : null;

        $previousPath = $user->last_path;
        $pathChanged = $previousPath !== $path;

        $user->forceFill([
            'last_seen_at' => now(),
            'last_path' => $path,
            'last_module' => $module,
            'last_path_at' => $pathChanged || $user->last_path_at === null ? now() : $user->last_path_at,
        ])->save();

        if ($pathChanged) {
            UserPageViewLog::query()->create([
                'user_id' => $user->getKey(),
                'tenant_id' => $user->tenant_id,
                'path' => $path,
                'module' => $module,
                'inertia_component' => $component,
                'seen_at' => now(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'module' => $module,
            'path' => $path,
        ]);
    }
}
