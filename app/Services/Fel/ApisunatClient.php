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

    private const PROD_VOIDED_URL = 'https://app.apisunat.pe/api/v3/voided';

    private const SANDBOX_VOIDED_URL = 'https://sandbox.apisunat.pe/api/v3/voided';

    private const PROD_SUMMARY_URL = 'https://app.apisunat.pe/api/v3/daily-summary';

    private const SANDBOX_SUMMARY_URL = 'https://sandbox.apisunat.pe/api/v3/daily-summary';

    private const PROD_STATUS_URL = 'https://app.apisunat.pe/api/v3/status';

    private const SANDBOX_STATUS_URL = 'https://sandbox.apisunat.pe/api/v3/status';

    private const DOC_NOMBRES = [
        FelSerie::TIPO_FACTURA => 'factura',
        FelSerie::TIPO_BOLETA => 'boleta',
        FelSerie::TIPO_NOTA_CREDITO => 'nota_credito',
        FelSerie::TIPO_NOTA_DEBITO => 'nota_debito',
    ];

    /**
     * @param  array{token: string, mode: 'sandbox'|'produccion'}  $credenciales
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generarComprobante(array $credenciales, array $payload): array
    {
        $url = $credenciales['mode'] === 'produccion' ? self::PROD_URL : self::SANDBOX_URL;

        return $this->postJson($credenciales, $url, $payload);
    }

    /**
     * Comunicación de baja (facturas / NC / ND).
     *
     * @param  array{token: string, mode: 'sandbox'|'produccion'}  $credenciales
     * @return array<string, mixed>
     */
    public function comunicarBaja(
        array $credenciales,
        string $documentoAfectado,
        string $serie,
        int $numero,
        string $motivo = 'ANULACIÓN DE OPERACIÓN',
    ): array {
        $url = $credenciales['mode'] === 'produccion' ? self::PROD_VOIDED_URL : self::SANDBOX_VOIDED_URL;

        return $this->postJson($credenciales, $url, [
            'documento' => 'comunicacion_baja',
            'motivo' => mb_substr(trim($motivo) !== '' ? trim($motivo) : 'ANULACIÓN DE OPERACIÓN', 0, 250),
            'documento_afectado' => [
                'documento' => $documentoAfectado,
                'serie' => $serie,
                'numero' => $numero,
            ],
        ]);
    }

    /**
     * Resumen diario para anular boletas.
     *
     * @param  array{token: string, mode: 'sandbox'|'produccion'}  $credenciales
     * @return array<string, mixed>
     */
    public function anularBoletaResumen(
        array $credenciales,
        string $serie,
        int $numero,
    ): array {
        $url = $credenciales['mode'] === 'produccion' ? self::PROD_SUMMARY_URL : self::SANDBOX_SUMMARY_URL;

        return $this->postJson($credenciales, $url, [
            'documento' => 'resumen_diario',
            'documentos_afectados' => [[
                'accion_resumen' => 'anular',
                'documento' => 'boleta',
                'serie' => $serie,
                'numero' => $numero,
            ]],
        ]);
    }

    /**
     * Consulta el estado SUNAT/Lucode de un comprobante ya enviado.
     *
     * @param  array{token: string, mode: 'sandbox'|'produccion'}  $credenciales
     * @return array<string, mixed>
     */
    public function consultarEstado(
        array $credenciales,
        string $documentoNombre,
        string $serie,
        int $numero,
    ): array {
        $url = $credenciales['mode'] === 'produccion' ? self::PROD_STATUS_URL : self::SANDBOX_STATUS_URL;

        return $this->postJson($credenciales, $url, [
            'documento' => $documentoNombre,
            'serie' => $serie,
            'numero' => $numero,
        ]);
    }

    /**
     * Extrae el estado Lucode (ACEPTADO|PENDIENTE|RECHAZADO|EXCEPCION|…).
     *
     * @param  array<string, mixed>  $respuesta
     */
    public function extraerEstado(array $respuesta): ?string
    {
        $estado = $respuesta['payload']['estado'] ?? null;
        if (! is_string($estado) || trim($estado) === '') {
            return null;
        }

        return strtoupper(trim($estado));
    }

    /**
     * @param  array{token: string, mode: 'sandbox'|'produccion'}  $credenciales
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postJson(array $credenciales, string $url, array $payload): array
    {
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
            $json['_vetsaas_emission_mode'] = $credenciales['mode'];
            $json['_vetsaas_api_base'] = $url;

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
        $fecha = now(config('app.timezone'));

        $items = $venta->lineas->map(function (VentaLinea $ln) use ($clinic): array {
            $cantidad = (float) (string) $ln->cantidad;
            $subtotal = round((float) (string) $ln->subtotal, 2);
            $valorUnit = $cantidad > 0 ? round($subtotal / $cantidad, 6) : 0.0;
            $tipo = (string) ($ln->igv_tipo_snapshot ?: ClinicSetting::IGV_AFECTACION_GRAVADO);
            $porcentaje = $tipo === ClinicSetting::IGV_AFECTACION_GRAVADO
                ? number_format((float) $clinic->igv_porcentaje, 0, '.', '')
                : '0';

            return [
                'unidad_de_medida' => 'NIU',
                'descripcion' => mb_substr($ln->descripcion_snapshot, 0, 250),
                'cantidad' => number_format($cantidad, 6, '.', ''),
                'valor_unitario' => number_format($valorUnit, 6, '.', ''),
                'porcentaje_igv' => $porcentaje,
                'codigo_tipo_afectacion_igv' => ClinicSetting::codigoSunatDesdeIgvTipo($tipo),
                'nombre_tributo' => ClinicSetting::nombreTributoDesdeIgvTipo($tipo),
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
     * Nota de crédito por anulación total de la operación (código 01).
     *
     * @param  array{
     *     tipo_doc: int,
     *     num_doc: string,
     *     nombre: string,
     * }  $receptor
     * @return array<string, mixed>
     */
    public function construirPayloadNotaCredito(
        Venta $venta,
        ClinicSetting $clinic,
        string $serieNc,
        int $correlativoNc,
        array $receptor,
        string $motivo,
        string $documentoAfectadoNombre,
        string $serieAfectada,
        int $numeroAfectado,
    ): array {
        $base = $this->construirPayload(
            $venta,
            $clinic,
            FelSerie::TIPO_NOTA_CREDITO,
            $serieNc,
            $correlativoNc,
            $receptor,
        );

        $base['documento'] = 'nota_credito';
        $base['nota_credito_codigo_tipo'] = '01';
        $base['nota_credito_motivo'] = mb_substr(trim($motivo) !== '' ? trim($motivo) : 'Anulación de la operación', 0, 250);
        $base['documento_afectado'] = [
            'documento' => $documentoAfectadoNombre,
            'serie' => $serieAfectada,
            'numero' => $numeroAfectado,
        ];

        return $base;
    }

    public function nombreDocumentoTipo(int $tipoComprobante): string
    {
        return self::DOC_NOMBRES[$tipoComprobante] ?? 'boleta';
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
     * Mensaje de error / motivo de rechazo lo más específico posible.
     *
     * Lucode suele devolver un texto genérico en `message`; el detalle
     * puede venir en faults/notes/observaciones del payload (APIs viejas)
     * o, en v3, solo en el CDR.
     *
     * @param  array<string, mixed>  $respuesta
     */
    public function extraerMensajeError(array $respuesta): string
    {
        $motivo = $this->extraerMotivoRechazo($respuesta);
        $base = $motivo !== '' ? $motivo : 'APISUNAT rechazó el comprobante.';

        return $this->anotarAutorizacionProduccion($base, $respuesta);
    }

    /**
     * Lucode autentica el token y aun así rechaza si la empresa no pasó a producción.
     * Eso no es “falta el token en VetSaaS”.
     *
     * @param  array<string, mixed>  $respuesta
     */
    private function anotarAutorizacionProduccion(string $mensaje, array $respuesta): string
    {
        $lower = mb_strtolower($mensaje);
        if (
            ! str_contains($lower, 'autorización para emitir')
            && ! str_contains($lower, 'autorizacion para emitir')
            && ! str_contains($lower, 'entorno de producción')
            && ! str_contains($lower, 'entorno de produccion')
        ) {
            return $mensaje;
        }

        $mode = $respuesta['_vetsaas_emission_mode'] ?? null;
        if ($mode !== 'produccion') {
            return $mensaje;
        }

        $nota = ' El token de VetSaaS sí se envió. Lucode rechaza porque esa empresa aún no está habilitada en PRODUCCIÓN (certificado, usuario secundario y “Pasar a producción” en apisunat.com). Si todavía son pruebas, el modo debe ser Sandbox.';

        if (str_contains($mensaje, 'El token de VetSaaS sí se envió')) {
            return $mensaje;
        }

        return mb_substr($mensaje.$nota, 0, 2000);
    }

    /**
     * @param  array<string, mixed>  $respuesta
     */
    public function extraerMotivoRechazo(array $respuesta): string
    {
        $candidatos = [];

        $payload = is_array($respuesta['payload'] ?? null) ? $respuesta['payload'] : [];

        foreach (['faults', 'notes', 'observaciones', 'errores', 'errors', 'detalle'] as $key) {
            $extra = $this->aplanarTextos($payload[$key] ?? null);
            foreach ($extra as $line) {
                $candidatos[] = $line;
            }
        }

        foreach (['motivo', 'descripcion', 'description', 'error', 'error_message'] as $key) {
            if (is_string($payload[$key] ?? null) && trim((string) $payload[$key]) !== '') {
                $candidatos[] = trim((string) $payload[$key]);
            }
        }

        $msg = is_string($respuesta['message'] ?? null) ? trim((string) $respuesta['message']) : '';
        if ($msg !== '') {
            $candidatos[] = $msg;
        }

        $especificos = array_values(array_filter(
            $candidatos,
            fn (string $t): bool => ! $this->esMensajeGenericoRechazo($t),
        ));

        if ($especificos !== []) {
            return mb_substr(implode(' · ', array_values(array_unique($especificos))), 0, 2000);
        }

        if ($msg !== '') {
            return mb_substr($msg, 0, 2000);
        }

        $estado = $payload['estado'] ?? null;
        if (is_string($estado) && trim($estado) !== '') {
            return 'APISUNAT: '.strtoupper(trim($estado));
        }

        return '';
    }

    public function esMensajeGenericoRechazo(string $mensaje): bool
    {
        $normalized = mb_strtolower(trim($mensaje));
        if ($normalized === '') {
            return true;
        }

        $genericos = [
            'el comprobante presenta errores o datos incorrectos',
            'el documento fue rechazado por sunat',
            'apisunat: rechazado',
            'apisunat: excepcion',
            'apisunat rechazó el comprobante',
            'apisunat rechazo el comprobante',
        ];

        foreach ($genericos as $g) {
            if (str_starts_with($normalized, $g) || $normalized === $g || str_contains($normalized, $g)) {
                return true;
            }
        }

        return str_contains($normalized, 'comuníquese con soporte')
            || str_contains($normalized, 'comuniquese con soporte');
    }

    /**
     * @return list<string>
     */
    private function aplanarTextos(mixed $value): array
    {
        if (is_string($value)) {
            $t = trim($value);

            return $t === '' ? [] : [$t];
        }

        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $t = trim($item);
                if ($t !== '') {
                    $out[] = $t;
                }

                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            foreach (['message', 'mensaje', 'description', 'descripcion', 'detail', 'detalle', 'code', 'codigo', 'text'] as $k) {
                if (is_string($item[$k] ?? null) && trim((string) $item[$k]) !== '') {
                    $out[] = trim((string) $item[$k]);
                }
            }
        }

        return $out;
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
