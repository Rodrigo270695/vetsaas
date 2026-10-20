<?php

declare(strict_types=1);

use App\Models\TenantWhatsAppSession;
use App\Support\OpenWa\OpenWaSessionStatusClock;
use Illuminate\Support\Carbon;

it('conserva status_changed_at si el estado remoto no cambió', function (): void {
    $session = new TenantWhatsAppSession([
        'status' => 'initializing',
        'status_changed_at' => Carbon::parse('2026-10-17 08:00:00'),
    ]);

    $next = OpenWaSessionStatusClock::nextChangedAt($session, 'initializing');

    expect($next->equalTo(Carbon::parse('2026-10-17 08:00:00')))->toBeTrue();
});

it('reinicia el reloj de boot cuando cambia el estado', function (): void {
    Carbon::setTestNow('2026-10-20 10:00:00');

    $session = new TenantWhatsAppSession([
        'status' => 'initializing',
        'status_changed_at' => Carbon::parse('2026-10-17 08:00:00'),
    ]);

    $next = OpenWaSessionStatusClock::nextChangedAt($session, 'qr_ready');

    expect($next->equalTo(Carbon::parse('2026-10-20 10:00:00')))->toBeTrue();

    Carbon::setTestNow();
});
