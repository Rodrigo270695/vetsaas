<?php

namespace Tests\Unit\Integrations;

use App\Services\Integrations\ApiPeruDniService;
use App\Services\Integrations\ApisunatLookupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentoDniFallbackTest extends TestCase
{
    public function test_usa_apisunat_cuando_apiperu_devuelve_429(): void
    {
        Cache::flush();

        config()->set('services.apiperu.token', 'apiperu-token');
        config()->set('services.apiperu.base_url', 'https://apiperu.dev/api');
        config()->set('services.apisunat_lookup.token', 'lucode-token');
        config()->set('services.apisunat_lookup.base_url', 'https://dev.apisunat.pe/api/v1');

        Http::fake([
            'https://apiperu.dev/api/dni' => Http::response(['message' => 'Too Many Requests'], 429),
            'https://dev.apisunat.pe/api/v1/person/dni/12345678' => Http::response([
                'success' => true,
                'message' => 'OK',
                'payload' => [
                    'dni' => '12345678',
                    'nombres' => 'JUAN CARLOS',
                    'apellido_paterno' => 'PEREZ',
                    'apellido_materno' => 'GARCIA',
                    'nombre_completo' => 'PEREZ GARCIA JUAN CARLOS',
                ],
            ], 200),
        ]);

        $result = app(ApiPeruDniService::class)->consultar('12345678');

        $this->assertSame('12345678', $result['dni']);
        $this->assertSame('JUAN CARLOS', $result['nombres']);
        $this->assertSame('PEREZ GARCIA', $result['apellidos']);
    }

    public function test_apisunat_lookup_service_parsea_ruc(): void
    {
        config()->set('services.apisunat_lookup.token', 'lucode-token');
        config()->set('services.apisunat_lookup.base_url', 'https://dev.apisunat.pe/api/v1');

        Http::fake([
            'https://dev.apisunat.pe/api/v1/business/ruc/20553300429' => Http::response([
                'success' => true,
                'payload' => [
                    'ruc' => '20553300429',
                    'razon_social' => 'EMPRESA DEMO S.A.C.',
                    'estado' => 'ACTIVO',
                    'condicion' => 'HABIDO',
                    'direccion_fiscal' => 'AV. DEMO 123',
                ],
            ], 200),
        ]);

        $result = app(ApisunatLookupService::class)->consultarRuc('20553300429');

        $this->assertSame('EMPRESA DEMO S.A.C.', $result['razon_social']);
        $this->assertSame('AV. DEMO 123', $result['direccion']);
        $this->assertSame('ACTIVO', $result['estado_sunat']);
    }
}
