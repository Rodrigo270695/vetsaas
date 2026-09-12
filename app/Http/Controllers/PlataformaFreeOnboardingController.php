<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Tenancy\FreeOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlataformaFreeOnboardingController extends Controller
{
    public function index(Request $request, FreeOnboardingService $service): Response
    {
        $payload = $service->paginate(
            trim((string) $request->string('search', '')),
            (string) $request->string('stage', 'todos'),
            (string) $request->string('plan', 'free'),
            (int) $request->integer('per_page', 15),
        );

        return Inertia::render('plataforma/tenants/free-onboarding', $payload);
    }

    public function sendCheckIn(Request $request, Tenant $tenant, FreeOnboardingService $service): RedirectResponse
    {
        $result = $service->sendCheckIn($tenant);

        if ($result['whatsapp_sent']) {
            return back()->with('success', 'WhatsApp de seguimiento enviado a '.$tenant->telefono.'.');
        }

        if ($result['email_sent'] ?? false) {
            return back()->with('success', 'Correo de seguimiento enviado a '.$tenant->email_admin.'.');
        }

        return back()->with('error', $result['warning'] ?? 'No se pudo enviar el seguimiento.');
    }

    public function sendCheckInBulk(Request $request, FreeOnboardingService $service): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid'],
        ]);

        $result = $service->sendCheckInBulk($data['ids']);

        if ($result['sent'] === 0 && $result['failed'] === 0) {
            return back()->with('info', 'Ningún tenant tenía celular o correo válido.');
        }

        $parts = [];
        if ($result['sent'] > 0) {
            $parts[] = $result['sent'].' enviados';
        }
        if ($result['skipped'] > 0) {
            $parts[] = $result['skipped'].' sin contacto';
        }
        if ($result['failed'] > 0) {
            $parts[] = $result['failed'].' fallidos';
        }

        $message = 'Seguimiento Free: '.implode(', ', $parts).'.';
        if ($result['failed'] > 0) {
            if ($result['errors'] !== []) {
                $message .= ' '.$result['errors'][0];
            }

            return back()->with('error', $message);
        }

        return back()->with('success', $message);
    }
}
