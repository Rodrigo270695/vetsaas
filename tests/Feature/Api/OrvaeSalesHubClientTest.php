<?php

declare(strict_types=1);

use App\Support\Sales\OrvaeSalesHubClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'orvae.provision.hmac_secret' => 'test-saleshub-secret',
        'salesbot.orvae.refresh_url' => 'https://orvae.test/api/internal/saas/vetsaas/knowledge/refresh',
        'salesbot.orvae.conversations_url' => null,
    ]);
});

it('deduce la URL de conversaciones desde la de refresco y firma el GET', function (): void {
    Http::fake(['orvae.test/*' => Http::response(['data' => [['id' => 'x', 'nombre' => 'Clínica Sol']], 'meta' => ['total' => 1], 'stats' => ['total' => 1]])]);

    $data = app(OrvaeSalesHubClient::class)->conversations(['search' => 'sol', 'estado' => 'todos', 'page' => 2]);

    expect($data['data'][0]['nombre'])->toBe('Clínica Sol');
    Http::assertSent(function (Request $request): bool {
        $timestamp = $request->header('X-Orvae-Timestamp')[0];

        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://orvae.test/api/internal/saas/vetsaas/saleshub/conversations?')
            && $request['search'] === 'sol'
            && (int) $request['page'] === 2
            && $request->header('X-Orvae-Signature')[0] === 'sha256='.hash_hmac('sha256', $timestamp.'.', 'test-saleshub-secret');
    });
});

it('pide una conversación por id y no consulta ids inválidos', function (): void {
    Http::fake(['orvae.test/*' => Http::response(['conversation' => ['id' => 'a'], 'messages' => [['body' => 'Hola']]])]);
    $client = app(OrvaeSalesHubClient::class);
    $id = '9f1c2e4a-1111-4222-8333-444455556666';

    expect($client->conversation($id)['messages'][0]['body'])->toBe('Hola')
        ->and($client->conversation('../../otra-ruta'))->toBeNull();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === "https://orvae.test/api/internal/saas/vetsaas/saleshub/conversations/{$id}");
});

it('devuelve null si ORVAE falla o no está configurado', function (): void {
    Http::fake(['orvae.test/*' => Http::response(['error' => 'down'], 500)]);

    expect(app(OrvaeSalesHubClient::class)->conversations())->toBeNull();

    config(['orvae.provision.hmac_secret' => null]);
    $client = app(OrvaeSalesHubClient::class);

    expect($client->isConfigured())->toBeFalse()
        ->and($client->conversations())->toBeNull();
    Http::assertSentCount(1);
});

it('usa la URL explícita si está definida', function (): void {
    config(['salesbot.orvae.conversations_url' => 'https://otro.test/conv/']);

    expect(app(OrvaeSalesHubClient::class)->baseUrl())->toBe('https://otro.test/conv');
});
