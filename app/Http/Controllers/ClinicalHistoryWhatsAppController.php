<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Services\Clinica\ClinicalHistoryWhatsAppSender;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

final class ClinicalHistoryWhatsAppController extends Controller
{
    public function consulta(
        Request $request,
        Consulta $consulta,
        ClinicalHistoryWhatsAppSender $sender,
    ): RedirectResponse {
        abort_unless($request->user()?->can('historias-clinicas.view') ?? false, 403);

        $consulta->loadMissing(
            'historiaClinica.paciente.propietario:id,nombres,apellidos,razon_social,telefono',
        );

        $paciente = $consulta->historiaClinica?->paciente;
        abort_if($paciente === null, 404);

        return $this->send(
            request: $request,
            paciente: $paciente,
            sender: $sender,
            routeName: 'tenant.public.clinical-history.consulta',
            routeParameters: ['consulta' => (string) $consulta->getKey()],
            documentLabel: 'la consulta clínica',
            logContext: ['consulta_id' => (string) $consulta->getKey()],
        );
    }

    public function historial(
        Request $request,
        Paciente $paciente,
        ClinicalHistoryWhatsAppSender $sender,
    ): RedirectResponse {
        abort_unless($request->user()?->can('pacientes.view') ?? false, 403);
        abort_unless(
            ($request->user()?->can('historias-clinicas.view') ?? false)
            || ($request->user()?->can('vacunaciones.view') ?? false),
            403,
        );

        $paciente->loadMissing('propietario:id,nombres,apellidos,razon_social,telefono');

        return $this->send(
            request: $request,
            paciente: $paciente,
            sender: $sender,
            routeName: 'tenant.public.clinical-history.historial',
            routeParameters: ['paciente' => (string) $paciente->getKey()],
            documentLabel: 'el historial clínico completo',
            logContext: ['paciente_id' => (string) $paciente->getKey()],
        );
    }

    /**
     * @param  array<string, string>  $routeParameters
     * @param  array<string, string>  $logContext
     */
    private function send(
        Request $request,
        Paciente $paciente,
        ClinicalHistoryWhatsAppSender $sender,
        string $routeName,
        array $routeParameters,
        string $documentLabel,
        array $logContext,
    ): RedirectResponse {
        $data = $request->validate([
            'telefono' => ['nullable', 'string', 'max:20'],
        ]);

        $phone = trim((string) ($data['telefono'] ?? '')) !== ''
            ? (string) $data['telefono']
            : $paciente->propietario?->telefono;

        $chatId = WhatsAppChatId::fromPhone($phone);
        if ($chatId === null) {
            return back()->with('warning', 'Ingresa un número de WhatsApp válido para el titular.');
        }

        $tenantId = tenant_id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        $tenantSlug = $tenant?->slug;

        if ($tenant === null || ! is_string($tenantSlug) || $tenantSlug === '') {
            return back()->with('warning', 'No se pudo identificar la clínica para generar el enlace.');
        }

        $ttlMinutes = max(5, (int) config('clinic-documents.public_link_ttl_minutes', 10080));
        $url = URL::temporarySignedRoute(
            $routeName,
            now()->addMinutes($ttlMinutes),
            [
                'tenant_subdomain' => $tenantSlug,
                ...$routeParameters,
            ],
        );

        $clinic = ClinicSetting::current();
        $clinicName = trim((string) ($clinic->nombre_comercial ?: $clinic->razon_social))
            ?: (string) config('app.name', 'Clínica veterinaria');
        $ownerName = $paciente->propietario?->displayName() ?: 'cliente';
        $expiresDays = max(1, (int) ceil($ttlMinutes / 1440));

        $message = "Hola {$ownerName} 👋\n\n"
            ."{$clinicName} comparte contigo {$documentLabel} de {$paciente->nombre}.\n\n"
            ."Puedes verla en línea y descargarla desde este enlace:\n{$url}\n\n"
            ."🔒 Por seguridad, el enlace estará disponible por {$expiresDays} día(s).";

        try {
            $sender->send($tenant, $chatId, $message);

            return back()->with('success', 'El enlace del documento fue enviado por WhatsApp.');
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar historial clínico por WhatsApp', [
                ...$logContext,
                'error' => $e->getMessage(),
            ]);

            return back()->with('warning', 'No se pudo enviar por WhatsApp. Verifica la conexión e inténtalo nuevamente.');
        }
    }
}
