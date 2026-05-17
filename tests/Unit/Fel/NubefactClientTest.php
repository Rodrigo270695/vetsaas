<?php

use App\Services\Fel\NubefactClient;
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

it('envía generar_comprobante al endpoint del token', function (): void {
    Http::fake([
        'api.nubefact.com/*' => Http::response([
            'aceptada_por_sunat' => 'SI',
            'enlace_del_pdf' => 'https://example.com/b001-1.pdf',
            'serie' => 'B001',
            'numero' => '1',
        ], 200),
    ]);

    $client = new NubefactClient;
    $resp = $client->generarComprobante('token-demo', [
        'operacion' => 'generar_comprobante',
        'tipo_de_comprobante' => '2',
    ]);

    expect($resp)->toHaveKey('enlace_del_pdf');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'token-demo')
            && $request['operacion'] === 'generar_comprobante';
    });
});
