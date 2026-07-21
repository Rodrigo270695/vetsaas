<?php

use App\Models\ClinicSetting;

it('conserva siete días como recordatorio de vacuna predeterminado', function (): void {
    $setting = new ClinicSetting;

    expect($setting->recordatorioVacunaDiasAntesOpciones())->toBe([7]);
});

it('normaliza las anticipaciones permitidas sin duplicados', function (): void {
    $setting = new ClinicSetting;
    $setting->forceFill([
        'recordatorio_vacuna_dias_antes_opciones' => [30, 1, 7, 1, 15, '3'],
    ]);

    expect($setting->recordatorioVacunaDiasAntesOpciones())->toBe([1, 3, 7, 30]);
});

it('conserva dos días para citas y permite varios avisos', function (): void {
    $setting = new ClinicSetting;

    expect($setting->recordatorioCitaDiasAntesOpciones())->toBe([2]);

    $setting->forceFill([
        'recordatorio_cita_dias_antes_opciones' => [30, 2, 1, 2, 9],
    ]);

    expect($setting->recordatorioCitaDiasAntesOpciones())->toBe([1, 2, 30]);
});
