<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
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

        $cfg = ClinicSetting::query()->first();
        $tenant = Tenant::query()->find($tenantId);

        $clinicNombre = $cfg?->nombre_comercial
            ?: $cfg?->razon_social
            ?: $tenant?->nombre_comercial
            ?: $tenant?->razon_social
            ?: config('app.name');

        $colorPrimario = $cfg?->color_primario ?: '#0F6E56';
        $colorSecundario = $cfg?->color_secundario ?: '#F4F7F6';

        $pdf = Pdf::loadView('pdf.caja-sesion-arqueo', [
            'arqueo' => $arqueo,
            'sesion' => $sesion,
            'clinic_nombre' => $clinicNombre,
            'clinic_ruc' => $cfg?->ruc,
            'colorPrimario' => $colorPrimario,
            'colorSecundario' => $colorSecundario,
            'abierta_por' => $sesion->abiertaPor?->name,
            'cerrada_por' => $sesion->cerradaPor?->name,
        ])->setPaper('a4');

        $filename = 'arqueo-caja-'.substr((string) $sesion->getKey(), 0, 8).'.pdf';

        return $pdf->stream($filename);
    }
}
