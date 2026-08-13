<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Reportes\ReporteVentasService;
use App\Support\Tenancy\TenantModuleAccess;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ReporteVentasController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ReporteVentasService $reportes,
    ) {}

    public function productos(Request $request): Response
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();

        $capabilities = TenantModuleAccess::filterCapabilities(
            $this->tenantManager->current()?->tenant,
            [
                'ventas' => $this->userCan($user, 'ventas.view'),
                'productos' => $this->userCan($user, 'productos.view'),
            ],
        );

        abort_unless(
            ($capabilities['ventas'] ?? false) && ($capabilities['productos'] ?? false),
            403,
        );

        $payload = $this->reportes->ventasPorProducto(
            $this->stringOrNull($request->query('fecha_desde')),
            $this->stringOrNull($request->query('fecha_hasta')),
            $this->stringOrNull($request->query('periodo')),
        );

        return Inertia::render('reportes/ventas-productos/index', [
            'moneda' => $payload['moneda'],
            'filtros' => $payload['filtros'],
            'totales' => $payload['totales'],
            'items' => $payload['items'],
        ]);
    }

    public function servicios(Request $request): Response
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();

        $capabilities = TenantModuleAccess::filterCapabilities(
            $this->tenantManager->current()?->tenant,
            [
                'ventas' => $this->userCan($user, 'ventas.view'),
                'grooming' => $this->userCan($user, 'grooming.view'),
            ],
        );

        abort_unless($capabilities['ventas'] ?? false, 403);

        $tipo = (string) $request->query('tipo', 'todos');
        if (! in_array($tipo, ['todos', 'tratamiento', 'vacuna', 'grooming'], true)) {
            $tipo = 'todos';
        }

        $includeGrooming = (bool) ($capabilities['grooming'] ?? false);
        if ($tipo === 'grooming' && ! $includeGrooming) {
            $tipo = 'todos';
        }

        $payload = $this->reportes->ventasPorServicio(
            $tipo,
            $this->stringOrNull($request->query('fecha_desde')),
            $this->stringOrNull($request->query('fecha_hasta')),
            $this->stringOrNull($request->query('periodo')),
            $includeGrooming,
        );

        return Inertia::render('reportes/ventas-servicios/index', [
            'moneda' => $payload['moneda'],
            'filtros' => $payload['filtros'],
            'totales' => $payload['totales'],
            'resumen' => $payload['resumen'],
            'items' => $payload['items'],
            'capabilities' => [
                'ventas' => (bool) ($capabilities['ventas'] ?? false),
                'grooming' => (bool) ($capabilities['grooming'] ?? false),
            ],
        ]);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function userCan(User $user, string $ability): bool
    {
        try {
            return $user->can($ability);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
