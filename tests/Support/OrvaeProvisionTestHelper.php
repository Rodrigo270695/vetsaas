<?php

declare(strict_types=1);

namespace Tests\Support;

final class OrvaeProvisionTestHelper
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{body: string, headers: array<string, string>}
     */
    public static function signedJsonRequest(array $payload, string $secret, string $idempotencyKey = 'test:provision'): array
    {
        $timestamp = (string) now()->timestamp;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($body)) {
            $body = '{}';
        }

        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Orvae-Timestamp' => $timestamp,
                'X-Orvae-Signature' => $signature,
                'X-Idempotency-Key' => $idempotencyKey,
            ],
            'server' => self::headersToServerVars([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Orvae-Timestamp' => $timestamp,
                'X-Orvae-Signature' => $signature,
                'X-Idempotency-Key' => $idempotencyKey,
            ]),
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    public static function headersToServerVars(array $headers): array
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $normalized = strtoupper(str_replace('-', '_', $name));
            if ($normalized === 'CONTENT_TYPE' || $normalized === 'CONTENT_LENGTH') {
                $server[$normalized] = $value;
            } else {
                $server['HTTP_'.$normalized] = $value;
            }
        }

        return $server;
    }
}
