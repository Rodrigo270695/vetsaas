<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DemoAccessLog;
use App\Support\Database\PublicSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Registra de dónde entran a la demo (GPS del browser + IP).
 */
class DemoAccessGeoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(is_public_demo_tenant(), 404);

        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if (! PublicSchema::hasTable('demo_access_logs')) {
            return response()->json([
                'ok' => false,
                'message' => 'Falta migrar demo_access_logs.',
            ], 503);
        }

        $lat = $validated['lat'] ?? null;
        $lng = $validated['lng'] ?? null;

        DemoAccessLog::query()->create([
            'lat' => $lat !== null ? round((float) $lat, 7) : null,
            'lng' => $lng !== null ? round((float) $lng, 7) : null,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'user_id' => $request->user()?->id,
            'created_at' => Carbon::now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
