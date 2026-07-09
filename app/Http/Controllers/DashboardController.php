<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Dashboard\DashboardStatsService;
use App\Models\Tenant;
use App\Support\Tenancy\TenantModuleAccess;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly DashboardStatsService $stats,
    ) {}

    public function index(Request $request): Response
    {
        if (! $this->tenantManager->check()) {
            return Inertia::render('dashboard/central');
        }

        /** @var User $user */
        $user = $request->user();

        $capabilities = [
            'citas' => $this->userCan($user, 'citas.view'),
            'consultas' => $this->userCan($user, 'historias-clinicas.view'),
            'ventas' => $this->userCan($user, 'ventas.view'),
            'pacientes' => $this->userCan($user, 'pacientes.view'),
            'propietarios' => $this->userCan($user, 'propietarios.view'),
            'vacunaciones' => $this->userCan($user, 'vacunaciones.view'),
            'grooming' => $this->userCan($user, 'grooming.view'),
            'hotel' => $this->userCan($user, 'hotel.view'),
            'hospitalizacion' => $this->userCan($user, 'hospitalizacion.view'),
            'productos' => $this->userCan($user, 'productos.view'),
            'alertas_stock' => $this->userCan($user, 'alertas-stock.view'),
            'caja_sesiones' => $this->userCan($user, 'caja-sesiones.view'),
        ];

        $tenantModel = $this->tenantManager->current()?->tenant;
        $capabilities = TenantModuleAccess::filterCapabilities($tenantModel, $capabilities);

        $context = $this->tenantManager->current();
        $clinicLabel = $context !== null
            ? (trim((string) ($context->nombreComercial() ?: '')) ?: $context->razonSocial())
            : '';

        return Inertia::render('dashboard/index', [
            'clinic_label' => $clinicLabel,
            'capabilities' => $capabilities,
            ...$this->stats->build($user, $capabilities),
        ]);
    }

    /**
     * Devuelve el resumen de rentabilidad para el periodo solicitado.
     * Se consume vía fetch desde el widget del dashboard (sin recargar la página).
     */
    public function rentabilidad(Request $request): JsonResponse
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $this->userCan($user, 'ventas.view') && $this->userCan($user, 'productos.view'),
            403,
        );

        $periodo = (string) $request->query('periodo', 'mes_actual');

        if (! in_array($periodo, ['semana', 'mes_actual', 'mes_pasado'], true)) {
            $periodo = 'mes_actual';
        }

        $comprobantes = DashboardStatsService::resolveRentabilidadComprobantes([
            'boleta' => $request->has('boleta') ? $request->boolean('boleta') : true,
            'factura' => $request->has('factura') ? $request->boolean('factura') : true,
            'ticket' => $request->has('ticket') ? $request->boolean('ticket') : true,
        ]);

        return response()->json($this->stats->rentabilidad($periodo, $comprobantes));
    }

    /**
     * Rentabilidad de grooming (precio del servicio menos costo de insumos).
     * Se consume vía fetch desde el widget del dashboard.
     */
    public function rentabilidadGrooming(Request $request): JsonResponse
    {
        abort_unless($this->tenantManager->check(), 404);

        /** @var User $user */
        $user = $request->user();

        abort_unless($this->userCan($user, 'grooming.view'), 403);

        $periodo = (string) $request->query('periodo', 'mes_actual');

        if (! in_array($periodo, ['semana', 'mes_actual', 'mes_pasado'], true)) {
            $periodo = 'mes_actual';
        }

        $comprobantes = DashboardStatsService::resolveRentabilidadComprobantes([
            'boleta' => $request->has('boleta') ? $request->boolean('boleta') : true,
            'factura' => $request->has('factura') ? $request->boolean('factura') : true,
            'ticket' => $request->has('ticket') ? $request->boolean('ticket') : true,
        ]);

        return response()->json($this->stats->rentabilidadGrooming($periodo, $comprobantes));
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
