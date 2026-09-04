<?php

declare(strict_types=1);

use App\Support\OpenWa\OpenWaWebhookEvents;

it('reconoce onMessage y message.received como chat inbound', function (): void {
    expect(OpenWaWebhookEvents::isInboundChat('message.received'))->toBeTrue()
        ->and(OpenWaWebhookEvents::isInboundChat('onMessage'))->toBeTrue()
        ->and(OpenWaWebhookEvents::isInboundChat('message'))->toBeTrue();
});

it('ignora acks, presence y eventos vacíos', function (): void {
    expect(OpenWaWebhookEvents::isInboundChat(''))->toBeFalse()
        ->and(OpenWaWebhookEvents::isInboundChat('message.ack'))->toBeFalse()
        ->and(OpenWaWebhookEvents::isInboundChat('presence.update'))->toBeFalse();
});
