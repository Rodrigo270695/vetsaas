<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalPropietario;
use App\Models\Propietario;
use App\Services\Portal\PortalPropietarioAccessService;
use App\Support\Portal\PortalHomePayload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PortalHomeController extends Controller
{
    public function __construct(
        private readonly PortalPropietarioAccessService $access,
    ) {}

    public function index(Request $request): Response
    {
        $titular = $request->attributes->get('portal_titular');
        abort_unless($titular instanceof Propietario, 403);

        $portal = $request->attributes->get('portal_propietario');
        if ($portal instanceof PortalPropietario) {
            cookie()->queue($this->access->identityCookie($portal));
        }

        $mascotaId = trim((string) $request->query('mascota', ''));

        return Inertia::render('portal/home', [
            'clinic' => PortalHomePayload::clinic(),
            'home' => PortalHomePayload::make($titular, $mascotaId !== '' ? $mascotaId : null),
            'push' => [
                'enabled' => filled(config('webpush.vapid.public_key')),
                'vapid' => (string) config('webpush.vapid.public_key'),
                'subscribe_url' => route('tenant.portal.push.store'),
                'unsubscribe_url' => route('tenant.portal.push.destroy'),
            ],
            'urls' => [
                'home' => route('tenant.portal.home'),
                'logout' => route('tenant.portal.logout'),
            ],
        ]);
    }
}
