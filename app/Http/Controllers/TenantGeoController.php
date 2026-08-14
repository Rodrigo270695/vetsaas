<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\Database\PublicSchema;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Guarda consentimiento / denegación de geolocalización del navegador
 * para el mapa de calor de Reportes de plataforma.
 */
class TenantGeoController extends Controller
{
    public function store(Request $request, TenantManager $tenants): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $validated = $request->validate([
            'action' => ['required', 'in:accept,deny'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if (! PublicSchema::hasColumn('tenants', 'geo_consent_at')) {
            return back()->with(
                'error',
                'Falta migrar columnas GPS. Ejecuta: php artisan migrate --path=database/migrations/2026_08_13_200000_add_geo_coords_to_tenants_table.php --force',
            );
        }

        /** @var Tenant $tenant */
        if ($validated['action'] === 'deny') {
            $tenant->forceFill([
                'geo_denied_at' => Carbon::now(),
            ])->save();

            return back();
        }

        $lat = $validated['lat'] ?? null;
        $lng = $validated['lng'] ?? null;
        if ($lat === null || $lng === null) {
            return back()->with('error', 'No se recibieron coordenadas.');
        }

        $tenant->forceFill([
            'geo_lat' => round((float) $lat, 7),
            'geo_lng' => round((float) $lng, 7),
            'geo_consent_at' => Carbon::now(),
            'geo_denied_at' => null,
            'geo_captured_at' => Carbon::now(),
        ])->save();

        return back();
    }
}
