<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TenantModulesRequest;
use App\Models\Tenant;
use App\Support\Tenancy\TenantModuleAccess;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TenantModuleController extends Controller
{
    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('plataforma/tenants/modulos', [
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'razon_social' => $tenant->razon_social,
                'nombre_comercial' => $tenant->nombre_comercial,
                'estado' => $tenant->estado,
            ],
            'module_groups' => TenantModuleAccess::catalogForTenant($tenant),
        ]);
    }

    public function update(
        TenantModulesRequest $request,
        Tenant $tenant,
        TenantManager $manager,
    ): RedirectResponse {
        $tenant->update([
            'modulos_deshabilitados' => $request->validatedDisabledModules(),
        ]);

        $manager->flushCacheFor($tenant);

        return redirect()
            ->route('plataforma.tenants.modules.edit', $tenant)
            ->with('success', __('tenants.modules.saved'));
    }
}
