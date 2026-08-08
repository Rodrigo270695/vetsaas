<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Support\Integrations\ApiPeruEndpointCatalog;
use RuntimeException;

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

        $response = ApiPeruHttp::client()->post($endpoint['path'], $body);

        ApiPeruHttp::assertSuccessful($response);

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
