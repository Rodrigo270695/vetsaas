<?php

namespace App\Services\Integrations;

use App\Support\Integrations\ApisunatLookupTokenResolver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Consulta DNI/RUC vía APISUNAT (Lucode) — APIs de apoyo.
 *
 * @see https://docs.apisunat.pe/apis-de-apoyo
 */
final class ApisunatLookupService
{
    public function isConfigured(): bool
    {
        return ApisunatLookupTokenResolver::resolve() !== null;
    }

    /**
     * @return array{
     *     dni: string,
     *     nombres: string,
     *     apellidos: string,
     *     nombre_completo: string,
     * }
     */
    public function consultarDni(string $dni): array
    {
        $dni = preg_replace('/\D+/', '', $dni) ?? '';

        if (strlen($dni) !== 8) {
            throw new RuntimeException('El DNI debe tener 8 dígitos.');
        }

        $json = $this->getJson("/person/dni/{$dni}");

        if (! ($json['success'] ?? false)) {
            $msg = is_string($json['message'] ?? null) ? $json['message'] : 'No se encontraron datos para el DNI indicado.';
            throw new RuntimeException($msg);
        }

        $data = $this->extractPayload($json);
        $nombres = trim((string) ($data['nombres'] ?? ''));
        $paterno = trim((string) ($data['apellido_paterno'] ?? $data['apellidoPaterno'] ?? ''));
        $materno = trim((string) ($data['apellido_materno'] ?? $data['apellidoMaterno'] ?? ''));
        $apellidos = trim($paterno.' '.$materno);
        $nombreCompleto = trim((string) ($data['nombre_completo'] ?? $data['nombreCompleto'] ?? ''));

        if ($nombres === '' && $nombreCompleto !== '') {
            $partes = preg_split('/\s+/', $nombreCompleto) ?: [];
            if (count($partes) >= 3) {
                $nombres = implode(' ', array_slice($partes, 2));
                $paterno = $paterno !== '' ? $paterno : (string) ($partes[0] ?? '');
                $materno = $materno !== '' ? $materno : (string) ($partes[1] ?? '');
                $apellidos = trim($paterno.' '.$materno);
            } else {
                $nombres = $nombreCompleto;
            }
        }

        if ($nombres === '' && $apellidos === '' && $nombreCompleto === '') {
            throw new RuntimeException('La API no devolvió nombres para este DNI.');
        }

        return [
            'dni' => $dni,
            'nombres' => mb_substr($nombres !== '' ? $nombres : $nombreCompleto, 0, 150),
            'apellidos' => mb_substr($apellidos, 0, 150),
            'nombre_completo' => mb_substr($nombreCompleto !== '' ? $nombreCompleto : trim($nombres.' '.$apellidos), 0, 255),
        ];
    }

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
    public function consultarRuc(string $ruc): array
    {
        $ruc = preg_replace('/\D+/', '', $ruc) ?? '';

        if (strlen($ruc) !== 11) {
            throw new RuntimeException('El RUC debe tener 11 dígitos.');
        }

        $json = $this->getJson("/business/ruc/{$ruc}");

        if (! ($json['success'] ?? false)) {
            $msg = is_string($json['message'] ?? null) ? $json['message'] : 'No se encontraron datos para el RUC indicado.';
            throw new RuntimeException($msg);
        }

        $data = $this->extractPayload($json);
        $razon = trim((string) ($data['razon_social'] ?? $data['nombre_o_razon_social'] ?? ''));
        if ($razon === '') {
            throw new RuntimeException('La API no devolvió razón social para este RUC.');
        }

        $direccion = $data['direccion_fiscal'] ?? $data['direccion_completa'] ?? $data['direccion'] ?? null;
        $direccion = is_string($direccion) && trim($direccion) !== '' ? trim($direccion) : null;

        $ubigeo = $data['ubigeo_sunat'] ?? $data['ubigeo'] ?? null;
        $ubigeo = is_string($ubigeo) && preg_match('/^\d{6}$/', $ubigeo) === 1 ? $ubigeo : null;

        $estado = $data['estado'] ?? $data['estado_sunat'] ?? null;
        $estado = is_string($estado) && $estado !== '' ? mb_substr($estado, 0, 32) : null;

        $condicion = $data['condicion'] ?? $data['condicion_sunat'] ?? null;
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

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $path): array
    {
        $token = ApisunatLookupTokenResolver::resolve();
        if ($token === null) {
            throw new ApiPeruConsultaException(
                __('propietarios.consulta.apisunat_token_missing'),
                503,
                'not_configured',
            );
        }

        $base = rtrim((string) config('services.apisunat_lookup.base_url', 'https://dev.apisunat.pe/api/v1'), '/');
        $response = Http::timeout(25)
            ->acceptJson()
            ->withToken($token)
            ->get($base.$path);

        if ($response->status() === 429) {
            throw new ApiPeruConsultaException(
                __('propietarios.consulta.rate_limit'),
                429,
                'rate_limit',
            );
        }

        if ($response->status() === 401) {
            throw new ApiPeruConsultaException(
                __('propietarios.consulta.apisunat_token_invalid'),
                503,
                'not_configured',
            );
        }

        if (! $response->successful()) {
            $json = $response->json();
            if (is_array($json) && is_string($json['message'] ?? null) && $json['message'] !== '') {
                throw new RuntimeException($json['message']);
            }

            throw new ApiPeruConsultaException(
                __('propietarios.consulta.error_generico', ['status' => $response->status()]),
                $response->status() >= 500 ? 503 : 422,
                'api_error',
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function extractPayload(array $json): array
    {
        $payload = $json['payload'] ?? $json['data'] ?? null;

        return is_array($payload) ? $payload : [];
    }
}
