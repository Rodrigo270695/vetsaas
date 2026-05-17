<?php

namespace App\Http\Controllers;

use App\Actions\SyncConsultaPlanTratamiento;
use App\Http\Requests\StoreConsultaPlanSeguimientoRequest;
use App\Http\Requests\UpsertConsultaPlanTratamientoRequest;
use App\Models\Consulta;
use App\Models\Producto;
use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ConsultaPlanTratamientoController extends Controller
{
    public function planTratamiento(Consulta $consulta): Response
    {
        $consulta->load([
            'historiaClinica.paciente.propietario:id,nombres,apellidos,razon_social',
            'veterinario:id,name',
            'planTratamiento.lineas.producto:id,nombre,unidad,sku',
            'planTratamiento.seguimientos.creadoPor:id,name',
            'planTratamiento.creadoPor:id,name',
            'planTratamiento.actualizadoPor:id,name',
        ]);

        return Inertia::render('clinica/historias-clinicas/plan-tratamiento', [
            'consulta' => $consulta,
        ]);
    }

    public function productosMedicamento(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->tenant_id !== null, 403);

        $sedeId = $this->resolveSedeIdParaStock($request, (string) $user->tenant_id);

        $forProductId = trim((string) $request->query('for_product_id', ''));
        if ($forProductId !== '' && Str::isUuid($forProductId)) {
            $row = $this->productoMedicamentoFilaConStock($forProductId, $sedeId);

            return response()->json([
                'data' => $row === null ? [] : [$row],
                'sede_id' => $sedeId !== '' ? $sedeId : null,
            ]);
        }

        $q = trim((string) $request->query('q', ''));
        $base = Producto::query()
            ->where('productos.activo', true)
            ->where('productos.medicamento', true)
            ->when($q !== '', function ($query) use ($q): void {
                $escaped = addcslashes(mb_strtolower($q, 'UTF-8'), '%_\\');
                $term = '%'.$escaped.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->whereRaw('LOWER(productos.nombre) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(productos.sku, \'\')) LIKE ?', [$term]);
                });
            });

        if ($sedeId !== '') {
            $items = (clone $base)
                ->leftJoin('existencias_sede as es', function ($join) use ($sedeId): void {
                    $join->on('es.producto_id', '=', 'productos.id')
                        ->where('es.sede_id', '=', $sedeId);
                })
                ->orderBy('productos.nombre')
                ->limit(25)
                ->get([
                    'productos.id',
                    'productos.nombre',
                    'productos.sku',
                    'productos.unidad',
                    DB::raw('COALESCE(es.cantidad, 0) as stock_sede'),
                ]);
        } else {
            $items = (clone $base)
                ->orderBy('productos.nombre')
                ->limit(25)
                ->get(['productos.id', 'productos.nombre', 'productos.sku', 'productos.unidad']);
            foreach ($items as $p) {
                $p->setAttribute('stock_sede', '0');
            }
        }

        $data = $items->map(fn (Producto $p): array => [
            'id' => (string) $p->id,
            'nombre' => (string) $p->nombre,
            'sku' => $p->sku,
            'unidad' => $p->unidad,
            'stock_sede' => (string) ($p->stock_sede ?? '0'),
        ])->values()->all();

        return response()->json([
            'data' => $data,
            'sede_id' => $sedeId !== '' ? $sedeId : null,
        ]);
    }

    /**
     * Sede para mostrar stock: query `sede_id` si es válida para el tenant; si no, la primera sede activa.
     */
    private function resolveSedeIdParaStock(Request $request, string $tenantId): string
    {
        $sedeIds = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->pluck('id')
            ->all();

        $sedeRequested = (string) $request->query('sede_id', '');

        if (Str::isUuid($sedeRequested) && in_array($sedeRequested, $sedeIds, true)) {
            return $sedeRequested;
        }

        return (string) ($sedeIds[0] ?? '');
    }

    /**
     * @return array{id: string, nombre: string, sku: ?string, unidad: ?string, stock_sede: string}|null
     */
    private function productoMedicamentoFilaConStock(string $productoId, string $sedeId): ?array
    {
        $base = Producto::query()
            ->where('productos.id', $productoId)
            ->where('productos.activo', true)
            ->where('productos.medicamento', true);

        if ($sedeId !== '') {
            $p = (clone $base)
                ->leftJoin('existencias_sede as es', function ($join) use ($sedeId): void {
                    $join->on('es.producto_id', '=', 'productos.id')
                        ->where('es.sede_id', '=', $sedeId);
                })
                ->first([
                    'productos.id',
                    'productos.nombre',
                    'productos.sku',
                    'productos.unidad',
                    DB::raw('COALESCE(es.cantidad, 0) as stock_sede'),
                ]);
        } else {
            $p = (clone $base)->first(['productos.id', 'productos.nombre', 'productos.sku', 'productos.unidad']);
            if ($p !== null) {
                $p->setAttribute('stock_sede', '0');
            }
        }

        if ($p === null) {
            return null;
        }

        return [
            'id' => (string) $p->id,
            'nombre' => (string) $p->nombre,
            'sku' => $p->sku,
            'unidad' => $p->unidad,
            'stock_sede' => (string) ($p->stock_sede ?? '0'),
        ];
    }

    public function upsert(
        UpsertConsultaPlanTratamientoRequest $request,
        Consulta $consulta,
    ): RedirectResponse {
        if ($consulta->cerrada_at !== null) {
            return redirect()
                ->route('clinica.historias-clinicas.consultas.plan-tratamiento', $consulta)
                ->with('error', __('historias-clinicas.flash.plan_consulta_cerrada'));
        }

        $validated = $request->validated();
        app(SyncConsultaPlanTratamiento::class)->handle($consulta, $validated, Auth::id());

        return redirect()
            ->route('clinica.historias-clinicas.consultas.plan-tratamiento', $consulta)
            ->with('success', __('historias-clinicas.flash.plan_saved'));
    }

    public function storeSeguimiento(
        StoreConsultaPlanSeguimientoRequest $request,
        Consulta $consulta,
    ): RedirectResponse {
        if ($consulta->cerrada_at !== null) {
            return redirect()
                ->route('clinica.historias-clinicas.consultas.plan-tratamiento', $consulta)
                ->with('error', __('historias-clinicas.flash.plan_consulta_cerrada'));
        }

        $plan = $consulta->planTratamiento;
        abort_if($plan === null, 422);

        $validated = $request->validated();
        $plan->seguimientos()->create([
            'registrado_at' => $validated['registrado_at'],
            'nota' => $validated['nota'],
            'created_by_id' => Auth::id(),
        ]);

        return redirect()
            ->route('clinica.historias-clinicas.consultas.plan-tratamiento', $consulta)
            ->with('success', __('historias-clinicas.flash.plan_seguimiento_created'));
    }
}
