<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Consulta;
use App\Models\DocumentoAutorizacionEnvio;
use App\Models\DocumentoAutorizacionPlantilla;
use App\Models\Tenant;
use App\Services\Clinica\DocumentoAutorizacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class DocumentoAutorizacionEnvioController extends Controller
{
    public function store(
        Request $request,
        Consulta $consulta,
        DocumentoAutorizacionService $service,
    ): RedirectResponse {
        abort_unless($request->user()?->can('historias-clinicas.update') ?? false, 403);

        $request->merge([
            'enviar_whatsapp' => $request->boolean('enviar_whatsapp'),
            'enviar_email' => $request->boolean('enviar_email'),
        ]);

        $data = $request->validate([
            'plantilla_id' => ['required', 'uuid', 'exists:documento_autorizacion_plantillas,id'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'enviar_whatsapp' => ['required', 'boolean'],
            'enviar_email' => ['required', 'boolean'],
        ]);

        if (! $data['enviar_whatsapp'] && ! $data['enviar_email']) {
            return back()->with('warning', 'Elige al menos WhatsApp o correo.');
        }

        $plantilla = DocumentoAutorizacionPlantilla::query()
            ->where('activo', true)
            ->findOrFail($data['plantilla_id']);

        $tenantId = tenant_id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        if ($tenant === null || ! is_string($tenant->slug) || $tenant->slug === '') {
            return back()->with('warning', 'No se pudo identificar la clínica.');
        }

        $result = $service->emitir(
            $consulta,
            $plantilla,
            $tenant,
            $data['telefono'] ?? null,
            $data['email'] ?? null,
            (bool) $data['enviar_whatsapp'],
            (bool) $data['enviar_email'],
            $request->user()?->id,
        );

        if ($result['warnings'] !== [] && ! $result['whatsapp_ok'] && ! $result['email_ok']) {
            return back()->with('warning', $result['warnings'][0] ?? 'No se pudo enviar el documento.');
        }

        $msg = 'Documento enviado al titular.';
        if ($result['warnings'] !== []) {
            return back()->with('success', $msg)->with('warning', implode(' ', $result['warnings']));
        }

        return back()->with('success', $msg);
    }

    public function pdf(
        Request $request,
        DocumentoAutorizacionEnvio $envio,
        DocumentoAutorizacionService $service,
    ): Response {
        abort_unless($request->user()?->can('historias-clinicas.view') ?? false, 403);
        abort_unless($envio->estado === DocumentoAutorizacionEnvio::ESTADO_FIRMADO, 404);

        $filename = preg_replace('/[^\w\-]+/u', '-', $envio->titulo) ?: 'documento';
        $filename .= '.pdf';
        $binary = $service->renderPdf($envio);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
