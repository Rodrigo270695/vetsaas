<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class ApiPeruHttp
{
    public static function client(): PendingRequest
    {
        $token = trim((string) config('services.apiperu.token', ''));

        if ($token === '') {
            throw new ApiPeruConsultaException(
                __('propietarios.consulta.token_missing'),
                503,
                'not_configured',
            );
        }

        $base = rtrim((string) config('services.apiperu.base_url', 'https://apiperu.dev/api'), '/');

        return Http::timeout(25)
            ->acceptJson()
            ->withToken($token)
            ->baseUrl($base);
    }

    public static function assertSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        if ($status === 429) {
            throw new ApiPeruConsultaException(
                __('propietarios.consulta.rate_limit'),
                429,
                'rate_limit',
            );
        }

        if ($status >= 500) {
            throw new ApiPeruConsultaException(
                __('propietarios.consulta.no_disponible'),
                503,
                'service_unavailable',
            );
        }

        throw new ApiPeruConsultaException(
            __('propietarios.consulta.error_generico', ['status' => $status]),
            422,
            'api_error',
        );
    }
}
