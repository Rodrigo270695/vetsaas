<?php

namespace App\Http\Controllers;

use App\Models\FelSerie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FelSerieController extends Controller
{
    public function index(): Response
    {
        $series = FelSerie::query()
            ->orderBy('tipo_comprobante')
            ->orderBy('serie')
            ->get()
            ->map(fn (FelSerie $s) => [
                'id'                 => $s->id,
                'tipo_comprobante'   => $s->tipo_comprobante,
                'tipo_label'         => FelSerie::labelTipo($s->tipo_comprobante),
                'serie'              => $s->serie,
                'ultimo_correlativo' => $s->ultimo_correlativo,
                'activo'             => $s->activo,
                'tiene_documentos'   => $s->documentos()->exists(),
            ]);

        $tipos = collect([
            FelSerie::TIPO_FACTURA,
            FelSerie::TIPO_BOLETA,
            FelSerie::TIPO_NOTA_CREDITO,
            FelSerie::TIPO_NOTA_DEBITO,
            FelSerie::TIPO_GUIA_REMISION,
        ])->map(fn (int $t) => [
            'value' => $t,
            'label' => FelSerie::labelTipo($t),
            'hint'  => FelSerie::prefijosPermitidos($t),
        ]);

        return Inertia::render('facturacion/series/index', [
            'series' => $series,
            'tipos'  => $tipos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tipo_comprobante' => ['required', 'integer', 'in:1,2,3,4,5'],
            'serie'            => ['required', 'string', 'size:4', 'regex:/^[A-Z0-9]{4}$/'],
        ]);

        $data['serie'] = strtoupper($data['serie']);

        $existe = FelSerie::query()
            ->where('tipo_comprobante', $data['tipo_comprobante'])
            ->where('serie', $data['serie'])
            ->exists();

        if ($existe) {
            return back()->withErrors(['serie' => 'Ya existe una serie con ese código para este tipo de comprobante.']);
        }

        FelSerie::create([
            'tipo_comprobante'   => $data['tipo_comprobante'],
            'serie'              => $data['serie'],
            'ultimo_correlativo' => 0,
            'activo'             => true,
        ]);

        return back()->with('success', 'Serie creada correctamente.');
    }

    public function update(Request $request, FelSerie $felSerie): RedirectResponse
    {
        $data = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        $felSerie->update(['activo' => $data['activo']]);

        return back()->with('success', 'Serie actualizada.');
    }

    public function destroy(FelSerie $felSerie): RedirectResponse
    {
        if ($felSerie->documentos()->exists()) {
            return back()->withErrors(['general' => 'No se puede eliminar una serie que ya tiene comprobantes emitidos.']);
        }

        $felSerie->delete();

        return back()->with('success', 'Serie eliminada.');
    }
}
