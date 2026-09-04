<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DocumentoAutorizacionPlantillaRequest;
use App\Models\ClinicSetting;
use App\Models\DocumentoAutorizacionPlantilla;
use App\Support\Clinica\DocumentoAutorizacionRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class DocumentoAutorizacionPlantillaController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->can('config-general.view') ?? false, 403);

        $items = DocumentoAutorizacionPlantilla::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn (DocumentoAutorizacionPlantilla $row) => [
                'id' => $row->id,
                'nombre' => $row->nombre,
                'descripcion' => $row->descripcion,
                'cuerpo' => $row->cuerpo,
                'activo' => $row->activo,
                'updated_at' => $row->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('configuracion/documentos-autorizacion/index', [
            'plantillas' => $items,
            'cuerpo_default' => DocumentoAutorizacionRenderer::defaultCuerpo(),
            'clinic_logo_url' => ClinicSetting::current()->logo_url,
        ]);
    }

    public function store(DocumentoAutorizacionPlantillaRequest $request): RedirectResponse
    {
        $userId = Auth::id();
        DocumentoAutorizacionPlantilla::query()->create([
            ...$request->validated(),
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return back()->with('success', 'Plantilla creada.');
    }

    public function update(
        DocumentoAutorizacionPlantillaRequest $request,
        DocumentoAutorizacionPlantilla $plantilla,
    ): RedirectResponse {
        $plantilla->update([
            ...$request->validated(),
            'updated_by_id' => Auth::id(),
        ]);

        return back()->with('success', 'Plantilla actualizada.');
    }

    public function destroy(DocumentoAutorizacionPlantilla $plantilla): RedirectResponse
    {
        abort_unless(request()->user()?->can('config-general.update') ?? false, 403);
        $plantilla->delete();

        return back()->with('success', 'Plantilla eliminada.');
    }
}
