<?php

use App\Services\Fel\NubefactClient;
use App\Support\Fel\NubefactCredentials;
use Illuminate\Support\Facades\Http;

it('detecta respuesta exitosa de Nubefact', function (): void {
    $client = new NubefactClient;

    expect($client->respuestaExitosa([
        'aceptada_por_sunat' => 'SI',
        'enlace_del_pdf' => 'https://example.com/pdf',
    ]))->toBeTrue();

    expect($client->respuestaExitosa([
        'errors' => 'Serie no existe',
    ]))->toBeFalse();
});

it('envía generar_comprobante a la ruta con token en Authorization', function (): void {
    $ruta = 'https://api.nubefact.com/api/v1/empresa-demo-uuid';

    Http::fake([
        $ruta => Http::response([
            'aceptada_por_sunat' => 'SI',
            'enlace_del_pdf' => 'https://example.com/b001-1.pdf',
            'serie' => 'B001',
            'numero' => '1',
        ], 200),
    ]);

    $client = new NubefactClient;
    $resp = $client->generarComprobante(
        new NubefactCredentials(apiRuta: $ruta, apiToken: 'token-demo'),
        [
            'operacion' => 'generar_comprobante',
            'tipo_de_comprobante' => '2',
        ],
    );

    expect($resp)->toHaveKey('enlace_del_pdf');

    Http::assertSent(function ($request) use ($ruta): bool {
        return $request->url() === $ruta
            && $request->hasHeader('Authorization', 'Token token="token-demo"')
            && $request['operacion'] === 'generar_comprobante';
    });
});

it('incluye serie y guía cuando Nubefact rechaza la serie (código 21)', function (): void {
    $ruta = 'https://api.nubefact.com/api/v1/empresa-demo-uuid';

    Http::fake([
        $ruta => Http::response([
            'errors' => 'No puedes emitir comprobantes con esta serie',
            'codigo' => 21,
        ], 400),
    ]);

    $client = new NubefactClient;

    try {
        $client->generarComprobante(
            new NubefactCredentials(apiRuta: $ruta, apiToken: 'token-demo'),
            [
                'operacion' => 'generar_comprobante',
                'tipo_de_comprobante' => '2',
                'serie' => 'B001',
                'numero' => '1',
            ],
        );
        expect(false)->toBeTrue('debía lanzar RuntimeException');
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->toContain('B001')
            ->toContain('Locales y series')
            ->toContain('Configuración › Sedes');
    }
});
