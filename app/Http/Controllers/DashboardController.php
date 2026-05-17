<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Dashboard\DashboardStatsService;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
            'citas' => $user->can('citas.view'),
            'consultas' => $user->can('historias-clinicas.view'),
            'ventas' => $user->can('ventas.view'),
            'pacientes' => $user->can('pacientes.view'),
            'propietarios' => $user->can('propietarios.view'),
            'vacunaciones' => $user->can('vacunaciones.view'),
            'grooming' => $user->can('grooming.view'),
            'hotel' => $user->can('hotel.view'),
            'hospitalizacion' => $user->can('hospitalizacion.view'),
            'productos' => $user->can('productos.view'),
            'alertas_stock' => $user->can('alertas-stock.view'),
            'caja_sesiones' => $user->can('caja-sesiones.view'),
        ];

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
}
