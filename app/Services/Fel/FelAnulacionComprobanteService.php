<?php

declare(strict_types=1);

namespace App\Services\Fel;

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Models\Venta;
use App\Support\Fel\ApisunatCredentialResolver;
use App\Support\PlanCapabilities;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Anulación del comprobante electrónico en SUNAT vía Lucode (APISUNAT v3).
 *
 * - Factura → `POST /api/v3/voided` (comunicación de baja)
 * - Boleta → `POST /api/v3/daily-summary` (resumen diario / anular)
 */
final class FelAnulacionComprobanteService
{
    public function __construct(
        private readonly ApisunatClient $apisunat,
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
            || ! ApisunatCredentialResolver::estaConfigurado($clinic)) {
            throw new RuntimeException(__('caja.ventas.anulacion.fel_no_configurado'));
        }

        $documento = $venta->felDocument;
        if ($documento === null) {
            throw new RuntimeException(__('caja.ventas.anulacion.sin_documento_fel'));
        }

        $credenciales = ApisunatCredentialResolver::fromClinicSetting($clinic);
        $tipo = (int) $documento->tipo_comprobante;
        $serie = (string) $documento->serie;
        $numero = (int) $documento->correlativo;
        $motivo = 'ANULACIÓN DE OPERACIÓN';

        try {
            $respuesta = match ($tipo) {
                FelSerie::TIPO_FACTURA => $this->apisunat->comunicarBaja(
                    $credenciales,
                    'factura',
                    $serie,
                    $numero,
                    $motivo,
                ),
                FelSerie::TIPO_BOLETA => $this->apisunat->anularBoletaResumen(
                    $credenciales,
                    $serie,
                    $numero,
                ),
                default => throw new RuntimeException(__('caja.ventas.anulacion.tipo_no_anulable')),
            };
        } catch (RuntimeException $e) {
            throw new RuntimeException(__('caja.ventas.anulacion.fel_error', [
                'detalle' => $e->getMessage(),
            ]), 0, $e);
        }

        if (! $this->apisunat->respuestaExitosa($respuesta)) {
            throw new RuntimeException(__('caja.ventas.anulacion.fel_error', [
                'detalle' => $this->apisunat->extraerMensajeError($respuesta),
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
