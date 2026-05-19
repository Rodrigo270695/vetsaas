<?php

declare(strict_types=1);

namespace App\Services\Fel;

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Models\Venta;
use App\Support\Fel\NubefactCredentialResolver;
use App\Support\PlanCapabilities;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Anulación del comprobante electrónico en SUNAT vía Nubefact (`generar_anulacion`).
 */
final class FelAnulacionComprobanteService
{
    public function __construct(
        private readonly NubefactClient $nubefact,
    ) {}

    public function requiereAnulacionSunat(Venta $venta): bool
    {
        return $venta->fel_estado === Venta::FEL_EMITIDO
            && $venta->felDocument !== null
            && $venta->felDocument->estado === FelDocument::ESTADO_EMITIDO;
    }

    public function anularEnSunat(Venta $venta): void
    {
        if (! $this->requiereAnulacionSunat($venta)) {
            return;
        }

        $clinic = ClinicSetting::current();
        $tenant = app(TenantManager::class)->current()?->tenant;

        if (! PlanCapabilities::facturaElectronica($tenant)
            || ! (bool) $clinic->emite_comprobantes_sunat
            || ! (bool) $clinic->nubefact_configurado) {
            throw new RuntimeException(__('caja.ventas.anulacion.fel_no_configurado'));
        }

        $documento = $venta->felDocument;
        if ($documento === null) {
            throw new RuntimeException(__('caja.ventas.anulacion.sin_documento_fel'));
        }

        $nubefact = NubefactCredentialResolver::fromClinicSetting($clinic);

        $payload = [
            'operacion' => 'generar_anulacion',
            'tipo_de_comprobante' => (string) $documento->tipo_comprobante,
            'serie' => $documento->serie,
            'numero' => (string) $documento->correlativo,
        ];

        try {
            $respuesta = $this->nubefact->generarComprobante($nubefact, $payload);
        } catch (RuntimeException $e) {
            throw new RuntimeException(__('caja.ventas.anulacion.fel_error', [
                'detalle' => $e->getMessage(),
            ]), 0, $e);
        }

        if (! $this->nubefact->respuestaExitosa($respuesta) && ! $this->nubefact->respuestaAnulacionExitosa($respuesta)) {
            throw new RuntimeException(__('caja.ventas.anulacion.fel_error', [
                'detalle' => $this->nubefact->extraerMensajeError($respuesta),
            ]));
        }

        DB::transaction(function () use ($documento, $venta): void {
            $documento->update([
                'estado' => FelDocument::ESTADO_ANULADO,
                'anulado_at' => now(),
            ]);
            $venta->update(['fel_estado' => Venta::FEL_ANULADO]);
        });
    }
}
