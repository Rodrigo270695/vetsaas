<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Platform\PlataformaReportesSnapshotService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard de reportes de marketing / geo para el superadmin (host central).
 */
class PlataformaReportesController extends Controller
{
    public function index(PlataformaReportesSnapshotService $snapshot): Response
    {
        $data = $snapshot->build();
        unset($data['map_markers']);

        return Inertia::render('plataforma/reportes/index', [
            'snapshot' => $data,
        ]);
    }

    public function mapa(PlataformaReportesSnapshotService $snapshot): Response
    {
        $data = $snapshot->build();
        $markers = $data['map_markers'] ?? [];
        $gpsCount = collect($markers)->where('source', 'gps')->count();

        return Inertia::render('plataforma/reportes/mapa', [
            'markers' => $markers,
            'summary' => [
                'total_vivos' => (int) ($data['kpis']['total_vivos'] ?? 0),
                'paid' => (int) ($data['kpis']['paid'] ?? 0),
                'free' => (int) ($data['kpis']['free'] ?? 0),
                'markers' => count($markers),
                'gps' => $gpsCount,
                'departamento' => max(0, count($markers) - $gpsCount),
                'cobertura_geo_pct' => (float) ($data['insights']['cobertura_geo_pct'] ?? 0),
                'gps_consents' => (int) ($data['kpis']['gps_consents'] ?? 0),
            ],
        ]);
    }
}
