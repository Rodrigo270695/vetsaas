<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCajaEgresoRequest;
use App\Models\CajaEgreso;
use App\Models\CajaSesion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CajaEgresoController extends Controller
{
    public function index(Request $request, CajaSesion $cajaSesion): JsonResponse
    {
        abort_unless($request->user()?->can('caja-sesiones.view'), 403);

        $egresos = $cajaSesion->egresos()
            ->with(['creadoPor:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'egresos' => $egresos->map(static fn (CajaEgreso $e): array => self::serialize($e))->values(),
            'total' => number_format((float) $egresos->sum(static fn (CajaEgreso $e): float => (float) $e->monto), 2, '.', ''),
            'sesion_abierta' => $cajaSesion->estaAbierta(),
        ]);
    }

    public function store(StoreCajaEgresoRequest $request, CajaSesion $cajaSesion): RedirectResponse
    {
        $userId = Auth::id();
        abort_if($userId === null, 403);

        if (! $cajaSesion->estaAbierta()) {
            throw ValidationException::withMessages([
                'monto' => [__('caja.validation.egreso_sesion_cerrada')],
            ]);
        }

        if ((string) $cajaSesion->opened_by_id !== (string) $userId) {
            throw ValidationException::withMessages([
                'monto' => [__('caja.validation.egreso_sesion_no_tuya')],
            ]);
        }

        $data = $request->validated();

        DB::transaction(function () use ($cajaSesion, $data, $userId): void {
            CajaEgreso::query()->create([
                'caja_sesion_id' => $cajaSesion->getKey(),
                'monto' => number_format((float) $data['monto'], 2, '.', ''),
                'motivo' => $data['motivo'],
                'notas' => isset($data['notas']) && is_string($data['notas']) && trim($data['notas']) !== ''
                    ? trim($data['notas'])
                    : null,
                'created_by_id' => $userId,
            ]);
        });

        return back()->with('success', __('caja.flash.egreso_registrado'));
    }

    public function destroy(Request $request, CajaSesion $cajaSesion, CajaEgreso $egreso): RedirectResponse
    {
        abort_unless($request->user()?->can('caja-sesiones.egreso'), 403);

        $userId = Auth::id();
        abort_if($userId === null, 403);

        if ((string) $egreso->caja_sesion_id !== (string) $cajaSesion->getKey()) {
            abort(404);
        }

        if (! $cajaSesion->estaAbierta()) {
            throw ValidationException::withMessages([
                'egreso' => [__('caja.validation.egreso_sesion_cerrada')],
            ]);
        }

        if ((string) $cajaSesion->opened_by_id !== (string) $userId) {
            throw ValidationException::withMessages([
                'egreso' => [__('caja.validation.egreso_sesion_no_tuya')],
            ]);
        }

        $egreso->delete();

        return back()->with('success', __('caja.flash.egreso_eliminado'));
    }

    /**
     * @return array{
     *     id: string,
     *     monto: string,
     *     motivo: string,
     *     motivo_label: string,
     *     notas: string|null,
     *     created_at: string|null,
     *     created_by: string|null
     * }
     */
    public static function serialize(CajaEgreso $egreso): array
    {
        return [
            'id' => (string) $egreso->getKey(),
            'monto' => number_format((float) $egreso->monto, 2, '.', ''),
            'motivo' => (string) $egreso->motivo,
            'motivo_label' => CajaEgreso::labelMotivo((string) $egreso->motivo),
            'notas' => $egreso->notas,
            'created_at' => $egreso->created_at?->toIso8601String(),
            'created_by' => $egreso->creadoPor?->name,
        ];
    }
}
