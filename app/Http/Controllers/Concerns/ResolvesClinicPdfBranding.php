<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ClinicSetting;
use App\Models\Paciente;
use App\Support\Pdf\SignatureImageProcessor;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

trait ResolvesClinicPdfBranding
{
    /**
     * @return array{
     *     clinicNombre: string,
     *     logoDataUri: string|null,
     *     colorPrimario: string,
     *     colorSecundario: string,
     *     clinicEmail: string|null,
     *     clinicTelefono: string|null,
     *     clinicWeb: string|null,
     *     clinicDireccion: string|null,
     *     generadoEn: string,
     *     showVetFirma: bool,
     *     vetFirmaDataUri: string|null,
     *     vetFirmaNombre: string|null,
     *     vetFirmaColegiatura: string|null
     * }
     */
    protected function clinicPdfBranding(?string $firmaDocumento = null): array
    {
        $clinic = ClinicSetting::current();
        $tz = (string) config('app.timezone', 'UTC');

        $clinicNombre = $clinic->nombre_comercial
            ?: $clinic->razon_social
            ?: (string) config('app.name', 'Clínica');

        return array_merge([
            'clinicNombre' => $clinicNombre,
            'logoDataUri' => $this->clinicLogoDataUri($clinic),
            'colorPrimario' => $this->sanitizeHexColor($clinic->color_primario, '#166534'),
            'colorSecundario' => $this->sanitizeHexColor($clinic->color_secundario, '#f0fdf4'),
            'clinicEmail' => $clinic->email_institucional,
            'clinicTelefono' => $clinic->telefono_principal,
            'clinicWeb' => $clinic->web_url,
            'clinicDireccion' => $clinic->direccion_fiscal,
            'generadoEn' => now($tz)->format('d/m/Y H:i'),
        ], $this->clinicVetFirmaVars($clinic, $firmaDocumento));
    }

    /**
     * @return array{showVetFirma: bool, vetFirmaDataUri: string|null, vetFirmaNombre: string|null, vetFirmaColegiatura: string|null}
     */
    protected function clinicVetFirmaVars(ClinicSetting $clinic, ?string $documento): array
    {
        $enabled = $documento !== null && $clinic->usaFirmaDigitalEn($documento);
        $uri = $enabled ? $this->clinicFirmaDataUri($clinic) : null;
        $nombre = $enabled ? (trim((string) ($clinic->firma_digital_nombre ?? '')) ?: null) : null;
        $colegiatura = $enabled ? (trim((string) ($clinic->firma_digital_colegiatura ?? '')) ?: null) : null;

        return [
            'showVetFirma' => $enabled && ($uri !== null || $nombre !== null),
            'vetFirmaDataUri' => $uri,
            'vetFirmaNombre' => $nombre,
            'vetFirmaColegiatura' => $colegiatura,
        ];
    }

    protected function clinicFirmaDataUri(ClinicSetting $clinic): ?string
    {
        $path = $clinic->firma_digital_path;
        if ($path === null || $path === '') {
            return null;
        }
        $path = ltrim((string) $path, '/');
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }
        $binary = Storage::disk('public')->get($path);
        if (! is_string($binary) || $binary === '') {
            return null;
        }

        try {
            $binary = app(SignatureImageProcessor::class)->toTransparentPng($binary);
            $mime = 'image/png';
        } catch (Throwable) {
            $mime = Storage::disk('public')->mimeType($path) ?? 'image/png';
            if (! is_string($mime) || ! str_starts_with($mime, 'image/')) {
                return null;
            }
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    protected function respondClinicPdf(Request $request, PDF $pdf, string $filename): Response
    {
        $pdf->setPaper('a4', 'portrait');

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    protected function clinicLogoDataUri(ClinicSetting $clinic): ?string
    {
        $path = $clinic->logo_path;
        if ($path === null || $path === '') {
            return null;
        }
        $path = ltrim((string) $path, '/');
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }
        $binary = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?? 'image/png';
        if (! is_string($mime) || ! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) $binary);
    }

    protected function sanitizeHexColor(?string $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        $v = trim($value);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $v) === 1) {
            return $v;
        }

        return $fallback;
    }

    protected function propietarioNombreParaPdf(Paciente $paciente): string
    {
        $p = $paciente->propietario;
        if ($p === null) {
            return '—';
        }
        if ($p->razon_social) {
            return trim((string) $p->razon_social);
        }

        return trim(implode(' ', array_filter([(string) $p->nombres, (string) $p->apellidos]))) ?: '—';
    }
}
