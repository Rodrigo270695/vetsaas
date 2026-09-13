<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Entrada del subdominio del tenant.
 *
 * Arquitectura "single-login + datos aislados":
 *   - El login y el dashboard autenticado son COMPARTIDOS con el panel
 *     central (rutas `login`, `dashboard` definidas por Fortify y
 *     `routes/web.php`). El sidebar y la UI se filtran por permisos.
 *   - `/` no es una landing “en construcción”: invitados van a login
 *     y el personal de esta clínica va al dashboard.
 */
class TenantDashboardController extends Controller
{
    /**
     * Entrada del subdominio (`/`).
     */
    public function welcome(TenantManager $manager): RedirectResponse
    {
        $context = $manager->current();
        abort_if($context === null, 500, 'Tenant context missing.');

        /** @var User|null $user */
        $user = Auth::guard('web')->user();
        if ($user !== null && $user->belongsToTenant($context->id())) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('login');
    }
}
