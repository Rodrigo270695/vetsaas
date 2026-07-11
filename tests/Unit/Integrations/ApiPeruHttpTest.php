<?php

namespace Tests\Unit\Integrations;

use App\Services\Integrations\ApiPeruConsultaException;
use App\Services\Integrations\ApiPeruHttp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiPeruHttpTest extends TestCase
{
    public function test_assert_successful_maps_rate_limit_to_429_exception(): void
    {
        config()->set('services.apiperu.token', 'test-token');
        config()->set('services.apiperu.base_url', 'https://apiperu.dev/api');

        Http::fake([
            'https://apiperu.dev/api/dni' => Http::response(['message' => 'Too Many Requests'], 429),
        ]);

        $response = ApiPeruHttp::client()->post('/dni', ['dni' => '12345678']);

        try {
            ApiPeruHttp::assertSuccessful($response);
            $this->fail('Se esperaba ApiPeruConsultaException.');
        } catch (ApiPeruConsultaException $e) {
            $this->assertSame(429, $e->httpStatus);
            $this->assertSame('rate_limit', $e->errorCode);
        }
    }
}
