<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalPropietario;
use App\Models\Tenant;
use App\Services\Portal\PortalPropietarioAccessService;
use App\Support\Portal\PortalHomePayload;
use App\Support\Portal\PortalPhoneMask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;

final class PortalAuthController extends Controller
{
    public function __construct(
        private readonly PortalPropietarioAccessService $access,
    ) {}

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $portal = $this->access->findValidInvite($token);
        if ($portal === null) {
            abort(404);
        }

        cookie()->queue($this->access->identityCookie($portal));

        return $this->entrarResponse($request, $portal, $token);
    }

    public function showPin(Request $request): Response|RedirectResponse
    {
        $portal = $this->portalFromIdentity($request);
        if ($portal === null) {
            return redirect()->route('tenant.portal.sin-acceso');
        }

        return $this->entrarResponse($request, $portal, null);
    }

    public function storePin(Request $request, string $token): RedirectResponse
    {
        $portal = $this->access->findValidInvite($token);
        if ($portal === null) {
            abort(404);
        }

        return $this->completeSetPin($request, $portal);
    }

    public function storePinIdentity(Request $request): RedirectResponse
    {
        $portal = $this->portalFromIdentity($request);
        if ($portal === null) {
            abort(404);
        }

        return $this->completeSetPin($request, $portal);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $portal = $this->access->findValidInvite($token);
        if ($portal === null) {
            abort(404);
        }

        return $this->completeUnlock($request, $portal);
    }

    public function unlockIdentity(Request $request): RedirectResponse
    {
        $portal = $this->portalFromIdentity($request);
        if ($portal === null) {
            abort(404);
        }

        return $this->completeUnlock($request, $portal);
    }

    public function sendReset(Request $request, string $token): RedirectResponse
    {
        $portal = $this->access->findValidInvite($token);
        if ($portal === null) {
            abort(404);
        }

        return $this->completeSendReset($request, $portal);
    }

    public function sendResetIdentity(Request $request): RedirectResponse
    {
        $portal = $this->portalFromIdentity($request);
        if ($portal === null) {
            abort(404);
        }

        return $this->completeSendReset($request, $portal);
    }

    public function confirmReset(Request $request, string $token): RedirectResponse
    {
        $portal = $this->access->findValidInvite($token);
        if ($portal === null) {
            abort(404);
        }

        return $this->completeConfirmReset($request, $portal);
    }

    public function confirmResetIdentity(Request $request): RedirectResponse
    {
        $portal = $this->portalFromIdentity($request);
        if ($portal === null) {
            abort(404);
        }

        return $this->completeConfirmReset($request, $portal);
    }

    public function logout(Request $request): RedirectResponse
    {
        $cookieName = (string) config('portal.cookie', 'vetsaas_portal');
        $sesion = $this->access->resolveSession($request->cookie($cookieName));
        $this->access->revokeSession($sesion);

        $portal = $this->portalFromIdentity($request);
        $redirect = $portal !== null
            ? redirect()->route('tenant.portal.pin')
            : redirect()->route('tenant.portal.sin-acceso');

        return $redirect->withCookie($this->access->forgetCookie());
    }

    public function sinAcceso(): Response
    {
        return Inertia::render('portal/sin-acceso', [
            'clinic' => PortalHomePayload::clinic(),
        ]);
    }

    private function completeSetPin(Request $request, PortalPropietario $portal): RedirectResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'digits:4'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        try {
            $this->access->setPin($portal, $data['pin']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }

        return $this->withAuthCookies(
            redirect()->route('tenant.portal.home'),
            $portal,
            $request,
        );
    }

    private function completeUnlock(Request $request, PortalPropietario $portal): RedirectResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'digits:4'],
        ]);

        try {
            $this->access->unlock($portal, $data['pin']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }

        return $this->withAuthCookies(
            redirect()->route('tenant.portal.home'),
            $portal,
            $request,
        );
    }

    private function completeSendReset(Request $request, PortalPropietario $portal): RedirectResponse
    {
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

    private function completeConfirmReset(Request $request, PortalPropietario $portal): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'pin' => ['required', 'digits:4'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        try {
            $this->access->confirmReset($portal, $data['code'], $data['pin']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return $this->withAuthCookies(
            redirect()->route('tenant.portal.home'),
            $portal,
            $request,
        );
    }

    private function entrarResponse(Request $request, PortalPropietario $portal, ?string $token): Response|RedirectResponse
    {
        $cookieName = (string) config('portal.cookie', 'vetsaas_portal');
        $sesion = $this->access->resolveSession($request->cookie($cookieName));
        if ($sesion !== null && $sesion->portal_propietario_id === $portal->id) {
            return redirect()->route('tenant.portal.home');
        }

        $portal->propietario?->load([
            'pacientes' => fn ($q) => $q->where('activo', true)->orderBy('nombre'),
        ]);
        $propietario = $portal->propietario;
        $pet = $propietario?->pacientes->first();

        $urls = $token === null
            ? [
                'setup' => route('tenant.portal.pin.store.identity'),
                'unlock' => route('tenant.portal.unlock.identity'),
                'reset_send' => route('tenant.portal.reset.send.identity'),
                'reset_confirm' => route('tenant.portal.reset.confirm.identity'),
            ]
            : [
                'setup' => route('tenant.portal.pin.store', ['token' => $token]),
                'unlock' => route('tenant.portal.unlock', ['token' => $token]),
                'reset_send' => route('tenant.portal.reset.send', ['token' => $token]),
                'reset_confirm' => route('tenant.portal.reset.confirm', ['token' => $token]),
            ];

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
            'urls' => $urls,
        ]);
    }

    private function withAuthCookies(RedirectResponse $redirect, PortalPropietario $portal, Request $request): RedirectResponse
    {
        foreach ($this->access->authCookies($portal, $request) as $cookie) {
            if ($cookie instanceof Cookie) {
                $redirect->withCookie($cookie);
            }
        }

        return $redirect;
    }

    private function portalFromIdentity(Request $request): ?PortalPropietario
    {
        $name = (string) config('portal.identity_cookie', 'vetsaas_portal_id');

        return $this->access->findByIdentity($request->cookie($name));
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
