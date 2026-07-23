<?php

declare(strict_types=1);

it('responde 503 si SALESBOT_WEBHOOK_SECRET no está configurado', function (): void {
    config([
        'salesbot.enabled' => true,
        'salesbot.webhook_secret' => '',
    ]);

    $this->postJson('/api/webhooks/sales-bot', [
        'event' => 'message.received',
        'sessionId' => 'orvae-platform',
        'data' => [
            'body' => 'hola',
            'from' => '51999999999@c.us',
            'fromMe' => false,
            'type' => 'chat',
        ],
    ], [
        'X-Webhook-Secret' => 'cualquier-cosa',
    ])->assertStatus(503)->assertJson(['error' => 'Webhook secret not configured']);
});

it('rechaza sales-bot sin header de secreto cuando sí está configurado', function (): void {
    config([
        'salesbot.enabled' => true,
        'salesbot.webhook_secret' => 'test-salesbot-secret',
    ]);

    $this->postJson('/api/webhooks/sales-bot', [
        'event' => 'message.received',
        'sessionId' => 'orvae-platform',
        'data' => [
            'body' => 'hola',
            'from' => '51999999999@c.us',
            'fromMe' => false,
            'type' => 'chat',
        ],
    ])->assertUnauthorized();
});
