<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalPropietario;
use App\Models\Tenant;
use App\Services\Portal\PortalLandingAccessService;
use App\Services\Portal\PortalPropietarioAccessService;
use App\Support\Portal\PortalHomePayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
final class PortalLandingController extends Controller
{
    public const SESSION_PORTAL_ID = 'portal_landing_portal_id';

    public function __construct(
        private readonly PortalLandingAccessService $landing,
        private readonly PortalPropietarioAccessService $access,
    ) {}

    public function show(Request $request): InertiaResponse
    {
        return Inertia::render('portal/acceso', [
            'clinic' => PortalHomePayload::clinic(),
            'step' => $request->session()->has(self::SESSION_PORTAL_ID) ? 'otp' : 'document',
            'telefono_mascara' => $this->maskedPhoneForSession($request),
            'urls' => [
                'document' => route('tenant.portal.acceso.document'),
                'otp' => route('tenant.portal.acceso.otp'),
                'widget' => route('tenant.portal.acceso.widget'),
            ],
        ]);
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'numero_documento' => ['required', 'string', 'max:20'],
        ]);

        $propietario = $this->landing->findPropietarioByDocument($data['numero_documento']);
        if ($propietario === null) {
            return back()->withErrors([
                'numero_documento' => __('portal_propietario.landing.document_not_found'),
            ]);
        }

        try {
            $portal = $this->landing->portalForPropietario($propietario);
            $tenant = $this->currentTenant();
            if ($tenant === null) {
                abort(404);
            }

            $mask = $this->landing->sendLandingOtp($portal, $tenant);
        } catch (RuntimeException $e) {
            return back()->withErrors(['numero_documento' => $e->getMessage()]);
        }

        $request->session()->put(self::SESSION_PORTAL_ID, $portal->id);

        return redirect()
            ->route('tenant.portal.acceso')
            ->with('info', __('portal_propietario.landing.otp_sent', ['phone' => $mask]));
    }

    public function storeOtp(Request $request): RedirectResponse
    {
        $portal = $this->portalFromSession($request);
        if ($portal === null) {
            return redirect()
                ->route('tenant.portal.acceso')
                ->withErrors(['code' => __('portal_propietario.landing.session_expired')]);
        }

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        try {
            $this->landing->verifyLandingOtp($portal, $data['code']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->forget(self::SESSION_PORTAL_ID);

        $redirect = redirect()->route('tenant.portal.pin');
        $redirect->withCookie($this->access->identityCookie($portal));

        return $redirect;
    }

    public function widget(Request $request): Response
    {
        $label = 'Mis mascotas';
        $url = route('tenant.portal.acceso', [], absolute: true);
        $escapedUrl = json_encode($url, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $escapedLabel = json_encode($label, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $js = <<<JS
(function () {
  var u = {$escapedUrl};
  var label = {$escapedLabel};
  var nodes = document.querySelectorAll('[data-vetsaas-portal-acceso]');
  if (!nodes.length) {
    var b = document.createElement('a');
    b.href = u;
    b.textContent = label;
    b.style.cssText = 'display:inline-block;padding:12px 20px;border-radius:999px;font-weight:600;text-decoration:none;background:#0f766e;color:#fff;font-family:system-ui,sans-serif;font-size:15px';
    (document.currentScript && document.currentScript.parentNode
      ? document.currentScript.parentNode
      : document.body).appendChild(b);
    return;
  }
  nodes.forEach(function (el) {
    var a = document.createElement('a');
    a.href = u;
    a.textContent = el.getAttribute('data-vetsaas-portal-label') || label;
    a.style.cssText = 'display:inline-block;padding:12px 20px;border-radius:999px;font-weight:600;text-decoration:none;background:#0f766e;color:#fff;font-family:system-ui,sans-serif;font-size:15px';
  el.appendChild(a);
  });
})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function portalFromSession(Request $request): ?PortalPropietario
    {
        $id = $request->session()->get(self::SESSION_PORTAL_ID);
        if (! is_string($id) || $id === '') {
            return null;
        }

        return PortalPropietario::query()->where('id', $id)->with('propietario')->first();
    }

    private function maskedPhoneForSession(Request $request): ?string
    {
        $portal = $this->portalFromSession($request);
        if ($portal === null) {
            return null;
        }

        $phone = $portal->propietario?->telefono ?: $portal->telefono_snapshot;

        return \App\Support\Portal\PortalPhoneMask::mask($phone);
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