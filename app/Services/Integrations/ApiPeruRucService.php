<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente para consulta de RUC vía {@link https://apiperu.dev apiperu.dev}.
 *
 * Documentación: POST `{base_url}/ruc` con header `Authorization: Bearer {token}`
 * y cuerpo JSON `{"ruc":"20100070970"}` (11 dígitos).
 */
final class ApiPeruRucService
{
    /**
     * @return array{
     *     ruc: string,
     *     razon_social: string,
     *     direccion: string|null,
     *     ubigeo_sunat: string|null,
     *     estado_sunat: string|null,
     *     condicion_sunat: string|null,
     * }
     */
    public function consultar(string $ruc): array
    {
        $token = trim((string) config('services.apiperu.token', ''));
        $base = rtrim((string) config('services.apiperu.base_url', 'https://apiperu.dev/api'), '/');

        if ($token === '') {
            throw new RuntimeException('Consulta RUC no disponible: configure APIPERU_TOKEN en el servidor.');
        }

        $response = Http::timeout(25)
            ->acceptJson()
            ->withToken($token)
            ->post($base.'/ruc', ['ruc' => $ruc]);

        if (! $response->successful()) {
            throw new RuntimeException('La API de consulta RUC devolvió HTTP '.$response->status().'.');
        }

        $json = $response->json();
        if (! is_array($json) || ! ($json['success'] ?? false)) {
            $msg = is_string($json['message'] ?? null) ? $json['message'] : 'No se encontraron datos para el RUC indicado.';
            throw new RuntimeException($msg);
        }

        $data = $json['data'] ?? null;
        if (! is_array($data)) {
            throw new RuntimeException('Respuesta de API RUC inválida (sin data).');
        }

        $razon = (string) ($data['nombre_o_razon_social'] ?? '');
        if ($razon === '') {
            throw new RuntimeException('La API no devolvió razón social para este RUC.');
        }

        $direccion = $data['direccion_completa'] ?? $data['direccion'] ?? null;
        $direccion = is_string($direccion) && $direccion !== '' ? $direccion : null;

        $ubigeo = $data['ubigeo_sunat'] ?? null;
        $ubigeo = is_string($ubigeo) && preg_match('/^\d{6}$/', $ubigeo) === 1 ? $ubigeo : null;

        $estado = $data['estado'] ?? null;
        $estado = is_string($estado) && $estado !== '' ? mb_substr($estado, 0, 32) : null;

        $condicion = $data['condicion'] ?? null;
        $condicion = is_string($condicion) && $condicion !== '' ? mb_substr($condicion, 0, 32) : null;

        return [
            'ruc' => $ruc,
            'razon_social' => mb_substr($razon, 0, 255),
            'direccion' => $direccion,
            'ubigeo_sunat' => $ubigeo,
            'estado_sunat' => $estado,
            'condicion_sunat' => $condicion,
        ];
    }
}
