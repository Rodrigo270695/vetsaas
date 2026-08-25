<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Referrals\ReferralService;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClinicReferralController extends Controller
{
    public function show(Request $request, ReferralService $referrals): Response
    {
        abort_unless($request->user()?->can('config-general.view') ?? false, 403);

        $tenant = app(TenantManager::class)->current()?->tenant;
        abort_if($tenant === null, 404);

        return Inertia::render('configuracion/referidos/index', [
            'referral' => $referrals->summaryForTenant($tenant),
        ]);
    }
}
