<?php

declare(strict_types=1);

use App\Services\Notifications\ReminderMessageBuilder;
use Carbon\Carbon;

it('incluye el motivo en mensajes de cita cuando existe', function (): void {
    $builder = new ReminderMessageBuilder;
    $inicio = Carbon::parse('2026-08-20 09:00:00', config('app.timezone'));

    $creada = $builder->citaCreada('OpenVet', 'Jairo', 'Kaizer', $inicio, 'VACUNACION CACHORRO');
    $recordatorio = $builder->cita48h('OpenVet', 'Jairo', 'Kaizer', $inicio, 'VACUNACION CACHORRO');
    $dosHoras = $builder->cita2h('OpenVet', 'Jairo', 'Kaizer', $inicio, 'VACUNACION CACHORRO');

    expect($creada)->toContain('📋 Motivo: *VACUNACION CACHORRO*')
        ->and($recordatorio)->toContain('📋 Motivo: *VACUNACION CACHORRO*')
        ->and($dosHoras)->toContain('📋 Motivo: *VACUNACION CACHORRO*');
});

it('omite la línea de motivo si viene vacío', function (): void {
    $builder = new ReminderMessageBuilder;
    $inicio = Carbon::parse('2026-08-20 09:00:00', config('app.timezone'));

    $sinMotivo = $builder->citaCreada('OpenVet', 'Jairo', 'Kaizer', $inicio, null);
    $vacio = $builder->citaCreada('OpenVet', 'Jairo', 'Kaizer', $inicio, '   ');

    expect($sinMotivo)->not->toContain('Motivo:')
        ->and($vacio)->not->toContain('Motivo:');
});

it('usa el texto de fábrica cuando no hay tabla de plantillas', function (): void {
    $builder = new ReminderMessageBuilder;
    $inicio = Carbon::parse('2026-08-20 09:00:00', config('app.timezone'));

    $msg = $builder->citaCreada('OpenVet', 'Jairo', 'Kaizer', $inicio, null);

    expect($msg)->toContain('Registramos la cita de *Kaizer*')
        ->and($msg)->toContain('OpenVet');
});
