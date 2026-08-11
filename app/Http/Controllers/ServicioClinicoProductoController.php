<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ServicioClinico;
use App\Models\ServicioClinicoProducto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Productos de inventario vinculados a un servicio clínico (paquete vacuna, etc.).
 */
class ServicioClinicoProductoController extends Controller
{
    public function index(ServicioClinico $servicioClinico): JsonResponse
    {
        $this->ensureTables();

        $catalogo = Producto::query()
            ->where('activo', true)
            ->where('medicamento', true)
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre', 'sku'])
            ->map(fn (Producto $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'sku' => $p->sku,
            ])
            ->all();

        $asignados = $servicioClinico->productosPaquete()
            ->with('producto:id,nombre,sku')
            ->get()
            ->map(fn (ServicioClinicoProducto $row): array => [
                'producto_id' => $row->producto_id,
                'nombre' => $row->producto?->nombre ?? '',
                'sku' => $row->producto?->sku,
                'cantidad' => (string) $row->cantidad,
            ])
            ->all();

        return response()->json([
            'catalogo' => $catalogo,
            'asignados' => $asignados,
            'moneda' => $servicioClinico->moneda,
        ]);
    }

    public function sync(Request $request, ServicioClinico $servicioClinico): RedirectResponse
    {
        $this->ensureTables();

        $validated = $request->validate([
            'items' => ['present', 'array'],
            'items.*.producto_id' => [
                'required',
                'uuid',
                Rule::exists('productos', 'id')->where(
                    fn ($q) => $q->where('activo', true)->where('medicamento', true),
                ),
            ],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.001', 'max:9999'],
        ]);

        $items = $validated['items'] ?? [];

        DB::transaction(function () use ($items, $servicioClinico): void {
            $resueltos = [];
            $orden = 0;

            foreach ($items as $item) {
                $productoId = (string) $item['producto_id'];
                ServicioClinicoProducto::query()->updateOrCreate(
                    [
                        'servicio_clinico_id' => $servicioClinico->id,
                        'producto_id' => $productoId,
                    ],
                    [
                        'cantidad' => (float) $item['cantidad'],
                        'orden' => $orden++,
                    ],
                );
                $resueltos[] = $productoId;
            }

            $servicioClinico->productosPaquete()
                ->when($resueltos !== [], fn ($q) => $q->whereNotIn('producto_id', $resueltos))
                ->delete();
        });

        return back()->with('success', __('tarifas-servicios.paquete.saved'));
    }

    private function ensureTables(): void
    {
        abort_unless(
            Schema::hasTable('servicio_clinico_productos'),
            503,
            __('tarifas-servicios.paquete.missing_table'),
        );
    }
}
