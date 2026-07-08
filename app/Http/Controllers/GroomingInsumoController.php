<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GroomingInsumo;
use App\Models\GroomingServicio;
use App\Models\GroomingServicioInsumo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroomingInsumoController extends Controller
{
    /**
     * Catálogo de insumos de la clínica + insumos asignados a un servicio.
     */
    public function index(GroomingServicio $groomingServicio): JsonResponse
    {
        $this->ensureTables();

        $catalogo = GroomingInsumo::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (GroomingInsumo $i): array => [
                'id' => $i->id,
                'nombre' => $i->nombre,
            ])
            ->all();

        $asignados = $groomingServicio->insumos()
            ->with('insumo:id,nombre')
            ->get()
            ->map(fn (GroomingServicioInsumo $row): array => [
                'grooming_insumo_id' => $row->grooming_insumo_id,
                'nombre' => $row->insumo?->nombre ?? '',
                'precio' => (string) $row->precio,
            ])
            ->all();

        return response()->json([
            'catalogo' => $catalogo,
            'asignados' => $asignados,
            'moneda' => $groomingServicio->moneda,
        ]);
    }

    /**
     * Reemplaza por completo los insumos asignados a un servicio. Crea en el
     * catálogo los insumos nuevos (por nombre) que aún no existan.
     */
    public function sync(Request $request, GroomingServicio $groomingServicio): RedirectResponse
    {
        $this->ensureTables();

        $validated = $request->validate([
            'items' => ['present', 'array'],
            'items.*.grooming_insumo_id' => ['nullable', 'uuid'],
            'items.*.nombre' => ['required_without:items.*.grooming_insumo_id', 'nullable', 'string', 'max:150'],
            'items.*.precio' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $items = $validated['items'] ?? [];

        DB::transaction(function () use ($items, $groomingServicio): void {
            $resueltos = [];

            foreach ($items as $item) {
                $insumoId = $item['grooming_insumo_id'] ?? null;
                $nombre = trim((string) ($item['nombre'] ?? ''));

                if ($insumoId !== null) {
                    $insumo = GroomingInsumo::query()->find($insumoId);
                } else {
                    $insumo = null;
                }

                if ($insumo === null && $nombre !== '') {
                    $insumo = GroomingInsumo::query()
                        ->whereRaw('LOWER(nombre) = LOWER(?)', [$nombre])
                        ->first()
                        ?? GroomingInsumo::query()->create([
                            'nombre' => $nombre,
                            'activo' => true,
                        ]);
                }

                if ($insumo === null) {
                    continue;
                }

                GroomingServicioInsumo::query()->updateOrCreate(
                    [
                        'grooming_servicio_id' => $groomingServicio->id,
                        'grooming_insumo_id' => $insumo->id,
                    ],
                    [
                        'precio' => (float) $item['precio'],
                    ],
                );

                $resueltos[] = $insumo->id;
            }

            $groomingServicio->insumos()
                ->when($resueltos !== [], fn ($q) => $q->whereNotIn('grooming_insumo_id', $resueltos))
                ->delete();
        });

        return back()->with('success', __('tarifas-servicios.insumos.saved'));
    }

    private function ensureTables(): void
    {
        abort_unless(
            Schema::hasTable('grooming_insumos') && Schema::hasTable('grooming_servicio_insumo'),
            503,
            __('tarifas-servicios.insumos.missing_table'),
        );
    }
}
