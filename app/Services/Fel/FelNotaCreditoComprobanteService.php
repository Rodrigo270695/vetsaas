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
 * Nota de crédito SUNAT vía Nubefact cuando la anulación directa no está disponible.
 */
final class FelNotaCreditoComprobanteService
{
    public function __construct(
        private readonly NubefactClient $nubefact,
    ) {}

    public function emitirPorAnulacionVenta(Venta $venta, string $motivo): void
    {
        $venta->load('felDocument');
        $documento = $venta->felDocument;

        if ($documento === null || $documento->estado !== FelDocument::ESTADO_EMITIDO) {
            throw new RuntimeException(__('caja.ventas.anulacion.sin_documento_fel'));
        }

        $clinic = ClinicSetting::current();
        $tenant = app(TenantManager::class)->current()?->tenant;

        if (! PlanCapabilities::facturaElectronica($tenant)
            || ! (bool) $clinic->emite_comprobantes_sunat
            || ! (bool) $clinic->nubefact_configurado) {
            throw new RuntimeException(__('caja.ventas.anulacion.fel_no_configurado'));
        }

        $nubefact = NubefactCredentialResolver::fromClinicSetting($clinic);

        $payload = [
            'operacion' => 'generar_nota',
            'tipo_de_nota_de_credito' => 1,
            'documento_que_se_modifica_tipo' => (string) $documento->tipo_comprobante,
            'documento_que_se_modifica_serie' => $documento->serie,
            'documento_que_se_modifica_numero' => (string) $documento->correlativo,
            'motivo' => mb_substr(trim($motivo), 0, 500) ?: 'Anulación de venta',
        ];

        try {
            $respuesta = $this->nubefact->generarComprobante($nubefact, $payload);
        } catch (RuntimeException $e) {
            throw new RuntimeException(__('caja.ventas.anulacion.nota_credito_error', [
                'detalle' => $e->getMessage(),
            ]), 0, $e);
        }

        if (! $this->nubefact->respuestaExitosa($respuesta) && ! $this->nubefact->respuestaAnulacionExitosa($respuesta)) {
            throw new RuntimeException(__('caja.ventas.anulacion.nota_credito_error', [
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
