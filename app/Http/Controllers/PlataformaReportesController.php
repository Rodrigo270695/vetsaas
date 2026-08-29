<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DemoAccessLog;
use App\Models\Tenant;
use App\Services\Demo\DemoLeadOutreachService;
use App\Services\OpenWa\OpenWaClient;
use App\Services\Platform\PlataformaReportesSnapshotService;
use App\Support\Database\PublicSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $pendingRefresh = 0;
        if (PublicSchema::hasColumn('tenants', 'geo_refresh_requested_at')) {
            $pendingRefresh = Tenant::query()
                ->whereNotNull('geo_consent_at')
                ->whereNull('geo_denied_at')
                ->whereNotNull('geo_refresh_requested_at')
                ->where(function ($q): void {
                    $q->whereNull('geo_captured_at')
                        ->orWhereColumn('geo_refresh_requested_at', '>', 'geo_captured_at');
                })
                ->count();
        }

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
                'gps_refresh_pending' => $pendingRefresh,
            ],
        ]);
    }

    /**
     * Marca a las clínicas con consentimiento para que, al navegar ellas,
     * su navegador capture de nuevo el GPS (sin cron).
     */
    public function solicitarGpsRefresh(): RedirectResponse
    {
        if (! PublicSchema::hasColumn('tenants', 'geo_consent_at')) {
            return back()->with('error', 'Faltan columnas GPS en tenants.');
        }
        if (! PublicSchema::hasColumn('tenants', 'geo_refresh_requested_at')) {
            return back()->with(
                'error',
                'Falta migrar geo_refresh_requested_at.',
            );
        }

        $now = Carbon::now();
        $updated = Tenant::query()
            ->whereNotNull('geo_consent_at')
            ->whereNull('geo_denied_at')
            ->update(['geo_refresh_requested_at' => $now]);

        return back()->with(
            'success',
            $updated === 0
                ? 'Ninguna clínica con consentimiento GPS aún.'
                : "Solicitud enviada a {$updated} clínica(s). El GPS se actualizará cuando entren a trabajar.",
        );
    }

    public function mapaDemos(): Response
    {
        $markers = [];
        $withGps = 0;
        $withLead = 0;
        $leads = [];

        if (PublicSchema::hasTable('demo_access_logs')) {
            $rows = DemoAccessLog::query()
                ->orderByDesc('created_at')
                ->limit(500)
                ->get([
                    'id',
                    'lat',
                    'lng',
                    'ip',
                    'clinic_name',
                    'phone',
                    'email',
                    'lead_captured_at',
                    'outreach_sent_at',
                    'outreach_channel',
                    'created_at',
                ]);

            foreach ($rows as $row) {
                $hasLead = $row->lead_captured_at !== null
                    && ($row->phone || $row->email);
                if ($hasLead) {
                    $withLead++;
                    $leads[] = [
                        'id' => (string) $row->id,
                        'clinic_name' => $row->clinic_name,
                        'phone' => $row->phone,
                        'email' => $row->email,
                        'has_gps' => $row->lat !== null && $row->lng !== null,
                        'ip' => $row->ip,
                        'captured_at' => $row->lead_captured_at
                            ?->timezone(config('app.timezone'))
                            ->format('d/m/Y H:i'),
                        'outreach_sent_at' => $row->outreach_sent_at
                            ?->timezone(config('app.timezone'))
                            ->format('d/m/Y H:i'),
                        'outreach_channel' => $row->outreach_channel,
                    ];
                }

                if ($row->lat === null || $row->lng === null) {
                    continue;
                }
                $withGps++;

                $contactBits = array_filter([
                    $row->clinic_name,
                    $row->phone,
                    $row->email,
                ]);
                $when = $row->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '';
                $label = $contactBits !== []
                    ? implode(' · ', $contactBits)
                    : 'Demo · '.$when;

                $markers[] = [
                    'tenant_id' => (string) $row->id,
                    'slug' => $row->ip ? 'ip:'.$row->ip : 'demo',
                    'label' => $label,
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
            'leads' => array_slice($leads, 0, 100),
            'summary' => [
                'total_logs' => PublicSchema::hasTable('demo_access_logs')
                    ? DemoAccessLog::query()->count()
                    : 0,
                'with_gps' => $withGps,
                'without_gps' => PublicSchema::hasTable('demo_access_logs')
                    ? DemoAccessLog::query()->whereNull('lat')->count()
                    : 0,
                'with_lead' => $withLead,
            ],
            'openwa_configured' => app(OpenWaClient::class)->isConfigured(),
        ]);
    }

    public function sendDemoLeadOutreach(
        Request $request,
        string $id,
        DemoLeadOutreachService $outreach,
    ): RedirectResponse {
        abort_unless(PublicSchema::hasTable('demo_access_logs'), 404);

        $validated = $request->validate([
            'force' => ['sometimes', 'boolean'],
        ]);

        $log = DemoAccessLog::query()->findOrFail($id);
        $result = $outreach->send($log, (bool) ($validated['force'] ?? false));

        if (! $result['ok']) {
            return back()->with(
                $result['skipped'] ? 'info' : 'error',
                $result['message'],
            );
        }

        return back()->with('success', $result['message']);
    }
}
