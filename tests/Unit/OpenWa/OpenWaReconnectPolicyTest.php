<?php

declare(strict_types=1);

use App\Support\OpenWa\OpenWaReconnectPolicy;

it('no arranca el motor si la sesión ya está viva', function (string $status): void {
    expect(OpenWaReconnectPolicy::shouldStartEngine($status, true, false, true))->toBeFalse()
        ->and(OpenWaReconnectPolicy::isLive($status))->toBeTrue();
})->with(['ready', 'initializing', 'authenticating', 'qr_ready']);

it('arranca sesiones caídas con auto-reconnect', function (string $status): void {
    expect(OpenWaReconnectPolicy::shouldStartEngine($status, true, false, true))->toBeTrue();
})->with(['disconnected', 'failed']);

it('no arranca una sesión created sin teléfono ni pedido de QR', function (): void {
    expect(OpenWaReconnectPolicy::shouldStartEngine('created', true, false, false))->toBeFalse();
});

it('arranca created si ya hubo teléfono (auth en disco) o el usuario pidió QR', function (): void {
    expect(OpenWaReconnectPolicy::shouldStartEngine('created', true, false, true))->toBeTrue()
        ->and(OpenWaReconnectPolicy::shouldStartEngine('created', true, true, false))->toBeTrue();
});

it('no encola reconnect si auto_reconnect está apagado', function (): void {
    expect(OpenWaReconnectPolicy::shouldEnqueueAutoReconnect('disconnected', false, true, true))->toBeFalse();
});

it('no encola reconnect si falta el id de sesión OpenWA', function (): void {
    expect(OpenWaReconnectPolicy::shouldEnqueueAutoReconnect('disconnected', true, true, false))->toBeFalse();
});

it('encola disconnected con auto-reconnect', function (): void {
    expect(OpenWaReconnectPolicy::shouldEnqueueAutoReconnect('disconnected', true, true, true))->toBeTrue();
});
