<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Propietario;
use App\Models\Tenant;
use App\Services\Portal\PortalPropietarioAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class PortalInviteController extends Controller
{
    public function store(
        Request $request,
        Propietario $propietario,
        PortalPropietarioAccessService $access,
    ): RedirectResponse {
        abort_unless($request->user()?->can('propietarios.view') ?? false, 403);

        $tenantId = tenant_id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        if ($tenant === null) {
            return back()->with('warning', 'No se pudo identificar la clínica.');
        }

        try {
            $access->invite(
                $propietario,
                $tenant,
                $request->user()?->getAuthIdentifier(),
            );
        } catch (RuntimeException $e) {
            return back()->with('warning', $e->getMessage());
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el portal del propietario por WhatsApp', [
                'propietario_id' => $propietario->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('warning', 'No se pudo enviar por WhatsApp. Verifica la conexión e inténtalo de nuevo.');
        }

        return back()->with('success', 'Enviamos el acceso del portal por WhatsApp al titular.');
    }
}
