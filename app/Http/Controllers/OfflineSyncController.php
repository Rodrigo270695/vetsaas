<?php

namespace App\Http\Controllers;

use App\Http\Requests\OfflineSyncPushRequest;
use App\Models\User;
use App\Services\Offline\OfflineBootstrapService;
use App\Services\Offline\OfflineSyncPushService;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfflineSyncController extends Controller
{
    public function cola(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('offline/cola/index');
    }

    public function bootstrap(Request $request, OfflineBootstrapService $bootstrap, TenantManager $tenants): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $scope = $request->string('scope')->toString();

        return match ($scope) {
            'caja' => $this->bootstrapCaja($user, $bootstrap, $tenants),
            'clinica' => $this->bootstrapClinica($user, $bootstrap),
            'inventario' => $this->bootstrapInventario($user, $bootstrap),
            'servicios' => $this->bootstrapServicios($user, $bootstrap),
            'configuracion' => $this->bootstrapConfiguracion($user, $bootstrap),
            'all' => response()->json([
                'data' => array_filter([
                    'caja' => ($user->can('ventas.create') ?? false)
                        ? $bootstrap->caja($user, $tenants->current()?->tenant)
                        : null,
                    'clinica' => $this->userCanBootstrapClinica($user)
                        ? $bootstrap->clinica($user)
                        : null,
                    'inventario' => $this->userCanBootstrapInventario($user)
                        ? $bootstrap->inventario($user)
                        : null,
                    'servicios' => $this->userCanBootstrapServicios($user)
                        ? $bootstrap->servicios($user)
                        : null,
                    'configuracion' => $this->userCanBootstrapConfiguracion($user)
                        ? $bootstrap->configuracion($user)
                        : null,
                ]),
            ]),
            default => abort(404),
        };
    }

    public function push(OfflineSyncPushRequest $request, OfflineSyncPushService $push): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $results = [];

        foreach ($request->validated('items') as $item) {
            $results[] = $push->process($user, $item);
        }

        return response()->json(['results' => $results]);
    }

    private function bootstrapCaja(
        User $user,
        OfflineBootstrapService $bootstrap,
        TenantManager $tenants,
    ): JsonResponse {
        abort_unless($user->can('ventas.create') ?? false, 403);

        return response()->json([
            'data' => $bootstrap->caja($user, $tenants->current()?->tenant),
        ]);
    }

    private function bootstrapClinica(User $user, OfflineBootstrapService $bootstrap): JsonResponse
    {
        abort_unless($this->userCanBootstrapClinica($user), 403);

        return response()->json([
            'data' => $bootstrap->clinica($user),
        ]);
    }

    private function bootstrapInventario(User $user, OfflineBootstrapService $bootstrap): JsonResponse
    {
        abort_unless($this->userCanBootstrapInventario($user), 403);

        return response()->json([
            'data' => $bootstrap->inventario($user),
        ]);
    }

    private function bootstrapServicios(User $user, OfflineBootstrapService $bootstrap): JsonResponse
    {
        abort_unless($this->userCanBootstrapServicios($user), 403);

        return response()->json([
            'data' => $bootstrap->servicios($user),
        ]);
    }

    private function bootstrapConfiguracion(User $user, OfflineBootstrapService $bootstrap): JsonResponse
    {
        abort_unless($this->userCanBootstrapConfiguracion($user), 403);

        return response()->json([
            'data' => $bootstrap->configuracion($user),
        ]);
    }

    private function userCanBootstrapClinica(User $user): bool
    {
        return ($user->can('citas.view') ?? false)
            || ($user->can('propietarios.view') ?? false)
            || ($user->can('pacientes.view') ?? false)
            || ($user->can('historias-clinicas.view') ?? false)
            || ($user->can('vacunaciones.view') ?? false)
            || ($user->can('cirugias.view') ?? false)
            || ($user->can('hospitalizacion.view') ?? false)
            || ($user->can('recetas.view') ?? false)
            || ($user->can('laboratorio.view') ?? false);
    }

    private function userCanBootstrapInventario(User $user): bool
    {
        return ($user->can('productos.view') ?? false)
            || ($user->can('categorias-inventario.view') ?? false)
            || ($user->can('movimientos-stock.view') ?? false)
            || ($user->can('compras.view') ?? false)
            || ($user->can('proveedores.view') ?? false)
            || ($user->can('stock.view') ?? false);
    }

    private function userCanBootstrapServicios(User $user): bool
    {
        return ($user->can('grooming.view') ?? false)
            || ($user->can('grooming.create') ?? false)
            || ($user->can('hotel.view') ?? false)
            || ($user->can('hotel.create') ?? false);
    }

    private function userCanBootstrapConfiguracion(User $user): bool
    {
        return ($user->can('sedes.view') ?? false)
            || ($user->can('sedes.create') ?? false);
    }
}
