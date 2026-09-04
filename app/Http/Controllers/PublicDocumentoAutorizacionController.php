<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClinicSetting;
use App\Models\DocumentoAutorizacionEnvio;
use App\Services\Clinica\DocumentoAutorizacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class PublicDocumentoAutorizacionController extends Controller
{
    public function show(string $token): Response
    {
        $envio = $this->findByToken($token);
        $clinic = ClinicSetting::current();

        return Inertia::render('public/documento-autorizacion', [
            'token' => $token,
            'titulo' => $envio->titulo,
            'cuerpo' => $envio->cuerpo_snapshot,
            'estado' => $envio->isPending() ? 'pendiente' : $envio->estado,
            'expirado' => $envio->estado === DocumentoAutorizacionEnvio::ESTADO_PENDIENTE
                && $envio->expires_at->isPast(),
            'firmado_at' => $envio->firmado_at?->toIso8601String(),
            'clinic' => [
                'nombre' => trim((string) ($clinic->nombre_comercial ?: $clinic->razon_social)) ?: (string) config('app.name'),
                'logo_url' => $clinic->logo_url,
            ],
            'paciente_nombre' => $envio->paciente?->nombre,
            'propietario_nombre' => $envio->propietario?->displayName()
                ?? $envio->paciente?->propietario?->displayName(),
            'firmante_nombre_sugerido' => $envio->propietario?->displayName()
                ?? $envio->paciente?->propietario?->displayName(),
            'firmante_documento_sugerido' => $envio->propietario?->numero_documento
                ?? $envio->paciente?->propietario?->numero_documento,
            'submit_url' => route('tenant.public.autorizacion.store', [
                'token' => $token,
            ]),
        ]);
    }

    public function store(
        Request $request,
        string $token,
        DocumentoAutorizacionService $service,
    ): RedirectResponse {
        $envio = $this->findByToken($token);
        if (! $envio->isPending()) {
            return back()->with('warning', 'Este documento ya no se puede firmar.');
        }

        $request->merge([
            'acepto' => $request->boolean('acepto'),
        ]);

        $data = $request->validate([
            'firmante_nombre' => ['required', 'string', 'max:180'],
            'firmante_documento' => ['nullable', 'string', 'max:40'],
            'firma' => ['required', 'string', 'max:600000'],
            'acepto' => ['accepted'],
        ]);

        try {
            $service->firmar(
                $envio,
                $data['firma'],
                $data['firmante_nombre'],
                $data['firmante_documento'] ?? null,
                $request->ip(),
            );
        } catch (RuntimeException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'Documento firmado. Gracias.');
    }

    private function findByToken(string $token): DocumentoAutorizacionEnvio
    {
        return DocumentoAutorizacionEnvio::query()
            ->where('token', $token)
            ->with(['paciente.propietario', 'propietario'])
            ->firstOrFail();
    }
}
