<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\Clinica\SalaEsperaHoyService;
use App\Services\Clinica\SalaEsperaNotifier;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaEsperaController extends Controller
{
    public function show(
        Request $request,
        TenantManager $tenants,
        SalaEsperaHoyService $salaEspera,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $tipo = (string) $request->string('tipo', SalaEsperaHoyService::TIPO_CONSULTA);
        $tenant = $tenants->current()?->tenant;

        return response()->json($salaEspera->forQueue($user, $tenant, $tipo));
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
