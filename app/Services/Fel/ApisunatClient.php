<?php

namespace App\Services\Fel;

use App\Models\ClinicSetting;
use App\Models\FelSerie;
use App\Models\Venta;
use App\Models\VentaLinea;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Cliente HTTP para APISUNAT (Lucode PSE) — API v3.
 *
 * @see https://docs.apisunat.pe/integracion/facturacion-electronica
 */
final class ApisunatClient
{
    private const PROD_URL = 'https://app.apisunat.pe/api/v3/documents';

    private const SANDBOX_URL = 'https://sandbox.apisunat.pe/api/v3/documents';

    private const DOC_NOMBRES = [
        FelSerie::TIPO_FACTURA => 'factura',
        FelSerie::TIPO_BOLETA => 'boleta',
    ];

    /**
     * @param  array{token: string, mode: 'sandbox'|'produccion'}  $credenciales
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generarComprobante(array $credenciales, array $payload): array
    {
        $url = $credenciales['mode'] === 'produccion' ? self::PROD_URL : self::SANDBOX_URL;

        try {
            $response = Http::withToken($credenciales['token'])
                ->timeout(45)
                ->acceptJson()
                ->post($url, $payload);

            $json = $response->json();

            if (! is_array($json)) {
                throw new RuntimeException('APISUNAT no devolvió JSON válido.');
            }

            $json['_http_status'] = $response->status();

            return $json;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException('Error de conexión con APISUNAT: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array{
     *     tipo_doc: int,
     *     num_doc: string,
     *     nombre: string,
     * }  $receptor
     * @return array<string, mixed>
     */
    public function construirPayload(
        Venta $venta,
        ClinicSetting $clinic,
        int $tipoComprobante,
        string $serie,
        int $correlativo,
        array $receptor,
    ): array {
        $docName = self::DOC_NOMBRES[$tipoComprobante] ?? 'boleta';
        $igvPct = number_format((float) $clinic->igv_porcentaje, 0, '.', '');
        $fecha = now(config('app.timezone'));

        $items = $venta->lineas->map(function (VentaLinea $ln) use ($igvPct): array {
            $cantidad = (float) (string) $ln->cantidad;
            $subtotal = round((float) (string) $ln->subtotal, 2);
            $valorUnit = $cantidad > 0 ? round($subtotal / $cantidad, 6) : 0.0;

            return [
                'unidad_de_medida' => 'NIU',
                'descripcion' => mb_substr($ln->descripcion_snapshot, 0, 250),
                'cantidad' => number_format($cantidad, 6, '.', ''),
                'valor_unitario' => number_format($valorUnit, 6, '.', ''),
                'porcentaje_igv' => $igvPct,
                'codigo_tipo_afectacion_igv' => '10',
                'nombre_tributo' => 'IGV',
            ];
        })->values()->all();

        return [
            'documento' => $docName,
            'serie' => $serie,
            'numero' => $correlativo,
            'fecha_de_emision' => $fecha->format('Y-m-d'),
            'hora_de_emision' => $fecha->format('H:i:s'),
            'moneda' => $venta->moneda === 'USD' ? 'USD' : 'PEN',
            'tipo_operacion' => '0101',
            'cliente_tipo_de_documento' => (string) $receptor['tipo_doc'],
            'cliente_numero_de_documento' => $receptor['num_doc'],
            'cliente_denominacion' => $receptor['nombre'],
            'cliente_direccion' => mb_substr((string) ($venta->propietario?->direccion ?? '-'), 0, 250) ?: '-',
            'items' => $items,
            'total' => number_format((float) (string) $venta->total, 2, '.', ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $respuesta
     */
    public function respuestaExitosa(array $respuesta): bool
    {
        if (! ($respuesta['success'] ?? false)) {
            return false;
        }

        $estado = strtoupper((string) (($respuesta['payload'] ?? [])['estado'] ?? ''));

        return in_array($estado, ['ACEPTADO', 'PENDIENTE'], true);
    }

    /**
     * @param  array<string, mixed>  $respuesta
     */
    public function extraerMensajeError(array $respuesta): string
    {
        $msg = $respuesta['message'] ?? null;
        if (is_string($msg) && $msg !== '') {
            return $msg;
        }

        $estado = ($respuesta['payload'] ?? [])['estado'] ?? null;
        if (is_string($estado) && $estado !== '') {
            return 'APISUNAT: '.$estado;
        }

        return 'APISUNAT rechazó el comprobante.';
    }

    /**
     * @param  array<string, mixed>  $respuesta
     * @return array{pdf: ?string, xml: ?string, cdr: ?string, consulta: ?string}
     */
    public function extraerEnlaces(array $respuesta): array
    {
        $payload = $respuesta['payload'] ?? [];
        $pdfBlock = is_array($payload['pdf'] ?? null) ? $payload['pdf'] : [];

        $pdf = $pdfBlock['ticket'] ?? $pdfBlock['a4'] ?? null;
        if (! is_string($pdf)) {
            $pdf = null;
        }

        $xml = is_string($payload['xml'] ?? null) ? $payload['xml'] : null;
        $cdr = is_string($payload['cdr'] ?? null) ? $payload['cdr'] : null;

        return [
            'pdf' => $pdf,
            'xml' => $xml,
            'cdr' => $cdr,
            'consulta' => $pdf,
        ];
    }
}
