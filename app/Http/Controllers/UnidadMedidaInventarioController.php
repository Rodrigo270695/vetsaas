<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnidadMedidaInventarioStoreRequest;
use App\Http\Requests\UnidadMedidaInventarioUpdateRequest;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UnidadMedidaInventarioController extends Controller
{
    public function store(UnidadMedidaInventarioStoreRequest $request): RedirectResponse
    {
        $codigo = $request->codigoResuelto();

        UnidadMedida::create([
            'codigo' => $codigo,
            'nombre' => trim((string) $request->validated('nombre')),
            'es_sistema' => false,
            'activo' => true,
        ]);

        return back()->with('success', 'Unidad de medida creada correctamente.');
    }

    public function update(UnidadMedidaInventarioUpdateRequest $request, UnidadMedida $unidadMedida): RedirectResponse
    {
        if ($unidadMedida->es_sistema) {
            return back()->withErrors(['unidad_medida' => 'Las unidades del sistema no se pueden editar.']);
        }

        $unidadMedida->update([
            'nombre' => trim((string) $request->validated('nombre')),
        ]);

        return back()->with('success', 'Unidad de medida actualizada correctamente.');
    }

    public function destroy(Request $request, UnidadMedida $unidadMedida): RedirectResponse
    {
        abort_unless($request->user()?->can('productos.update'), 403);

        if ($unidadMedida->es_sistema) {
            return back()->withErrors(['unidad_medida' => 'Las unidades del sistema no se pueden eliminar.']);
        }

        if (Producto::withTrashed()->where('unidad', $unidadMedida->codigo)->exists()) {
            return back()->withErrors([
                'unidad_medida' => 'No se puede eliminar: hay productos que usan esta unidad.',
            ]);
        }

        $unidadMedida->delete();

        return back()->with('success', 'Unidad de medida eliminada correctamente.');
    }
}
