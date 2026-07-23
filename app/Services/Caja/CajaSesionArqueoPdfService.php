<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF imprimible del arqueo de una sesión de caja.
 */
final class CajaSesionArqueoPdfService
{
    public function __construct(
        private readonly CajaSesionArqueoService $arqueoService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $arqueo
     */
    public function stream(CajaSesion $sesion, string $tenantId, ?array $arqueo = null): Response|StreamedResponse
    {
        $arqueo ??= is_array($sesion->arqueo_json) && $sesion->arqueo_json !== []
            ? $sesion->arqueo_json
            : $this->arqueoService->build($sesion, $sesion->saldo_cierre_efectivo !== null
                ? (string) $sesion->saldo_cierre_efectivo
                : null);

        // Si el snapshot es antiguo (sin ventas detalle / rubros), recalcular en vivo.
        if (! isset($arqueo['ventas']) || ! is_array($arqueo['ventas'])
            || ! isset($arqueo['productos_total'])
            || ! isset($arqueo['servicios_total'])) {
            $arqueo = $this->arqueoService->build(
                $sesion,
                $sesion->saldo_cierre_efectivo !== null
                    ? (string) $sesion->saldo_cierre_efectivo
                    : null,
            );
        }

        $cfg = ClinicSetting::query()->first();
        $tenant = Tenant::query()->find($tenantId);

        $clinicNombre = $cfg?->nombre_comercial
            ?: $cfg?->razon_social
            ?: $tenant?->nombre_comercial
            ?: $tenant?->razon_social
            ?: config('app.name');

        $colorPrimario = $this->sanitizeHex($cfg?->color_primario, '#0F6E56');
        $colorSecundario = $this->sanitizeHex($cfg?->color_secundario, '#E8F2EF');
        $branding = $this->resolveContrastColors($colorPrimario, $colorSecundario);

        $pdf = Pdf::loadView('pdf.caja-sesion-arqueo', [
            'arqueo' => $arqueo,
            'sesion' => $sesion,
            'clinic_nombre' => $clinicNombre,
            'clinic_ruc' => $cfg?->ruc,
            'clinic_direccion' => $cfg?->direccion_fiscal,
            'clinic_telefono' => $cfg?->telefono_principal,
            'logoDataUri' => $this->resolveLogoDataUri($cfg),
            'colorPrimario' => $branding['primary'],
            'colorSecundario' => $branding['secondary'],
            'colorOnPrimary' => $branding['on_primary'],
            'colorText' => $branding['text'],
            'colorMuted' => $branding['muted'],
            'abierta_por' => $sesion->abiertaPor?->name,
            'cerrada_por' => $sesion->cerradaPor?->name,
            'notas_cierre' => $sesion->notas,
        ])->setPaper('a4');

        $filename = 'arqueo-caja-'.substr((string) $sesion->getKey(), 0, 8).'.pdf';

        return $pdf->stream($filename);
    }

    private function resolveLogoDataUri(?ClinicSetting $clinic): string
    {
        if ($clinic !== null) {
            $path = $clinic->logo_path;
            if (is_string($path) && $path !== '') {
                $path = ltrim($path, '/');
                if (Storage::disk('public')->exists($path)) {
                    $binary = Storage::disk('public')->get($path);
                    $mime = Storage::disk('public')->mimeType($path) ?? 'image/png';
                    if (is_string($mime) && str_starts_with($mime, 'image/')) {
                        return 'data:'.$mime.';base64,'.base64_encode((string) $binary);
                    }
                }
            }
        }

        $fallback = public_path('logo.png');
        if (is_file($fallback)) {
            $binary = (string) file_get_contents($fallback);

            return 'data:image/png;base64,'.base64_encode($binary);
        }

        return '';
    }

    /**
     * Garantiza contraste legible aunque el tenant configure colores claros u oscuros.
     *
     * @return array{primary: string, secondary: string, on_primary: string, text: string, muted: string}
     */
    private function resolveContrastColors(string $primary, string $secondary): array
    {
        $onPrimary = $this->relativeLuminance($primary) > 0.55 ? '#111827' : '#FFFFFF';

        // Fondo de bloques: forzar tono claro para texto oscuro.
        if ($this->relativeLuminance($secondary) < 0.72) {
            $secondary = $this->mixWithWhite($primary, 0.88);
        }

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'on_primary' => $onPrimary,
            'text' => '#111827',
            'muted' => '#4B5563',
        ];
    }

    private function sanitizeHex(?string $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $v = trim($value);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $v) === 1) {
            return strtoupper($v);
        }

        return $fallback;
    }

    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return 0.0;
        }

        $channels = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];

        $linear = array_map(static function (float $c): float {
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    private function mixWithWhite(string $hex, float $whiteRatio): string
    {
        $hex = ltrim($hex, '#');
        $r = (int) hexdec(substr($hex, 0, 2));
        $g = (int) hexdec(substr($hex, 2, 2));
        $b = (int) hexdec(substr($hex, 4, 2));
        $w = max(0.0, min(1.0, $whiteRatio));

        $nr = (int) round($r + (255 - $r) * $w);
        $ng = (int) round($g + (255 - $g) * $w);
        $nb = (int) round($b + (255 - $b) * $w);

        return sprintf('#%02X%02X%02X', $nr, $ng, $nb);
    }
}
