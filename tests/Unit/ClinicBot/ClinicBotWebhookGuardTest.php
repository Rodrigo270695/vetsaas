<?php

declare(strict_types=1);

use App\Support\ClinicBot\ClinicBotWebhookGuard;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

it('detecta eventos salientes de OpenWA', function (): void {
    $guard = new ClinicBotWebhookGuard;

    expect($guard->isOutgoingEvent('message.sent'))->toBeTrue()
        ->and($guard->isOutgoingEvent('message.ack'))->toBeTrue()
        ->and($guard->isOutgoingEvent('message.received'))->toBeFalse();
});

it('ignora eco de mensaje saliente reciente', function (): void {
    $guard = new ClinicBotWebhookGuard;
    $guard->rememberOutbound('sess-1', '51999@c.us', 'Hola, ¿en qué te ayudo?');

    expect($guard->shouldSkipOutboundEcho('sess-1', '51999@c.us', 'Hola, ¿en qué te ayudo?'))->toBeTrue()
        ->and($guard->shouldSkipOutboundEcho('sess-1', '51999@c.us', 'Otro mensaje'))->toBeFalse();
});

it('deduplica por id y por cuerpo del mensaje', function (): void {
    $guard = new ClinicBotWebhookGuard;

    expect($guard->isDuplicateInbound('sess-1', 'wamid-1', '51999@c.us', 'Hola'))->toBeFalse();

    $guard->rememberInbound('sess-1', 'wamid-1', '51999@c.us', 'Hola');

    expect($guard->isDuplicateInbound('sess-1', 'wamid-1', '51999@c.us', 'Hola'))->toBeTrue()
        ->and($guard->isDuplicateInbound('sess-1', '', '51999@c.us', 'Hola'))->toBeTrue();
});

it('no notifica al usuario cuando OpenWA devuelve 429', function (): void {
    $guard = new ClinicBotWebhookGuard;

    expect($guard->shouldNotifyUserOfFailure(new RuntimeException('OpenWA HTTP 429: Too Many Requests')))->toBeFalse()
        ->and($guard->shouldNotifyUserOfFailure(new RuntimeException('OpenWA HTTP 500')))->toBeTrue();
});
