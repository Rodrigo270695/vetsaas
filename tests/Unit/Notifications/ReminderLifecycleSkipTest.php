<?php

declare(strict_types=1);

use App\Support\Notifications\ReminderLifecycleSkip;
use Carbon\Carbon;

it('no salta el recordatorio de 2 h aunque acaben de registrar la cita', function (): void {
    $inicio = Carbon::parse('2026-09-19 18:34:00', 'America/Lima');
    $alta = Carbon::parse('2026-09-19 16:31:00', 'America/Lima');

    expect(ReminderLifecycleSkip::shouldSkip($inicio, 'cita_2h', $alta))->toBeFalse()
        ->and(ReminderLifecycleSkip::shouldSkip($inicio, 'grooming_2h', $alta))->toBeFalse()
        ->and(ReminderLifecycleSkip::shouldSkip($inicio, 'hotel_2h', $alta))->toBeFalse();
});

it('salta el recordatorio de 1 día si el alta ocurrió en esa misma ventana', function (): void {
    $inicio = Carbon::parse('2026-09-20 16:30:00', 'America/Lima');
    $alta = Carbon::parse('2026-09-19 16:30:00', 'America/Lima');

    expect(ReminderLifecycleSkip::shouldSkip($inicio, 'cita_1d', $alta))->toBeTrue();
});

it('no salta el de 1 día si agendaron con más holgura', function (): void {
    $inicio = Carbon::parse('2026-09-20 18:34:00', 'America/Lima');
    $alta = Carbon::parse('2026-09-19 15:00:00', 'America/Lima');

    expect(ReminderLifecycleSkip::shouldSkip($inicio, 'cita_1d', $alta))->toBeFalse();
});
