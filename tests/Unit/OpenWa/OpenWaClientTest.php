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

it('detecta errores ambiguos de entrega OpenWA', function (): void {
    $client = new OpenWaClient;

    expect($client->isAmbiguousDeliveryErrorMessage(
        'OpenWA HTTP 500: {"statusCode":500,"message":"Internal server error"}',
    ))->toBeTrue()
        ->and($client->isAmbiguousDeliveryErrorMessage('OpenWA HTTP 400: bad request'))->toBeFalse();
});
