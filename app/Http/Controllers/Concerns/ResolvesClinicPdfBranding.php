<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ClinicSetting;
use App\Models\Paciente;
use Illuminate\Support\Facades\Storage;

trait ResolvesClinicPdfBranding
{
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
