<?php

namespace App\Services\Fel;

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Models\Tenant;
use App\Models\Venta;
use App\Support\Fel\ApisunatCredentialResolver;
use App\Support\Fel\FelFechaEmision;
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

        $reservado = $this->documentoReservado($venta);
        if ($reservado instanceof FelDocument) {
            return $this->confirmarEnLucode($venta, $reservado, $clinic, recienReservado: false);
        }

        if (! $this->puedeEmitir($tenant, $clinic, $venta)) {
            throw new RuntimeException(__('caja.ventas.fel.no_emitible'));
        }

        $receptor = FelReceptorResolver::datosReceptor($venta->propietario);
        $tipoComprobante = (int) $venta->tipo_comprobante_sunat;

        if ($tipoComprobante === FelSerie::TIPO_FACTURA && (int) $receptor['tipo_doc'] !== 6) {
            throw new RuntimeException(__('caja.ventas.fel.factura_requiere_ruc'));
        }

        $documento = $this->reservarCorrelativo($venta, $clinic, $receptor, $tipoComprobante);

        return $this->confirmarEnLucode($venta->fresh(), $documento, $clinic, recienReservado: true);
    }

    /**
     * Enlaza una venta con un comprobante que Lucode ya aceptó y VetSaaS no registró.
     * No vuelve a emitir: solo consulta ese serie/número y lo guarda en la venta.
     */
    public function enlazarComprobanteYaEnLucode(Venta $venta, string $serieCodigo, int $correlativo): FelDocument
    {
        $clinic = ClinicSetting::current();
        $credenciales = ApisunatCredentialResolver::fromClinicSetting($clinic);
        $serieCodigo = strtoupper(trim($serieCodigo));
        $tipoComprobante = (int) $venta->tipo_comprobante_sunat;

        if (! FelSerie::esTipoSunat($tipoComprobante)) {
            throw new RuntimeException('La venta no es boleta ni factura.');
        }

        $ocupado = FelDocument::query()
            ->where('serie', $serieCodigo)
            ->where('correlativo', $correlativo)
            ->where('venta_id', '!=', $venta->id)
            ->exists();
        if ($ocupado) {
            throw new RuntimeException('Ese correlativo ya está asignado a otra venta.');
        }

        $propio = FelDocument::query()->where('venta_id', $venta->id)->first();
        if ($propio !== null && (int) $propio->correlativo !== $correlativo) {
            throw new RuntimeException('La venta ya tiene otro comprobante ('.$propio->numero_completo.').');
        }

        $nombre = $this->apisunat->nombreDocumentoTipo($tipoComprobante);
        $consulta = $this->apisunat->consultarEstado($credenciales, $nombre, $serieCodigo, $correlativo);
        $estado = $this->apisunat->extraerEstado($consulta);
        if (! in_array($estado, ['ACEPTADO', 'PENDIENTE'], true)) {
            throw new RuntimeException(
                'Lucode no tiene '. $serieCodigo.'-'.$correlativo.' como aceptado o pendiente.'
                .($estado !== null ? ' Estado: '.$estado.'.' : ''),
            );
        }

        $receptor = FelReceptorResolver::datosReceptor($venta->propietario);
        $documento = DB::transaction(function () use ($venta, $serieCodigo, $correlativo, $tipoComprobante, $receptor, $credenciales): FelDocument {
            $venta = Venta::query()->whereKey($venta->id)->lockForUpdate()->firstOrFail();
            $serie = FelSerie::query()
                ->where('serie', $serieCodigo)
                ->where('tipo_comprobante', $tipoComprobante)
                ->where('sede_id', $venta->sede_id)
                ->lockForUpdate()
                ->first();

            if ($serie === null) {
                throw new RuntimeException('No existe la serie '.$serieCodigo.' en la sede de la venta.');
            }

            $numeroCompleto = $serieCodigo.'-'.str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT);
            $documento = FelDocument::query()->updateOrCreate(
                ['venta_id' => $venta->id],
                [
                    'fel_serie_id' => $serie->id,
                    'tipo_comprobante' => $tipoComprobante,
                    'serie' => $serieCodigo,
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
                    'apisunat_mode' => $credenciales['mode'],
                ],
            );

            $venta->update([
                'fel_document_id' => $documento->id,
                'fel_estado' => Venta::FEL_PENDIENTE,
            ]);

            if ((int) $serie->ultimo_correlativo < $correlativo) {
                $serie->update(['ultimo_correlativo' => $correlativo]);
            }

            return $documento;
        });

        return $this->confirmarEnLucode($venta->fresh(), $documento->fresh(), $clinic, recienReservado: false);
    }

    /**
     * @param  array{tipo_doc: int, num_doc: string, nombre: string}  $receptor
     */
    private function reservarCorrelativo(Venta $venta, ClinicSetting $clinic, array $receptor, int $tipoComprobante): FelDocument
    {
        $emisionModo = ApisunatCredentialResolver::fromClinicSetting($clinic)['mode'];

        return DB::transaction(function () use ($venta, $receptor, $tipoComprobante, $emisionModo): FelDocument {
            $venta = Venta::query()->whereKey($venta->id)->lockForUpdate()->firstOrFail();

            $ya = $this->documentoReservado($venta);
            if ($ya instanceof FelDocument) {
                return $ya;
            }

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
                    'apisunat_mode' => $emisionModo,
                ],
            );

            $venta->update([
                'fel_document_id' => $documento->id,
                'fel_estado' => Venta::FEL_PENDIENTE,
            ]);
            $serie->update(['ultimo_correlativo' => $correlativo]);

            return $documento;
        });
    }

    private function confirmarEnLucode(Venta $venta, FelDocument $documento, ClinicSetting $clinic, bool $recienReservado): FelDocument
    {
        $credenciales = ApisunatCredentialResolver::fromClinicSetting($clinic);
        $emisionModo = $credenciales['mode'];
        $nombre = $this->apisunat->nombreDocumentoTipo((int) $documento->tipo_comprobante);

        if (! $recienReservado) {
            $consulta = $this->apisunat->consultarEstado(
                $credenciales,
                $nombre,
                (string) $documento->serie,
                (int) $documento->correlativo,
            );
            $estado = $this->apisunat->extraerEstado($consulta);
            if (in_array($estado, ['ACEPTADO', 'PENDIENTE'], true)) {
                return $this->aplicarRespuestaLucode($venta, $documento, $consulta, $emisionModo);
            }
        }

        $receptor = FelReceptorResolver::datosReceptor($venta->propietario);
        $fechaEmision = FelFechaEmision::instanteEmision(
            (int) $documento->tipo_comprobante,
            $venta->fecha_emision_sunat,
        );
        $payload = $this->apisunat->construirPayload(
            $venta,
            $clinic,
            (int) $documento->tipo_comprobante,
            (string) $documento->serie,
            (int) $documento->correlativo,
            $receptor,
            $fechaEmision,
        );

        try {
            $respuesta = $this->apisunat->generarComprobante($credenciales, $payload);
        } catch (RuntimeException $e) {
            // El número ya quedó guardado. Un reintento consulta Lucode y no pide otro correlativo.
            throw $e;
        }

        if (! $this->apisunat->respuestaExitosa($respuesta)) {
            $mensaje = $this->apisunat->extraerMensajeError($respuesta);
            $this->marcarRechazado($documento, $venta, $mensaje, $emisionModo);

            throw new RuntimeException($mensaje);
        }

        $estadoApisunat = strtoupper((string) (($respuesta['payload'] ?? [])['estado'] ?? ''));
        if (in_array($estadoApisunat, ['RECHAZADO', 'EXCEPCION'], true)) {
            $mensaje = $this->apisunat->extraerMensajeError($respuesta);
            $this->marcarRechazado($documento, $venta, $mensaje, $emisionModo);

            throw new RuntimeException($mensaje !== '' ? $mensaje : 'APISUNAT rechazó el comprobante.');
        }

        return $this->aplicarRespuestaLucode($venta, $documento, $respuesta, $emisionModo);
    }

    /**
     * @param  array<string, mixed>  $respuesta
     */
    private function aplicarRespuestaLucode(Venta $venta, FelDocument $documento, array $respuesta, string $emisionModo): FelDocument
    {
        $enlaces = $this->apisunat->extraerEnlaces($respuesta);
        $estadoApisunat = strtoupper((string) (($respuesta['payload'] ?? [])['estado'] ?? ''));
        $mapped = match ($estadoApisunat) {
            'ACEPTADO' => [
                'doc' => FelDocument::ESTADO_EMITIDO,
                'venta' => Venta::FEL_EMITIDO,
            ],
            default => [
                'doc' => FelDocument::ESTADO_PENDIENTE,
                'venta' => Venta::FEL_PENDIENTE,
            ],
        };

        $documento->update([
            'estado' => $mapped['doc'],
            'nubefact_id' => $estadoApisunat !== '' ? 'apisunat:'.$estadoApisunat : null,
            'url_pdf' => $enlaces['pdf'],
            'url_xml' => $enlaces['xml'],
            'url_cdr' => $enlaces['cdr'],
            'enlace_consulta' => $enlaces['consulta'],
            'apisunat_payload' => $respuesta,
            'apisunat_mode' => $emisionModo,
            'error_mensaje' => null,
            'emitido_at' => $mapped['doc'] === FelDocument::ESTADO_EMITIDO ? ($documento->emitido_at ?? now()) : null,
        ]);

        $venta->update(['fel_estado' => $mapped['venta']]);

        return $documento->fresh();
    }

    private function documentoReservado(Venta $venta): ?FelDocument
    {
        if ($venta->fel_estado !== Venta::FEL_PENDIENTE) {
            return null;
        }

        $doc = $venta->relationLoaded('felDocument')
            ? $venta->felDocument
            : FelDocument::query()->where('venta_id', $venta->id)->first();

        if ($doc !== null && filled($doc->serie) && $doc->correlativo !== null) {
            return $doc;
        }

        return null;
    }

    private function estadoPermiteEmision(Venta $venta): bool
    {
        if ($venta->fel_estado === Venta::FEL_RECHAZADO) {
            return true;
        }

        if ($venta->fel_estado === Venta::FEL_PENDIENTE) {
            // Ya enviado a Lucode (serie/correlativo): esperar sync SUNAT, no reemitir.
            $doc = $venta->relationLoaded('felDocument')
                ? $venta->felDocument
                : FelDocument::query()->where('venta_id', $venta->id)->first();

            if ($doc !== null && filled($doc->serie) && $doc->correlativo !== null) {
                return false;
            }

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

    private function marcarRechazado(FelDocument $documento, Venta $venta, string $mensaje, ?string $emisionModo = null): void
    {
        $documento->update([
            'estado' => FelDocument::ESTADO_RECHAZADO,
            'error_mensaje' => mb_substr($mensaje, 0, 2000),
            'apisunat_mode' => in_array($emisionModo, ['sandbox', 'produccion'], true) ? $emisionModo : $documento->apisunat_mode,
        ]);
        $venta->update(['fel_estado' => Venta::FEL_RECHAZADO]);
    }
}
