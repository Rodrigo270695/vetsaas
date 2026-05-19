<?php

namespace App\Services\Fel;

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Models\FelSerie;
use App\Models\Tenant;
use App\Models\Venta;
use App\Models\VentaLinea;
use App\Support\Fel\FelReceptorResolver;
use App\Support\Fel\FelSerieResolver;
use App\Support\Fel\NubefactCredentialResolver;
use App\Support\PlanCapabilities;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FelEmisionVentaService
{
    public function __construct(
        private readonly NubefactClient $nubefact,
        private readonly FelSerieResolver $felSeries,
    ) {}

    public function puedeEmitir(?Tenant $tenant, ClinicSetting $clinic, Venta $venta): bool
    {
        if ($venta->estado !== Venta::ESTADO_PAGADO) {
            return false;
        }

        if (! in_array($venta->fel_estado, [Venta::FEL_PENDIENTE, Venta::FEL_RECHAZADO], true)) {
            return false;
        }

        return PlanCapabilities::facturaElectronica($tenant)
            && (bool) $clinic->emite_comprobantes_sunat
            && (bool) $clinic->nubefact_configurado;
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

        $nubefact = NubefactCredentialResolver::fromClinicSetting($clinic);
        $receptor = FelReceptorResolver::datosReceptor($venta->propietario);
        $tipoComprobante = $venta->tipoComprobanteSunat();

        return DB::transaction(function () use ($venta, $clinic, $nubefact, $receptor, $tipoComprobante): FelDocument {
            $venta = Venta::query()->whereKey($venta->id)->lockForUpdate()->firstOrFail();

            if (! in_array($venta->fel_estado, [Venta::FEL_PENDIENTE, Venta::FEL_RECHAZADO], true)) {
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

            $payload = $this->construirPayloadNubefact(
                $venta,
                $clinic,
                $tipoComprobante,
                $serie->serie,
                $correlativo,
                $receptor,
            );

            try {
                $respuesta = $this->nubefact->generarComprobante($nubefact, $payload);
            } catch (RuntimeException $e) {
                $this->marcarRechazado($documento, $venta, $e->getMessage());

                throw $e;
            }

            if (! $this->nubefact->respuestaExitosa($respuesta)) {
                $mensaje = $this->nubefact->extraerMensajeError($respuesta);
                $this->marcarRechazado($documento, $venta, $mensaje);

                throw new RuntimeException($mensaje);
            }

            $serie->update(['ultimo_correlativo' => $correlativo]);

            $documento->update([
                'estado' => FelDocument::ESTADO_EMITIDO,
                'nubefact_id' => is_string($respuesta['codigo_unico'] ?? null)
                    ? $respuesta['codigo_unico']
                    : (is_string($respuesta['id'] ?? null) ? $respuesta['id'] : null),
                'url_pdf' => $this->primerEnlace($respuesta, ['enlace_del_pdf', 'enlace_del_pdf2']),
                'url_xml' => $this->primerEnlace($respuesta, ['enlace_del_xml', 'enlace_del_xml2']),
                'url_cdr' => $this->primerEnlace($respuesta, ['enlace_del_cdr', 'enlace_del_cdr2']),
                'enlace_consulta' => $this->primerEnlace($respuesta, ['enlace', 'enlace_del_pdf']),
                'error_mensaje' => null,
                'emitido_at' => now(),
            ]);

            $venta->update(['fel_estado' => Venta::FEL_EMITIDO]);

            return $documento->fresh();
        });
    }

    /**
     * @param  array{
     *     tipo_doc: int,
     *     num_doc: string,
     *     nombre: string,
     * }  $receptor
     * @return array<string, mixed>
     */
    private function construirPayloadNubefact(
        Venta $venta,
        ClinicSetting $clinic,
        int $tipoComprobante,
        string $serie,
        int $correlativo,
        array $receptor,
    ): array {
        $igvPct = number_format((float) $clinic->igv_porcentaje, 2, '.', '');
        // Nubefact exige la fecha del día de emisión (no la del cobro histórico).
        $fecha = now(config('app.timezone'))->format('d-m-Y');

        $items = $venta->lineas->map(function (VentaLinea $ln) use ($clinic, $igvPct): array {
            $cantidad = (float) (string) $ln->cantidad;
            $subtotal = round((float) (string) $ln->subtotal, 2);
            $igvLinea = round($subtotal * ((float) $igvPct / 100), 2);
            $valorUnit = $cantidad > 0 ? round($subtotal / $cantidad, 2) : 0.0;
            $precioUnit = round($valorUnit + ($cantidad > 0 ? $igvLinea / $cantidad : 0), 2);

            return [
                'unidad_de_medida' => 'NIU',
                'codigo' => '001',
                'descripcion' => mb_substr($ln->descripcion_snapshot, 0, 250),
                'cantidad' => number_format($cantidad, 2, '.', ''),
                'valor_unitario' => number_format($valorUnit, 2, '.', ''),
                'precio_unitario' => number_format($precioUnit, 2, '.', ''),
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'tipo_de_igv' => 1,
                'igv' => number_format($igvLinea, 2, '.', ''),
                'total' => number_format($subtotal + $igvLinea, 2, '.', ''),
            ];
        })->values()->all();

        return [
            'operacion' => 'generar_comprobante',
            'tipo_de_comprobante' => (string) $tipoComprobante,
            'serie' => $serie,
            'numero' => (string) $correlativo,
            'sunat_transaction' => '1',
            'cliente_tipo_de_documento' => (string) $receptor['tipo_doc'],
            'cliente_numero_de_documento' => $receptor['num_doc'],
            'cliente_denominacion' => $receptor['nombre'],
            'cliente_direccion' => mb_substr((string) ($venta->propietario?->direccion ?? '-'), 0, 250) ?: '-',
            'cliente_email' => $venta->propietario?->email,
            'fecha_de_emision' => $fecha,
            'moneda' => $venta->moneda === 'USD' ? '2' : '1',
            'porcentaje_de_igv' => $igvPct,
            'total_gravada' => number_format((float) (string) $venta->subtotal, 2, '.', ''),
            'total_igv' => number_format((float) (string) $venta->igv_monto, 2, '.', ''),
            'total' => number_format((float) (string) $venta->total, 2, '.', ''),
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $respuesta
     * @param  list<string>  $keys
     */
    private function primerEnlace(array $respuesta, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($respuesta[$key]) && is_string($respuesta[$key]) && $respuesta[$key] !== '') {
                return $respuesta[$key];
            }
        }

        return null;
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
