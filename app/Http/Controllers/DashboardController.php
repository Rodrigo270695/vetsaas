<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Onboarding\ClinicOnboardingService;
use App\Support\Tenancy\TenantModuleAccess;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly DashboardStatsService $stats,
        private readonly ClinicOnboardingService $onboarding,
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
            'onboarding' => $tenantModel !== null
                ? $this->onboarding->snapshot($tenantModel, $user, $request)
                : null,
            ...$this->stats->build($user, $capabilities),
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
