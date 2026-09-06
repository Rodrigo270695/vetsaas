<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Portal\PortalPropietarioAccessService;
use App\Support\Portal\PortalHomePayload;
use App\Support\Portal\PortalPhoneMask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class PortalAuthController extends Controller
{
    public function __construct(
        private readonly PortalPropietarioAccessService $access,
    ) {}

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $cookieName = (string) config('portal.cookie', 'vetsaas_portal');
        $portal = $this->access->findValidInvite($token);
        if ($portal === null) {
            abort(404);
        }

        $portal->propietario?->load([
            'pacientes' => fn ($q) => $q->where('activo', true)->orderBy('nombre'),
        ]);

        $sesion = $this->access->resolveSession($request->cookie($cookieName));
        if ($sesion !== null && $sesion->portal_propietario_id === $portal->id) {
            return redirect()->route('tenant.portal.home');
        }

        $propietario = $portal->propietario;
        $pet = $propietario?->pacientes()->where('activo', true)->orderBy('nombre')->first();

        return Inertia::render('portal/entrar', [
            'token' => $token,
            'step' => $portal->hasPin() ? 'pin' : 'setup',
            'clinic' => PortalHomePayload::clinic(),
            'saludo' => $this->firstName((string) ($propietario?->nombres ?? $propietario?->displayName())),
            'mascota' => $pet === null ? null : [
                'nombre' => $pet->nombre,
                'foto_url' => $pet->foto_url,
            ],
            'telefono_mascara' => PortalPhoneMask::mask($propietario?->telefono ?: $portal->telefono_snapshot),
            'urls' => [
                'setup' => route('tenant.portal.pin.store', ['token' => $token]),
                'unlock' => route('tenant.portal.unlock', ['token' => $token]),
                'reset_send' => route('tenant.portal.reset.send', ['token' => $token]),
                'reset_confirm' => route('tenant.portal.reset.confirm', ['token' => $token]),
            ],
        ]);
    }

    public function storePin(Request $request, string $token): RedirectResponse
    {
        $portal = $this->access->findValidInvite($token);
        if ($portal === null) {
            abort(404);
        }

        $data = $request->validate([
            'pin' => ['required', 'digits:4'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        try {
            $cookie = $this->access->setPin($portal, $data['pin'], $request);
        } catch (RuntimeException $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }

        return redirect()
            ->route('tenant.portal.home')
            ->withCookie($cookie);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $portal = $this->access->findValidInvite($token);
        if ($portal === null) {
            abort(404);
        }

        $data = $request->validate([
            'pin' => ['required', 'digits:4'],
        ]);

        try {
            $cookie = $this->access->unlock($portal, $data['pin'], $request);
        } catch (RuntimeException $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }

        return redirect()
            ->route('tenant.portal.home')
            ->withCookie($cookie);
    }

    public function sendReset(Request $request, string $token): RedirectResponse
    {
        $portal = $this->access->findValidInvite($token);
        if ($portal === null) {
            abort(404);
        }

        $tenant = $this->currentTenant();
        if ($tenant === null) {
            return back()->withErrors(['reset' => 'No se pudo identificar la clínica.']);
        }

        try {
            $mask = $this->access->sendResetCode($portal->loadMissing('propietario'), $tenant);
        } catch (RuntimeException $e) {
            return back()->withErrors(['reset' => $e->getMessage()]);
        }

        return back()->with('success', "Enviamos un código por WhatsApp al {$mask}.");
    }

    public function confirmReset(Request $request, string $token): RedirectResponse
    {
        $portal = $this->access->findValidInvite($token);
        if ($portal === null) {
            abort(404);
        }

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'pin' => ['required', 'digits:4'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        try {
            $cookie = $this->access->confirmReset($portal, $data['code'], $data['pin'], $request);
        } catch (RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()
            ->route('tenant.portal.home')
            ->withCookie($cookie);
    }

    public function logout(Request $request): RedirectResponse
    {
        $cookieName = (string) config('portal.cookie', 'vetsaas_portal');
        $sesion = $this->access->resolveSession($request->cookie($cookieName));
        $this->access->revokeSession($sesion);

        return redirect()
            ->route('tenant.portal.sin-acceso')
            ->withCookie($this->access->forgetCookie());
    }

    public function sinAcceso(): Response
    {
        return Inertia::render('portal/sin-acceso', [
            'clinic' => PortalHomePayload::clinic(),
        ]);
    }

    private function firstName(string $nombres): string
    {
        $nombres = trim($nombres);
        if ($nombres === '') {
            return '';
        }

        return explode(' ', $nombres)[0];
    }

    private function currentTenant(): ?Tenant
    {
        $tenantId = tenant_id();
        if ($tenantId === null) {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }
}
