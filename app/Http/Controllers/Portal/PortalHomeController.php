<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Paciente;
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
        $tab = (string) $request->query('tab', 'citas');
        if (! in_array($tab, ['citas', 'hc', 'banos', 'vacunas'], true)) {
            $tab = 'citas';
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $desde = self::optionalDate($request->query('desde'));
        $hasta = self::optionalDate($request->query('hasta'));
        $todo = $request->boolean('todo');

        $legacyYearToToday = $desde === now()->startOfYear()->toDateString()
            && $hasta === now()->toDateString();

        if ($mascotaId !== '' && ! $todo && ($legacyYearToToday || ($desde === null && $hasta === null))) {
            $desde = $monthStart;
            $hasta = $monthEnd;
        }

        $pet = null;
        if ($mascotaId !== '') {
            $paciente = Paciente::query()
                ->where('propietario_id', $titular->id)
                ->whereKey($mascotaId)
                ->firstOrFail();
            $pet = PortalHomePayload::pet($titular, $paciente, $desde, $hasta);
        }

        return Inertia::render('portal/home', [
            'clinic' => PortalHomePayload::clinic(),
            'overview' => PortalHomePayload::overview($titular),
            'pet' => $pet,
            'filters' => [
                'tab' => $tab,
                'desde' => $desde,
                'hasta' => $hasta,
                'default_desde' => $monthStart,
                'default_hasta' => $monthEnd,
            ],
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

    private static function optionalDate(mixed $value): ?string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            return null;
        }

        return $raw;
    }
}
