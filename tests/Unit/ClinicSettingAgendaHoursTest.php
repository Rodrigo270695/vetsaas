<?php

use App\Models\ClinicSetting;

it('usa el rango por defecto cuando la clínica aún no configuró su agenda', function (): void {
    $setting = new ClinicSetting;
    $setting->horario_atencion = [];

    expect($setting->agendaHoraInicio())->toBe('07:00')
        ->and($setting->agendaHoraFin())->toBe('20:00');
});

it('devuelve el rango de agenda configurado por la clínica', function (): void {
    $setting = new ClinicSetting;
    $setting->horario_atencion = [
        'agenda_hora_inicio' => '05:00',
        'agenda_hora_fin' => '23:00',
    ];

    expect($setting->agendaHoraInicio())->toBe('05:00')
        ->and($setting->agendaHoraFin())->toBe('23:00');
});
