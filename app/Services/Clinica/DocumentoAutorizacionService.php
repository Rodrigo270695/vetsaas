<?php

declare(strict_types=1);

namespace App\Services\Clinica;

use App\Http\Controllers\Concerns\ResolvesClinicPdfBranding;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\DocumentoAutorizacionEnvio;
use App\Models\DocumentoAutorizacionPlantilla;
use App\Models\Tenant;
use App\Notifications\Clinica\DocumentoAutorizacionMailNotification;
use App\Support\Clinica\DocumentoAutorizacionRenderer;
use App\Support\WhatsApp\WhatsAppChatId;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class DocumentoAutorizacionService
{
    use ResolvesClinicPdfBranding;

    public function __construct(
        private readonly ClinicalHistoryWhatsAppSender $whatsApp,
    ) {}

    /**
     * @return array{envio: DocumentoAutorizacionEnvio, whatsapp_ok: bool, email_ok: bool, warnings: list<string>}
     */
    public function emitir(
        Consulta $consulta,
        DocumentoAutorizacionPlantilla $plantilla,
        Tenant $tenant,
        ?string $telefono,
        ?string $email,
        bool $enviarWhatsapp,
        bool $enviarEmail,
        ?string $userId,
    ): array {
        $consulta->loadMissing([
            'historiaClinica.paciente.propietario',
            'veterinario:id,name',
        ]);
        $paciente = $consulta->historiaClinica?->paciente;
        if ($paciente === null) {
            throw new RuntimeException('La consulta no tiene paciente.');
        }

        $owner = $paciente->propietario;
        $cuerpo = DocumentoAutorizacionRenderer::renderPlantilla($plantilla, $consulta, $paciente, $owner);
        $ttlMinutes = max(5, (int) config('clinic-documents.public_link_ttl_minutes', 10080));
        $token = Str::lower(Str::random(48));

        $envio = DocumentoAutorizacionEnvio::query()->create([
            'plantilla_id' => $plantilla->id,
            'consulta_id' => $consulta->id,
            'paciente_id' => $paciente->id,
            'propietario_id' => $owner?->id,
            'titulo' => $plantilla->nombre,
            'cuerpo_snapshot' => $cuerpo,
            'token' => $token,
            'estado' => DocumentoAutorizacionEnvio::ESTADO_PENDIENTE,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'created_by_id' => $userId,
        ]);

        $url = route('tenant.public.autorizacion.show', [
            'tenant_subdomain' => $tenant->slug,
            'token' => $token,
        ]);

        $clinic = ClinicSetting::current();
        $clinicName = trim((string) ($clinic->nombre_comercial ?: $clinic->razon_social))
            ?: (string) config('app.name', 'Clínica veterinaria');
        $ownerName = $owner?->displayName() ?: 'cliente';
        $expiresDays = max(1, (int) ceil($ttlMinutes / 1440));

        $warnings = [];
        $whatsappOk = false;
        $emailOk = false;

        if ($enviarWhatsapp) {
            $phone = trim((string) $telefono) !== '' ? (string) $telefono : $owner?->telefono;
            $chatId = WhatsAppChatId::fromPhone($phone);
            if ($chatId === null) {
                $warnings[] = 'No hay un WhatsApp válido para el titular.';
            } else {
                $message = "Hola {$ownerName} 👋\n\n"
                    ."{$clinicName} te pide leer y firmar: {$plantilla->nombre} (paciente {$paciente->nombre}).\n\n"
                    ."Ábrelo en tu celular:\n{$url}\n\n"
                    ."🔒 El enlace estará disponible por {$expiresDays} día(s).";
                try {
                    $this->whatsApp->send($tenant, $chatId, $message);
                    $whatsappOk = true;
                } catch (Throwable $e) {
                    report($e);
                    $warnings[] = 'No se pudo enviar por WhatsApp. Verifica la conexión.';
                }
            }
        }

        if ($enviarEmail) {
            $mailTo = trim((string) $email) !== '' ? trim((string) $email) : trim((string) ($owner?->email ?? ''));
            if ($mailTo === '' || ! filter_var($mailTo, FILTER_VALIDATE_EMAIL)) {
                $warnings[] = 'No hay un correo válido para el titular.';
            } else {
                try {
                    Notification::route('mail', $mailTo)->notify(
                        new DocumentoAutorizacionMailNotification(
                            $clinicName,
                            $ownerName,
                            $paciente->nombre,
                            $plantilla->nombre,
                            $url,
                            $expiresDays,
                        ),
                    );
                    $emailOk = true;
                } catch (Throwable $e) {
                    report($e);
                    $warnings[] = 'No se pudo enviar el correo.';
                }
            }
        }

        $envio->update([
            'enviado_whatsapp' => $whatsappOk,
            'enviado_email' => $emailOk,
        ]);

        return [
            'envio' => $envio->fresh(),
            'whatsapp_ok' => $whatsappOk,
            'email_ok' => $emailOk,
            'warnings' => $warnings,
        ];
    }

    public function firmar(
        DocumentoAutorizacionEnvio $envio,
        string $firmaDataUri,
        string $firmanteNombre,
        ?string $firmanteDocumento,
        ?string $ip,
    ): DocumentoAutorizacionEnvio {
        if (! $envio->isPending()) {
            throw new RuntimeException('Este documento ya no se puede firmar.');
        }

        $png = $this->decodePngDataUri($firmaDataUri);
        $slug = current_tenant()?->slug ?? 'tenant';
        $base = 'tenants/'.$slug.'/autorizaciones/'.$envio->id;
        $firmaPath = $base.'-firma.png';
        Storage::disk('public')->put($firmaPath, $png);

        $envio->forceFill([
            'firma_path' => $firmaPath,
            'firmante_nombre' => $firmanteNombre,
            'firmante_documento' => $firmanteDocumento,
            'ip' => $ip,
            'firmado_at' => now(),
            'estado' => DocumentoAutorizacionEnvio::ESTADO_FIRMADO,
        ])->save();

        $pdfPath = $base.'.pdf';
        $binary = $this->renderPdf($envio->fresh() ?? $envio);
        Storage::disk('public')->put($pdfPath, $binary);
        $envio->update(['pdf_path' => $pdfPath]);

        return $envio->fresh() ?? $envio;
    }

    public function renderPdf(DocumentoAutorizacionEnvio $envio): string
    {
        $envio->loadMissing(['paciente.propietario', 'consulta']);
        $branding = $this->clinicPdfBranding();
        $firmaDataUri = null;
        if (is_string($envio->firma_path) && Storage::disk('public')->exists($envio->firma_path)) {
            $bin = Storage::disk('public')->get($envio->firma_path);
            $firmaDataUri = 'data:image/png;base64,'.base64_encode((string) $bin);
        }

        $pdf = Pdf::loadView('pdf.documento-autorizacion', [
            ...$branding,
            'envio' => $envio,
            'paciente' => $envio->paciente,
            'propietarioNombre' => $envio->paciente
                ? $this->propietarioNombreParaPdf($envio->paciente)
                : '—',
            'cuerpoHtml' => DocumentoAutorizacionRenderer::toSafeHtml($envio->cuerpo_snapshot),
            'firmaDataUri' => $firmaDataUri,
        ]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    private function decodePngDataUri(string $dataUri): string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', trim($dataUri), $m)) {
            throw new RuntimeException('La firma no es válida.');
        }
        $raw = base64_decode($m[1], true);
        if ($raw === false || strlen($raw) < 80 || strlen($raw) > 400_000) {
            throw new RuntimeException('La firma no es válida.');
        }

        return $raw;
    }
}
