<?php

namespace App\Http\Controllers;

use App\Support\Subscriptions\TenantSubscriptionSummary;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vista de solo lectura para que la clínica vea su plan y fechas de renovación.
 */
class ClinicSubscriptionController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless($request->user()?->can('config-general.view') ?? false, 403);

        $tenant = app(TenantManager::class)->current()?->tenant;
        abort_if($tenant === null, 404);

        return Inertia::render('configuracion/suscripcion/index', [
            'subscription' => TenantSubscriptionSummary::forTenant($tenant),
        ]);
    }
}
