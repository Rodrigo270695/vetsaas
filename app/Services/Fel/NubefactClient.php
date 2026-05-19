<?php

namespace App\Services\Fel;

use App\Support\Fel\NubefactCredentials;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente HTTP mínimo para Nubefact API JSON v1.
 *
 * Nubefact exige POST a la RUTA del panel y el TOKEN en el header
 * `Authorization: Token token="..."` (no en la URL).
 *
 * @see https://www.nubefact.com/integracion
 */
final class NubefactClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generarComprobante(NubefactCredentials $credentials, array $payload): array
    {
        $url = trim($credentials->apiRuta);
        $token = trim($credentials->apiToken);

        if ($url === '') {
            throw new RuntimeException('Ruta de Nubefact vacía.');
        }

        if ($token === '') {
            throw new RuntimeException('Token de Nubefact vacío.');
        }

        try {
            $response = Http::timeout((int) config('services.nubefact.timeout', 60))
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => 'Token token="'.$token.'"',
                    'Content-Type' => 'application/json',
                ])
                ->asJson()
                ->post($url, $payload);
        } catch (RequestException $e) {
            throw new RuntimeException(
                'Error de red al contactar Nubefact: '.$e->getMessage(),
                0,
                $e,
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                self::formatearErrorHttp($response->status(), $response->body(), $payload),
            );
        }

        /** @var array<string, mixed>|null $data */
        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Respuesta JSON inválida de Nubefact.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function formatearErrorHttp(int $status, string $body, array $payload): string
    {
        $base = 'Nubefact respondió HTTP '.$status.': '.$body;

        $serie = isset($payload['serie']) ? trim((string) $payload['serie']) : '';
        $tipo = isset($payload['tipo_de_comprobante']) ? trim((string) $payload['tipo_de_comprobante']) : '';
        $tipoLabel = match ($tipo) {
            '1' => 'factura',
            '2' => 'boleta',
            default => 'comprobante',
        };

        if ($serie === '') {
            return $base;
        }

        $hint = " (enviado: serie «{$serie}», tipo {$tipoLabel}). "
            .'Esa serie debe existir y estar activa en Nubefact › Locales y series para el mismo local de tu RUTA API.';

        if (str_contains($body, 'No puedes emitir comprobantes con esta serie') || str_contains($body, '"codigo":21')) {
            return $base.$hint
                .' Si en Nubefact tienes otra serie (ej. BB01), cámbiala en Configuración › Sedes.';
        }

        return $base.' Enviado: serie «'.$serie.'».';
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function extraerMensajeError(array $response): string
    {
        if (isset($response['errors']) && is_string($response['errors'])) {
            return $response['errors'];
        }

        if (isset($response['errors']) && is_array($response['errors'])) {
            $parts = [];
            foreach ($response['errors'] as $err) {
                if (is_string($err)) {
                    $parts[] = $err;
                } elseif (is_array($err) && isset($err['message']) && is_string($err['message'])) {
                    $parts[] = $err['message'];
                }
            }

            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        if (isset($response['codigo']) && is_string($response['codigo'])) {
            $desc = is_string($response['descripcion'] ?? null) ? $response['descripcion'] : '';

            return trim($response['codigo'].' '.$desc);
        }

        return 'Nubefact rechazó el comprobante sin detalle.';
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function respuestaExitosa(array $response): bool
    {
        if (isset($response['errors'])) {
            return false;
        }

        return isset($response['aceptada_por_sunat'])
            || isset($response['enlace_del_pdf'])
            || isset($response['enlace'])
            || isset($response['cadena_para_codigo_qr']);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function respuestaAnulacionExitosa(array $response): bool
    {
        if (isset($response['errors'])) {
            return false;
        }

        return isset($response['anulado'])
            || (isset($response['sunat_responsecode']) && (string) $response['sunat_responsecode'] === '0')
            || isset($response['aceptada_por_sunat']);
    }
}
