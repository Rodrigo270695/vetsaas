<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\User;
use App\Services\Clinica\SalaEsperaHoyService;
use App\Services\Clinica\SalaEsperaNotifier;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            $soloMios = $request->string('alcance')->toString() !== 'todos';

            return Inertia::render('clinica/sala-espera/index', [
                'board' => $salaEspera->board($user, $tenant, $soloMios),
                'usuarios' => $tenant !== null ? $salaEspera->usuariosActivos($tenant) : [],
                'alcance' => $soloMios ? 'mios' : 'todos',
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

    public function usuarios(
        Request $request,
        TenantManager $tenants,
        SalaEsperaHoyService $salaEspera,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless(
            $user->can('sala-espera.enviar')
            || $user->can('sala-espera.view')
            || $user->can('citas.view')
            || $user->can('citas.create'),
            403,
        );

        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        return response()->json([
            'data' => $salaEspera->usuariosActivos($tenant),
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
            'tratante_id' => ['nullable', 'uuid'],
        ]);

        $tratanteId = $request->exists('tratante_id')
            ? ($data['tratante_id'] ?? null)
            : $user->id;
        $this->assertTratanteDelTenant($user, $tratanteId);

        $paciente = Paciente::query()->whereKey($data['paciente_id'])->firstOrFail();
        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $result = $salaEspera->enviar($user, $tenant, $paciente, (string) $data['tipo'], $tratanteId);
        $notifier->ping($tenant, $user, 'enviar', (string) $result['item']['tipo'], $result['item']);

        return response()->json($result);
    }

    public function llamar(
        Request $request,
        TenantManager $tenants,
        SalaEsperaHoyService $salaEspera,
        SalaEsperaNotifier $notifier,
        string $tipo,
        string $id,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $item = $salaEspera->item($user, $tipo, $id);
        $notifier->ping($tenant, $user, 'llamar', (string) $item['tipo'], $item);

        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function marcarAtendido(
        Request $request,
        TenantManager $tenants,
        SalaEsperaHoyService $salaEspera,
        SalaEsperaNotifier $notifier,
        string $tipo,
        string $id,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $salaEspera->marcarAtendido($user, $tipo, $id);
        $notifier->ping($tenant, $user, 'atendido', $tipo, ['id' => $id, 'tipo' => $tipo]);

        return response()->json(['ok' => true]);
    }

    public function retirar(
        Request $request,
        TenantManager $tenants,
        SalaEsperaHoyService $salaEspera,
        SalaEsperaNotifier $notifier,
        string $tipo,
        string $id,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $salaEspera->retirar($user, $tipo, $id);
        $notifier->ping($tenant, $user, 'retirar', $tipo, ['id' => $id, 'tipo' => $tipo]);

        return response()->json(['ok' => true]);
    }

    public function asignar(
        Request $request,
        TenantManager $tenants,
        SalaEsperaHoyService $salaEspera,
        SalaEsperaNotifier $notifier,
        string $tipo,
        string $id,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        $data = $request->validate([
            'tratante_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $user->tenant_id)->where('is_active', true),
                ),
            ],
        ]);

        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $item = $salaEspera->asignarTratante($user, $tipo, $id, $data['tratante_id'] ?? null);
        $notifier->ping($tenant, $user, 'asignar', (string) $item['tipo'], $item);

        return response()->json(['ok' => true, 'item' => $item]);
    }

    private function assertTratanteDelTenant(User $user, ?string $tratanteId): void
    {
        if ($tratanteId === null || $tratanteId === '') {
            return;
        }

        $ok = User::query()
            ->whereKey($tratanteId)
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->exists();

        abort_unless($ok, 422, 'El profesional no pertenece a esta clínica.');
    }
}
