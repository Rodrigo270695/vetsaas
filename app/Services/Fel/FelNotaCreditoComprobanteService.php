<?php

declare(strict_types=1);

namespace App\Services\Fel;

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Models\Venta;
use App\Support\Fel\ApisunatCredentialResolver;
use App\Support\Fel\FelReceptorResolver;
use App\Support\Fel\FelSerieResolver;
use App\Support\PlanCapabilities;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Nota de crédito SUNAT vía Lucode (APISUNAT) cuando la baja/resumen no aplica.
 */
final class FelNotaCreditoComprobanteService
{
    public function __construct(
        private readonly ApisunatClient $apisunat,
        private readonly FelSerieResolver $felSeries,
    ) {}

    public function emitirPorAnulacionVenta(Venta $venta, string $motivo): void
    {
        $venta->load(['felDocument', 'lineas', 'propietario', 'sede']);
        $documento = $venta->felDocument;

        if ($documento === null || $documento->estado !== FelDocument::ESTADO_EMITIDO) {
            throw new RuntimeException(__('caja.ventas.anulacion.sin_documento_fel'));
        }

        $clinic = ClinicSetting::current();
        $tenant = app(TenantManager::class)->current()?->tenant;

        if (! PlanCapabilities::facturaElectronica($tenant)
            || ! (bool) $clinic->emite_comprobantes_sunat
            || ! ApisunatCredentialResolver::estaConfigurado($clinic)) {
            throw new RuntimeException(__('caja.ventas.anulacion.fel_no_configurado'));
        }

        $credenciales = ApisunatCredentialResolver::fromClinicSetting($clinic);
        $receptor = FelReceptorResolver::datosReceptor($venta->propietario);
        $tipoAfectado = (int) $documento->tipo_comprobante;
        $docAfectadoNombre = $this->apisunat->nombreDocumentoTipo($tipoAfectado);

        DB::transaction(function () use (
            $venta,
            $documento,
            $clinic,
            $credenciales,
            $receptor,
            $motivo,
            $docAfectadoNombre,
        ): void {
            $serieNc = $this->felSeries->resolverParaVenta($venta, FelSerie::TIPO_NOTA_CREDITO, true);
            $correlativo = ((int) $serieNc->ultimo_correlativo) + 1;

            $payload = $this->apisunat->construirPayloadNotaCredito(
                $venta,
                $clinic,
                (string) $serieNc->serie,
                $correlativo,
                $receptor,
                $motivo,
                $docAfectadoNombre,
                (string) $documento->serie,
                (int) $documento->correlativo,
            );

            try {
                $respuesta = $this->apisunat->generarComprobante($credenciales, $payload);
            } catch (RuntimeException $e) {
                throw new RuntimeException(__('caja.ventas.anulacion.nota_credito_error', [
                    'detalle' => $e->getMessage(),
                ]), 0, $e);
            }

            if (! $this->apisunat->respuestaExitosa($respuesta)) {
                throw new RuntimeException(__('caja.ventas.anulacion.nota_credito_error', [
                    'detalle' => $this->apisunat->extraerMensajeError($respuesta),
                ]));
            }

            $serieNc->update(['ultimo_correlativo' => $correlativo]);

            $documento->update([
                'estado' => FelDocument::ESTADO_ANULADO,
                'anulado_at' => now(),
            ]);
            $venta->update(['fel_estado' => Venta::FEL_ANULADO]);
        });
    }
}
