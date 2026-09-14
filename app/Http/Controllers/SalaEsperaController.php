<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\Clinica\SalaEsperaHoyService;
use App\Services\Clinica\SalaEsperaNotifier;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SalaEsperaController extends Controller
{
    public function show(
        Request $request,
        TenantManager $tenants,
        SalaEsperaHoyService $salaEspera,
    ): JsonResponse|InertiaResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $tenant = $tenants->current()?->tenant;

        if ($request->inertia() || ! $request->expectsJson()) {
            abort_unless($user->can('sala-espera.view'), 403);

            return Inertia::render('clinica/sala-espera/index', [
                'board' => $salaEspera->board($user, $tenant),
            ]);
        }

        $tipo = (string) $request->string('tipo', SalaEsperaHoyService::TIPO_CONSULTA);

        return response()->json($salaEspera->forQueue($user, $tenant, $tipo));
    }

    public function buscar(
        Request $request,
        SalaEsperaHoyService $salaEspera,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($user->can('sala-espera.view') || $user->can('sala-espera.enviar'), 403);
        abort_unless($user->can('pacientes.view'), 403);

        $q = trim((string) $request->string('q', ''));

        return response()->json([
            'data' => $salaEspera->buscarPacientes($q),
        ]);
    }

    public function resumen(
        Request $request,
        TenantManager $tenants,
        SalaEsperaHoyService $salaEspera,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        return response()->json($salaEspera->iconosVisibles($user, $tenants->current()?->tenant));
    }

    public function enviar(
        Request $request,
        TenantManager $tenants,
        SalaEsperaHoyService $salaEspera,
        SalaEsperaNotifier $notifier,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $data = $request->validate([
            'paciente_id' => ['required', 'uuid', 'exists:pacientes,id'],
            'tipo' => ['required', 'in:consulta,grooming,cita'],
        ]);

        $paciente = Paciente::query()->whereKey($data['paciente_id'])->firstOrFail();
        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $result = $salaEspera->enviar($user, $tenant, $paciente, (string) $data['tipo']);
        $notifier->notify($tenant, $user, (string) $result['item']['tipo'], $result['item']);

        return response()->json($result);
    }

    public function marcarAtendido(
        Request $request,
        SalaEsperaHoyService $salaEspera,
        string $tipo,
        string $id,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $salaEspera->marcarAtendido($user, $tipo, $id);

        return response()->json(['ok' => true]);
    }
}
