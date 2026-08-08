<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Support\Integrations\ApiPeruEndpointCatalog;
use RuntimeException;
use Throwable;

/**
 * Proxy genérico hacia endpoints ApiPerú whitelisteados (explorador plataforma).
 */
final class ApiPeruConsultaService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, data: mixed, time?: float|int|null, raw: array<string, mixed>}
     */
    public function consultar(string $endpointKey, array $payload): array
    {
        $endpoint = ApiPeruEndpointCatalog::find($endpointKey);
        if ($endpoint === null) {
            throw new RuntimeException('Endpoint ApiPerú no permitido.');
        }

        $body = $this->normalizePayload($endpoint, $payload);
        $path = (string) $endpoint['path'];

        $response = ApiPeruHttp::client()->post($path, $body);

        ApiPeruHttp::assertSuccessful($response, $path);

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Respuesta ApiPerú inválida (no JSON).');
        }

        if (! ($json['success'] ?? false)) {
            $msg = is_string($json['message'] ?? null)
                ? $json['message']
                : 'La consulta no devolvió resultados.';

            throw new RuntimeException($msg);
        }

        return [
            'success' => true,
            'data' => $json['data'] ?? null,
            'time' => $json['time'] ?? null,
            'raw' => $json,
        ];
    }

    /**
     * Ejecuta en secuencia los endpoints de un perfil UX (persona, empresa…).
     * Cada falló parcial se reporta sin abortar el resto.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     profile: string,
     *     label: string,
     *     subject: string|null,
     *     ok_count: int,
     *     fail_count: int,
     *     results: array<string, array{ok: bool, label: string, data?: mixed, time?: float|int|null, message?: string, code?: string|null}>
     * }
     */
    public function consultarPerfil(string $profileId, array $payload): array
    {
        $profile = ApiPeruEndpointCatalog::findProfile($profileId);
        if ($profile === null) {
            throw new RuntimeException('Perfil de consulta no permitido.');
        }

        $results = [];
        $ok = 0;
        $fail = 0;

        foreach ($profile['endpoint_keys'] as $endpointKey) {
            $label = $profile['tab_labels'][$endpointKey]
                ?? (ApiPeruEndpointCatalog::find($endpointKey)['label'] ?? $endpointKey);

            $endpointPayload = $this->payloadForEndpoint($endpointKey, $payload);

            try {
                $hit = $this->consultar($endpointKey, $endpointPayload);
                $results[$endpointKey] = [
                    'ok' => true,
                    'label' => $label,
                    'data' => $hit['data'],
                    'time' => $hit['time'] ?? null,
                ];
                $ok++;
            } catch (ApiPeruConsultaException $e) {
                $results[$endpointKey] = [
                    'ok' => false,
                    'label' => $label,
                    'message' => $e->getMessage(),
                    'code' => $e->errorCode,
                ];
                $fail++;
            } catch (Throwable $e) {
                $results[$endpointKey] = [
                    'ok' => false,
                    'label' => $label,
                    'message' => $e->getMessage(),
                    'code' => null,
                ];
                $fail++;
            }
        }

        $subject = null;
        if (isset($payload['ruc']) && is_string($payload['ruc']) && $payload['ruc'] !== '') {
            $subject = 'RUC '.$payload['ruc'];
        } elseif (isset($payload['dni']) && is_string($payload['dni']) && $payload['dni'] !== '') {
            $subject = 'DNI '.$payload['dni'];
        } elseif (isset($payload['placa']) && is_string($payload['placa']) && $payload['placa'] !== '') {
            $subject = 'Placa '.strtoupper($payload['placa']);
        } elseif (isset($payload['fecha']) && is_string($payload['fecha']) && $payload['fecha'] !== '') {
            $subject = 'Fecha '.$payload['fecha'];
        }

        return [
            'profile' => $profileId,
            'label' => (string) $profile['label'],
            'subject' => $subject,
            'ok_count' => $ok,
            'fail_count' => $fail,
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadForEndpoint(string $endpointKey, array $payload): array
    {
        return match ($endpointKey) {
            'dni', 'dni_ruc', 'licencia' => [
                'dni' => $payload['dni'] ?? '',
            ],
            'ruc', 'ruc_sunat', 'ruc_contacto', 'ruc_ssco', 'ruc_deuda_coactiva',
            'ruc_representantes', 'ruc_establecimientos_anexos', 'ruc_domicilio_fiscal', 'ruc_trabajadores' => [
                'ruc' => $payload['ruc'] ?? '',
            ],
            'tipo_de_cambio' => [
                'fecha' => $payload['fecha'] ?? '',
            ],
            'comisiones_afp' => [
                'periodo' => $this->periodoFromPayload($payload),
            ],
            'cpe' => [
                'ruc_emisor' => $payload['ruc_emisor'] ?? '',
                'codigo_tipo_documento' => $payload['codigo_tipo_documento'] ?? '',
                'serie' => $payload['serie'] ?? '',
                'numero' => $payload['numero'] ?? '',
                'fecha_de_emision' => $payload['fecha_de_emision'] ?? '',
                'monto' => $payload['monto'] ?? '',
            ],
            'placa' => [
                'placa' => $payload['placa'] ?? '',
            ],
            'ubigeo' => [
                'ubigeo' => $payload['q'] ?? ($payload['ubigeo'] ?? ''),
            ],
            'puertos', 'aeropuertos' => [
                'nombre' => $payload['q'] ?? ($payload['nombre'] ?? ''),
            ],
            default => $payload,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function periodoFromPayload(array $payload): string
    {
        $periodo = trim((string) ($payload['periodo'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}$/', $periodo) === 1) {
            return $periodo;
        }

        $fecha = trim((string) ($payload['fecha'] ?? ''));
        if (preg_match('/^(\d{4}-\d{2})/', $fecha, $m) === 1) {
            return $m[1];
        }

        return now()->format('Y-m');
    }

    /**
     * @param  array<string, mixed>  $endpoint
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $endpoint, array $payload): array
    {
        $fields = $endpoint['fields'] ?? [];
        if (! is_array($fields)) {
            return [];
        }

        // Endpoints sin campos definidos (AFP, listados abiertos): pasar payload limpio.
        if ($fields === []) {
            $clean = [];
            foreach ($payload as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    continue;
                }
                $clean[$key] = is_string($value) ? trim($value) : $value;
            }

            return $clean;
        }

        $body = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $required = (bool) ($field['required'] ?? false);
            $type = (string) ($field['type'] ?? 'text');
            $raw = $payload[$name] ?? null;

            if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                if ($required) {
                    throw new RuntimeException('El campo «'.((string) ($field['label'] ?? $name)).'» es obligatorio.');
                }

                continue;
            }

            if ($type === 'textarea' && $name === 'comprobantes') {
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                if (! is_array($decoded)) {
                    throw new RuntimeException('El campo comprobantes debe ser un JSON array válido.');
                }
                $body[$name] = $decoded;

                continue;
            }

            $value = is_string($raw) ? trim($raw) : $raw;

            if (in_array($name, ['dni', 'ruc', 'ruc_emisor'], true) && is_string($value)) {
                $value = preg_replace('/\D+/', '', $value) ?? '';
            }

            if ($name === 'placa' && is_string($value)) {
                $value = strtoupper(preg_replace('/\s+/', '', $value) ?? '');
            }

            $pattern = $field['pattern'] ?? null;
            if (is_string($pattern) && $pattern !== '' && is_string($value) && preg_match('/'.$pattern.'/', $value) !== 1) {
                throw new RuntimeException('Formato inválido en «'.((string) ($field['label'] ?? $name)).'».');
            }

            $max = $field['max_length'] ?? null;
            if (is_int($max) && is_string($value) && mb_strlen($value) > $max) {
                $value = mb_substr($value, 0, $max);
            }

            $body[$name] = $value;
        }

        return $body;
    }
}
