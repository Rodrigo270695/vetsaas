<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Distrito;
use App\Models\Provincia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints internos para el catálogo geográfico jerárquico.
 *
 * Diseño:
 *   - Solo lecturas. El catálogo no se edita desde la app (proviene
 *     del INEI y se siembra desde un seeder/import).
 *   - Respuesta minimalista (`id`, `name`) — los componentes UI
 *     (combobox) solo necesitan eso para renderizar opciones.
 *   - Se filtran SIEMPRE por `status = true` para que las opciones
 *     inactivas no aparezcan en la UI (soft-disable a nivel catálogo).
 *   - Sin paginación: los conjuntos son pequeños por filtro (máx ~30
 *     distritos por provincia). Si crecen, se puede paginar luego.
 */
class GeoController extends Controller
{
    /**
     * Lista departamentos activos del catálogo.
     *
     * En la práctica esta ruta no se usa desde Sedes (los departamentos
     * se cargan inline en page props del index), pero se expone para
     * uso futuro de otros módulos que necesiten autocomplete.
     */
    public function departamentos(): JsonResponse
    {
        $data = Departamento::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($data);
    }

    /**
     * Lista provincias activas de un departamento.
     *
     * @return JsonResponse  Array vacío si no se especifica departamento.
     */
    public function provincias(Request $request): JsonResponse
    {
        $departamentoId = $request->integer('departamento_id');

        if ($departamentoId <= 0) {
            return response()->json([]);
        }

        $data = Provincia::query()
            ->where('departamento_id', $departamentoId)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($data);
    }

    /**
     * Lista distritos activos de una provincia.
     *
     * @return JsonResponse  Array vacío si no se especifica provincia.
     */
    public function distritos(Request $request): JsonResponse
    {
        $provinciaId = $request->integer('provincia_id');

        if ($provinciaId <= 0) {
            return response()->json([]);
        }

        $data = Distrito::query()
            ->where('provincia_id', $provinciaId)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($data);
    }
}
