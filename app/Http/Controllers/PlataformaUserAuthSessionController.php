<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Platform\LivePresenceDetailService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Presencia en vivo y flujo de navegación (sin historial de login/logout).
 */
class PlataformaUserAuthSessionController extends Controller
{
    public function index(
        Request $request,
        LivePresenceDetailService $livePresence,
    ): Response {
        $defaultDesde = Carbon::now()->startOfMonth()->toDateString();
        $defaultHasta = Carbon::now()->toDateString();
        $fechaDesde = $this->parseDateParam($request->query('fecha_desde')) ?? $defaultDesde;
        $fechaHasta = $this->parseDateParam($request->query('fecha_hasta')) ?? $defaultHasta;

        if ($fechaDesde > $fechaHasta) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        $presence = $livePresence->build($fechaDesde, $fechaHasta, null);

        return Inertia::render('plataforma/sesiones-login/index', [
            'filters' => [
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ],
            'fecha_filtro_ui' => [
                'default_desde' => $defaultDesde,
                'default_hasta' => $defaultHasta,
            ],
            'presence' => $presence,
            'stats' => [
                'online' => count($presence['online'] ?? []),
            ],
        ]);
    }

    private function parseDateParam(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
