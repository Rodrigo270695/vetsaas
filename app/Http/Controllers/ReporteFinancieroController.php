<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Dashboard\DashboardStatsService;
use App\Support\Tenancy\TenantModuleAccess;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ReporteFinancieroController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly DashboardStatsService $stats,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();

        $capabilities = [
            'ventas' => $this->userCan($user, 'ventas.view'),
            'productos' => $this->userCan($user, 'productos.view'),
            'grooming' => $this->userCan($user, 'grooming.view'),
        ];

        $tenantModel = $this->tenantManager->current()?->tenant;
        $capabilities = TenantModuleAccess::filterCapabilities($tenantModel, $capabilities);
        $analysis = $this->stats->buildFinancialAnalysis($capabilities);

        return Inertia::render('reportes/financiero/index', [
            'capabilities' => $capabilities,
            'moneda' => $analysis['moneda'],
            'ingresos_mensuales' => $analysis['ingresos_mensuales'],
            'comparacion_ingresos_mes' => $analysis['comparacion_ingresos_mes'],
            'top_productos_mes' => $analysis['top_productos_mes'],
            'rentabilidad' => $analysis['rentabilidad'],
            'rentabilidad_grooming' => $analysis['rentabilidad_grooming'],
            'rentabilidad_clinica' => $analysis['rentabilidad_clinica'],
            'fel_estado_mes' => $analysis['fel_estado_mes'],
        ]);
    }

    public function rentabilidad(Request $request): JsonResponse
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $this->userCan($user, 'ventas.view') && $this->userCan($user, 'productos.view'),
            403,
        );

        return response()->json($this->stats->rentabilidad(
            $this->resolvePeriodo($request),
            $this->resolveComprobantes($request),
        ));
    }

    public function rentabilidadGrooming(Request $request): JsonResponse
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();

        abort_unless($this->userCan($user, 'grooming.view'), 403);

        return response()->json($this->stats->rentabilidadGrooming(
            $this->resolvePeriodo($request),
            $this->resolveComprobantes($request),
        ));
    }

    public function rentabilidadClinica(Request $request): JsonResponse
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();

        abort_unless($this->userCan($user, 'ventas.view'), 403);

        return response()->json($this->stats->rentabilidadClinica(
            $this->resolvePeriodo($request),
            $this->resolveComprobantes($request),
        ));
    }

    private function resolvePeriodo(Request $request): string
    {
        $periodo = (string) $request->query('periodo', 'mes_actual');

        if (! in_array($periodo, ['semana', 'mes_actual', 'mes_pasado'], true)) {
            return 'mes_actual';
        }

        return $periodo;
    }

    /**
     * @return array{boleta: bool, factura: bool, ticket: bool}
     */
    private function resolveComprobantes(Request $request): array
    {
        return DashboardStatsService::resolveRentabilidadComprobantes([
            'boleta' => $request->has('boleta') ? $request->boolean('boleta') : true,
            'factura' => $request->has('factura') ? $request->boolean('factura') : true,
            'ticket' => $request->has('ticket') ? $request->boolean('ticket') : true,
        ]);
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
