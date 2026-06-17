<?php

namespace App\Services\Fel;

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Models\Tenant;
use App\Models\Venta;
use App\Support\Fel\ApisunatCredentialResolver;
use App\Support\Fel\FelReceptorResolver;
use App\Support\Fel\FelSerieResolver;
use App\Support\PlanCapabilities;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FelEmisionVentaService
{
    public function __construct(
        private readonly ApisunatClient $apisunat,
        private readonly FelSerieResolver $felSeries,
    ) {}

    public function puedeEmitir(?Tenant $tenant, ClinicSetting $clinic, Venta $venta): bool
    {
        if ($venta->estado !== Venta::ESTADO_PAGADO) {
            return false;
        }

        if (! $this->estadoPermiteEmision($venta)) {
            return false;
        }

        $tipo = $venta->tipo_comprobante_sunat;

        return FelSerie::esTipoSunat($tipo)
            && $this->planPermiteTipo($tenant, $tipo)
            && (bool) $clinic->emite_comprobantes_sunat
            && ApisunatCredentialResolver::estaConfigurado($clinic);
    }

    public function emitirPorVentaId(string $ventaId): FelDocument
    {
        $venta = Venta::query()
            ->with(['lineas', 'propietario', 'felDocument'])
            ->findOrFail($ventaId);

        return $this->emitir($venta);
    }

    public function emitir(Venta $venta): FelDocument
    {
        $clinic = ClinicSetting::current();
        $tenant = app(TenantManager::class)->current()?->tenant;

        if (! $this->puedeEmitir($tenant, $clinic, $venta)) {
            throw new RuntimeException(__('caja.ventas.fel.no_emitible'));
        }

        $credenciales = ApisunatCredentialResolver::fromClinicSetting($clinic);
        $receptor = FelReceptorResolver::datosReceptor($venta->propietario);
        $tipoComprobante = (int) $venta->tipo_comprobante_sunat;

        if ($tipoComprobante === FelSerie::TIPO_FACTURA && (int) $receptor['tipo_doc'] !== 6) {
            throw new RuntimeException(__('caja.ventas.fel.factura_requiere_ruc'));
        }

        return DB::transaction(function () use ($venta, $credenciales, $receptor, $tipoComprobante): FelDocument {
            $venta = Venta::query()->whereKey($venta->id)->lockForUpdate()->firstOrFail();

            if (! $this->estadoPermiteEmision($venta)) {
                throw new RuntimeException(__('caja.ventas.fel.ya_procesada'));
            }

            $serie = $this->felSeries->resolverParaVenta($venta, $tipoComprobante, true);

            $correlativo = ((int) $serie->ultimo_correlativo) + 1;
            $numeroCompleto = $serie->serie.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT);

            $documento = FelDocument::query()->updateOrCreate(
                ['venta_id' => $venta->id],
                [
                    'fel_serie_id' => $serie->id,
                    'tipo_comprobante' => $tipoComprobante,
                    'serie' => $serie->serie,
                    'correlativo' => $correlativo,
                    'numero_completo' => $numeroCompleto,
                    'receptor_tipo_doc' => $receptor['tipo_doc'],
                    'receptor_num_doc' => $receptor['num_doc'],
                    'receptor_nombre' => $receptor['nombre'],
                    'subtotal' => $venta->subtotal,
                    'igv_monto' => $venta->igv_monto,
                    'total' => $venta->total,
                    'moneda' => $venta->moneda,
                    'estado' => FelDocument::ESTADO_PENDIENTE,
                    'error_mensaje' => null,
                    'emitido_at' => null,
                ],
            );

            $venta->update([
                'fel_document_id' => $documento->id,
                'fel_estado' => Venta::FEL_PENDIENTE,
            ]);

            $payload = $this->apisunat->construirPayload(
                $venta,
                ClinicSetting::current(),
                $tipoComprobante,
                $serie->serie,
                $correlativo,
                $receptor,
            );

            try {
                $respuesta = $this->apisunat->generarComprobante($credenciales, $payload);
            } catch (RuntimeException $e) {
                $this->marcarRechazado($documento, $venta, $e->getMessage());

                throw $e;
            }

            if (! $this->apisunat->respuestaExitosa($respuesta)) {
                $mensaje = $this->apisunat->extraerMensajeError($respuesta);
                $this->marcarRechazado($documento, $venta, $mensaje);

                throw new RuntimeException($mensaje);
            }

            $enlaces = $this->apisunat->extraerEnlaces($respuesta);
            $estadoApisunat = strtoupper((string) (($respuesta['payload'] ?? [])['estado'] ?? ''));

            $serie->update(['ultimo_correlativo' => $correlativo]);

            $documento->update([
                'estado' => FelDocument::ESTADO_EMITIDO,
                'nubefact_id' => $estadoApisunat !== '' ? 'apisunat:'.$estadoApisunat : null,
                'url_pdf' => $enlaces['pdf'],
                'url_xml' => $enlaces['xml'],
                'url_cdr' => $enlaces['cdr'],
                'enlace_consulta' => $enlaces['consulta'],
                'error_mensaje' => null,
                'emitido_at' => now(),
            ]);

            $venta->update(['fel_estado' => Venta::FEL_EMITIDO]);

            return $documento->fresh();
        });
    }

    private function estadoPermiteEmision(Venta $venta): bool
    {
        if (in_array($venta->fel_estado, [Venta::FEL_PENDIENTE, Venta::FEL_RECHAZADO], true)) {
            return true;
        }

        return $venta->fel_estado === Venta::FEL_SIN_CPE
            && FelSerie::esTipoSunat($venta->tipo_comprobante_sunat);
    }

    private function planPermiteTipo(?Tenant $tenant, ?int $tipo): bool
    {
        return match ($tipo) {
            FelSerie::TIPO_FACTURA => PlanCapabilities::facturasElectronicas($tenant),
            FelSerie::TIPO_BOLETA => PlanCapabilities::boletasElectronicas($tenant),
            default => false,
        };
    }

    private function marcarRechazado(FelDocument $documento, Venta $venta, string $mensaje): void
    {
        $documento->update([
            'estado' => FelDocument::ESTADO_RECHAZADO,
            'error_mensaje' => mb_substr($mensaje, 0, 2000),
        ]);
        $venta->update(['fel_estado' => Venta::FEL_RECHAZADO]);
    }
}
