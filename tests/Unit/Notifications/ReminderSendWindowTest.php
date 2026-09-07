<?php

declare(strict_types=1);

use App\Support\Notifications\ReminderSendWindow;
use Illuminate\Support\Carbon;

it('reprograma a las 09:00 si el scan corre de madrugada', function (): void {
    $tz = 'America/Lima';
    config(['app.timezone' => $tz]);
    $now = Carbon::parse('2026-09-07 00:05:00', $tz);

    expect(ReminderSendWindow::enqueueAt($now)->timezone($tz)->format('Y-m-d H:i'))
        ->toBe('2026-09-07 09:00');
});

it('envía de inmediato si ya pasó las 09:00', function (): void {
    $tz = 'America/Lima';
    config(['app.timezone' => $tz]);
    $now = Carbon::parse('2026-09-07 10:12:00', $tz);

    expect(ReminderSendWindow::enqueueAt($now)->timezone($tz)->format('Y-m-d H:i'))
        ->toBe('2026-09-07 10:12');
});
