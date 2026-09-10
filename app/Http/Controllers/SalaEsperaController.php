<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Clinica\SalaEsperaHoyService;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaEsperaController extends Controller
{
    public function __invoke(
        Request $request,
        TenantManager $tenants,
        SalaEsperaHoyService $salaEspera,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $canCitas = $user->can('citas.view');
        $canGrooming = $user->can('grooming.view');
        abort_unless($canCitas || $canGrooming, 403);

        $tenant = $tenants->current()?->tenant;

        return response()->json($salaEspera->forUser($user, $tenant));
    }
}
