<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DemoAccessLog;
use App\Services\Platform\PlataformaReportesSnapshotService;
use App\Support\Database\PublicSchema;
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

    public function mapaDemos(): Response
    {
        $markers = [];
        $withGps = 0;

        if (PublicSchema::hasTable('demo_access_logs')) {
            $rows = DemoAccessLog::query()
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(['id', 'lat', 'lng', 'ip', 'created_at']);

            foreach ($rows as $row) {
                if ($row->lat === null || $row->lng === null) {
                    continue;
                }
                $withGps++;
                $markers[] = [
                    'tenant_id' => (string) $row->id,
                    'slug' => $row->ip ? 'ip:'.$row->ip : 'demo',
                    'label' => 'Demo · '.($row->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? ''),
                    'segment' => 'free',
                    'lat' => (float) (string) $row->lat,
                    'lng' => (float) (string) $row->lng,
                    'source' => 'gps',
                    'departamento' => null,
                    'logo_url' => '',
                    'has_custom_logo' => false,
                ];
            }
        }

        return Inertia::render('plataforma/reportes/mapa-demos', [
            'markers' => $markers,
            'summary' => [
                'total_logs' => PublicSchema::hasTable('demo_access_logs')
                    ? DemoAccessLog::query()->count()
                    : 0,
                'with_gps' => $withGps,
                'without_gps' => PublicSchema::hasTable('demo_access_logs')
                    ? DemoAccessLog::query()->whereNull('lat')->count()
                    : 0,
            ],
        ]);
    }
}
