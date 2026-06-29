<?php

namespace App\Http\Controllers;

use App\Models\FelSerie;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FelSerieController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $sedesOpciones = Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        $sedeIds = $sedesOpciones->pluck('id')->all();

        $sedeRequested = (string) $request->string('sede_id', '');
        $sedeFiltro = '';
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sedeRequested) === 1
            && in_array($sedeRequested, $sedeIds, true)) {
            $sedeFiltro = $sedeRequested;
        }

        $sedeNombres = $sedesOpciones->pluck('nombre', 'id');

        $query = FelSerie::query()
            ->orderBy('sede_id')
            ->orderBy('tipo_comprobante')
            ->orderBy('serie');

        if ($sedeFiltro !== '') {
            $query->where('sede_id', $sedeFiltro);
        }

        $series = $query
            ->get()
            ->map(fn (FelSerie $s) => [
                'id'                 => $s->id,
                'sede_id'            => $s->sede_id,
                'sede_nombre'        => $sedeNombres[$s->sede_id] ?? '—',
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
            'series'         => $series,
            'tipos'          => $tipos,
            'sedes_opciones' => $sedesOpciones,
            'filters'        => [
                'sede_id' => $sedeFiltro,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 403);

        $data = $request->validate([
            'sede_id'            => [
                'required',
                'uuid',
                Rule::exists('sedes', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'tipo_comprobante'   => ['required', 'integer', 'in:1,2,3,4,5'],
            'serie'              => ['required', 'string', 'size:4', 'regex:/^[A-Z0-9]{4}$/'],
            'ultimo_correlativo' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['serie'] = strtoupper($data['serie']);

        $existe = FelSerie::query()
            ->where('sede_id', $data['sede_id'])
            ->where('tipo_comprobante', $data['tipo_comprobante'])
            ->where('serie', $data['serie'])
            ->exists();

        if ($existe) {
            return back()->withErrors([
                'serie' => 'Ya existe esa serie para este tipo de comprobante en la sede seleccionada.',
            ]);
        }

        FelSerie::create([
            'sede_id'            => $data['sede_id'],
            'tipo_comprobante'   => $data['tipo_comprobante'],
            'serie'              => $data['serie'],
            'ultimo_correlativo' => $data['ultimo_correlativo'] ?? 0,
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
