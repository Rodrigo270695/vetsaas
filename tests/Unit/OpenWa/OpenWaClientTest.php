<?php

declare(strict_types=1);

use App\Services\OpenWa\OpenWaClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'openwa.enabled' => true,
        'openwa.api_url' => 'https://wa.test',
        'openwa.api_key' => 'test-key',
    ]);
});

it('envía texto por sesión OpenWA', function (): void {
    Http::fake([
        'wa.test/api/sessions/sess-1/messages/send-text' => Http::response([
            'messageId' => 'msg-123',
            'timestamp' => 1706868000,
        ], 201),
    ]);

    $result = (new OpenWaClient)->sendText('sess-1', '51999111222@c.us', 'Hola');

    expect($result['messageId'])->toBe('msg-123');
    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://wa.test/api/sessions/sess-1/messages/send-text'
            && $request->header('X-API-Key')[0] === 'test-key'
            && $request['chatId'] === '51999111222@c.us'
            && $request['text'] === 'Hola';
    });
});

it('detiene sesión OpenWA', function (): void {
    Http::fake([
        'wa.test/api/sessions/sess-1/stop' => Http::response([
            'message' => 'Session stopped',
        ]),
    ]);

    $result = (new OpenWaClient)->stopSession('sess-1');

    expect($result['message'])->toBe('Session stopped');
    Http::assertSent(function ($request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://wa.test/api/sessions/sess-1/stop';
    });
});

it('asume entrega ante OpenWA HTTP 500 en send-text', function (): void {
    Http::fake([
        'wa.test/api/sessions/sess-1/messages/send-text' => Http::response([
            'statusCode' => 500,
            'message' => 'Internal server error',
        ], 500),
    ]);

    $result = (new OpenWaClient)->sendTextWithDeliveryFallback(
        'sess-1',
        '51999111222@c.us',
        'Hola',
    );

    expect($result['assumed_delivery'] ?? false)->toBeTrue();
});

it('no asume entrega ante timeout sin respuesta (0 bytes)', function (): void {
    $client = new OpenWaClient;
    $msg = 'cURL error 28: Operation timed out after 12001 milliseconds with 0 bytes received (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)';

    expect($client->isNoResponseTimeoutMessage($msg))->toBeTrue();
    expect($client->isAmbiguousDeliveryErrorMessage($msg))->toBeFalse();

    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException(
            'cURL error 28: Operation timed out after 12001 milliseconds with 0 bytes received',
        );
    });

    try {
        $client->sendTextWithDeliveryFallback('sess-1', '51999111222@c.us', 'Hola');
        expect(false)->toBeTrue(); // no debió asumir entrega
    } catch (\Throwable $e) {
        expect($client->isNoResponseTimeout($e) || str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), '0 bytes'))
            ->toBeTrue();
    }
});

it('intenta start si la sesión OpenWA está caída', function (): void {
    config(['openwa.reconnect_poll_seconds' => 0]);

    Http::fake([
        'wa.test/api/sessions/sess-1/start' => Http::response(['status' => 'initializing'], 200),
        'wa.test/api/sessions/sess-1' => Http::sequence()
            ->push(['id' => 'sess-1', 'status' => 'initializing'], 200)
            ->push(['id' => 'sess-1', 'status' => 'ready', 'phone' => '51999999999'], 200),
    ]);

    $client = new OpenWaClient;
    $result = $client->tryStartIfDown('sess-1', 'disconnected');

    expect($result['attempted'])->toBeTrue()
        ->and($result['error'])->toBeNull()
        ->and($result['remote']['status'] ?? null)->toBe('ready');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://wa.test/api/sessions/sess-1/start');
});

it('intenta start si la sesión OpenWA está created (nueva)', function (): void {
    config(['openwa.reconnect_poll_seconds' => 0]);

    Http::fake([
        'wa.test/api/sessions/sess-2/start' => Http::response(['status' => 'initializing'], 200),
        'wa.test/api/sessions/sess-2' => Http::response([
            'id' => 'sess-2',
            'status' => 'qr_ready',
        ], 200),
    ]);

    $result = (new OpenWaClient)->tryStartIfDown('sess-2', 'created');

    expect($result['attempted'])->toBeTrue()
        ->and($result['error'])->toBeNull()
        ->and($result['remote']['status'] ?? null)->toBe('qr_ready');
});

it('no intenta start si la sesión OpenWA ya está ready', function (): void {
    Http::fake();

    $result = (new OpenWaClient)->tryStartIfDown('sess-1', 'ready');

    expect($result['attempted'])->toBeFalse();
    Http::assertNothingSent();
});
