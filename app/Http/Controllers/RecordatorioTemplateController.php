<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RecordatorioTemplate;
use App\Support\Notifications\RecordatorioTemplateCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RecordatorioTemplateController extends Controller
{
    public function index(): Response
    {
        RecordatorioTemplateCatalog::ensureSeeded();

        $templates = RecordatorioTemplate::query()
            ->orderBy('grupo')
            ->orderBy('orden')
            ->orderBy('tipo')
            ->get(['id', 'tipo', 'grupo', 'canal', 'cuerpo', 'activo', 'orden', 'updated_at']);

        $byTipo = $templates->keyBy('tipo');

        $groups = [];
        foreach (RecordatorioTemplateCatalog::definitions() as $definition) {
            $row = $byTipo->get($definition['tipo']);
            if ($row === null) {
                continue;
            }

            $groups[$definition['grupo']][] = [
                'id' => $row->id,
                'tipo' => $row->tipo,
                'grupo' => $row->grupo,
                'canal' => $row->canal,
                'cuerpo' => $row->cuerpo,
                'cuerpo_default' => $definition['cuerpo_default'],
                'activo' => (bool) $row->activo,
                'orden' => (int) $row->orden,
                'variables' => $definition['variables'],
                'preview' => RecordatorioTemplateCatalog::preview((string) $row->cuerpo),
                'updated_at' => $row->updated_at?->toIso8601String(),
            ];
        }

        return Inertia::render('comunicaciones/plantillas/index', [
            'groups' => $groups,
            'groupOrder' => [
                RecordatorioTemplateCatalog::GRUPO_CITAS,
                RecordatorioTemplateCatalog::GRUPO_VACUNAS,
                RecordatorioTemplateCatalog::GRUPO_CUMPLE,
                RecordatorioTemplateCatalog::GRUPO_GROOMING,
                RecordatorioTemplateCatalog::GRUPO_HOTEL,
            ],
        ]);
    }

    public function update(Request $request, RecordatorioTemplate $plantilla): RedirectResponse
    {
        $data = $request->validate([
            'cuerpo' => ['required', 'string', 'min:10', 'max:4000'],
            'activo' => ['required', 'boolean'],
        ]);

        abort_unless(
            in_array($plantilla->tipo, RecordatorioTemplateCatalog::tipos(), true),
            404,
        );

        $plantilla->update([
            'cuerpo' => $data['cuerpo'],
            'activo' => $data['activo'],
            'updated_by_id' => Auth::id(),
        ]);

        return back()->with('success', 'Plantilla actualizada correctamente.');
    }

    public function restore(RecordatorioTemplate $plantilla): RedirectResponse
    {
        $default = RecordatorioTemplateCatalog::defaultBody($plantilla->tipo);
        abort_if($default === null, 404);

        $plantilla->update([
            'cuerpo' => $default,
            'activo' => true,
            'updated_by_id' => Auth::id(),
        ]);

        return back()->with('success', 'Plantilla restaurada al texto por defecto.');
    }
}
