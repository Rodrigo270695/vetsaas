<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Heartbeat de presencia: marca last_seen_at mientras el navegador está abierto.
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

        $user->forceFill(['last_seen_at' => now()])->save();

        return response()->json(['ok' => true]);
    }
}
