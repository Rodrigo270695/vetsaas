<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Propietario;
use App\Support\Portal\PortalHomePayload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PortalHomeController extends Controller
{
    public function index(Request $request): Response
    {
        $titular = $request->attributes->get('portal_titular');
        abort_unless($titular instanceof Propietario, 403);

        $mascotaId = trim((string) $request->query('mascota', ''));

        return Inertia::render('portal/home', [
            'clinic' => PortalHomePayload::clinic(),
            'home' => PortalHomePayload::make($titular, $mascotaId !== '' ? $mascotaId : null),
            'urls' => [
                'home' => route('tenant.portal.home'),
                'logout' => route('tenant.portal.logout'),
            ],
        ]);
    }
}
