<?php

declare(strict_types=1);

use App\Support\ClinicBot\ClinicBotWebhookTrafficGuard;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    config([
        'bot-ia.webhook_circuit_enabled' => true,
        'bot-ia.webhook_rate_limit_per_minute' => 5,
        'bot-ia.webhook_circuit_ttl_seconds' => 120,
    ]);
});

it('abre el circuito cuando se supera el límite de hits por minuto', function (): void {
    $guard = app(ClinicBotWebhookTrafficGuard::class);

    for ($i = 0; $i < 5; $i++) {
        expect($guard->shouldRejectAfterHit())->toBeFalse();
    }

    expect($guard->shouldRejectAfterHit())->toBeTrue()
        ->and($guard->isCircuitOpen())->toBeTrue()
        ->and($guard->snapshot()['circuit_open'])->toBeTrue()
        ->and($guard->snapshot()['hits_1m'])->toBeGreaterThanOrEqual(6);
});

it('sigue rechazando mientras el circuito está abierto', function (): void {
    $guard = app(ClinicBotWebhookTrafficGuard::class);

    for ($i = 0; $i < 6; $i++) {
        $guard->shouldRejectAfterHit();
    }

    expect($guard->shouldRejectAfterHit())->toBeTrue();
});
