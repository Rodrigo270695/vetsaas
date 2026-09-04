<?php

declare(strict_types=1);

use App\Services\Notifications\AppointmentReminderScanner;
use Carbon\Carbon;

it('el recordatorio de 1 día cubre la misma hora del día siguiente', function (): void {
    $now = Carbon::parse('2026-09-03 22:17:00', 'America/Lima');
    $inicio = Carbon::parse('2026-09-04 22:17:00', 'America/Lima');

    expect(AppointmentReminderScanner::inWindow($inicio, $now->copy()->addDays(1)))->toBeTrue();
});

it('no dispara el de 1 día si falta más de la ventana', function (): void {
    $now = Carbon::parse('2026-09-03 10:17:00', 'America/Lima');
    $inicio = Carbon::parse('2026-09-04 22:17:00', 'America/Lima');

    expect(AppointmentReminderScanner::inWindow($inicio, $now->copy()->addDays(1)))->toBeFalse();
});

it('el de 2 horas cubre inicio ≈ ahora + 2 h', function (): void {
    $now = Carbon::parse('2026-09-04 20:17:00', 'America/Lima');
    $inicio = Carbon::parse('2026-09-04 22:17:00', 'America/Lima');

    expect(AppointmentReminderScanner::inWindow($inicio, $now->copy()->addHours(2)))->toBeTrue();
});
