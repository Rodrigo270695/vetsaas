<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DocumentoAutorizacionPlantillaFromAiRequest;
use App\Http\Requests\DocumentoAutorizacionPlantillaRequest;
use App\Models\ClinicSetting;
use App\Models\DocumentoAutorizacionPlantilla;
use App\Services\Clinica\DocumentoAutorizacionPlantillaFromAiService;
use App\Support\Clinica\DocumentoAutorizacionRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class DocumentoAutorizacionPlantillaController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->can('config-general.view') ?? false, 403);

        $logoUrl = ClinicSetting::current()->logo_url;
        $items = DocumentoAutorizacionPlantilla::query()
            ->orderBy('nombre')
            ->get()
            ->map(function (DocumentoAutorizacionPlantilla $row) use ($logoUrl): array {
                $cuerpo = DocumentoAutorizacionRenderer::sanitizeHtml($row->cuerpo);

                return [
                    'id' => $row->id,
                    'nombre' => $row->nombre,
                    'descripcion' => $row->descripcion,
                    'cuerpo' => $cuerpo,
                    'cuerpo_preview' => DocumentoAutorizacionRenderer::prepareCuerpoHtml($cuerpo, $logoUrl),
                    'activo' => $row->activo,
                    'updated_at' => $row->updated_at?->toIso8601String(),
                ];
            });

        return Inertia::render('configuracion/documentos-autorizacion/index', [
            'plantillas' => $items,
            'cuerpo_default' => DocumentoAutorizacionRenderer::defaultCuerpo(),
            'clinic_logo_url' => $logoUrl,
            'ia_disponible' => app(DocumentoAutorizacionPlantillaFromAiService::class)->isConfigured(),
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

    public function fromAi(
        DocumentoAutorizacionPlantillaFromAiRequest $request,
        DocumentoAutorizacionPlantillaFromAiService $ai,
    ): JsonResponse {
        $file = $request->file('archivo');
        if ($file === null) {
            return response()->json(['message' => 'Adjunta un PDF o una imagen.'], 422);
        }

        try {
            $draft = $ai->generateFromUpload($file);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($draft);
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
