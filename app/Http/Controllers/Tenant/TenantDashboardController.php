<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing pública del subdominio del tenant.
 *
 * Arquitectura "single-login + datos aislados":
 *   - El login y el dashboard autenticado son COMPARTIDOS con el panel
 *     central (rutas `login`, `dashboard` definidas por Fortify y
 *     `routes/web.php`). El sidebar y la UI se filtran por permisos.
 *   - Esta clase solo se encarga de la página de bienvenida pública
 *     (lo primero que ve quien entra a `<clinica>.localhost` sin sesión).
 */
class TenantDashboardController extends Controller
{
    /**
     * Landing pública del subdominio.
     *
     * Si el usuario ya está autenticado Y pertenece a esta clínica,
     * lo enviamos directo al dashboard.
     */
    public function welcome(TenantManager $manager): Response|RedirectResponse
    {
        $context = $manager->current();
        abort_if($context === null, 500, 'Tenant context missing.');

        /** @var User|null $user */
        $user = Auth::guard('web')->user();
        if ($user !== null && $user->belongsToTenant($context->id())) {
            return redirect()->route('dashboard');
        }

        $tenant = $context->tenant;

        return Inertia::render('tenant/welcome', [
            'tenant' => [
                'id' => $context->id(),
                'slug' => $context->slug,
                'schema' => $context->schema,
                'razon_social' => $tenant->razon_social,
                'nombre_comercial' => $tenant->nombre_comercial,
                'estado' => $tenant->estado,
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'logo_url' => $tenant->logo_url,
            ],
        ]);
    }
}
