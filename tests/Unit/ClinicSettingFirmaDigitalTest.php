<?php

use App\Models\ClinicSetting;

it('solo acepta claves de PDF clínicas para la firma digital', function (): void {
    $setting = new ClinicSetting;
    $setting->firma_digital_documentos = ['receta', 'venta_ticket', 'consulta', 'receta'];

    expect($setting->firmaDigitalDocumentos())->toBe(['receta', 'consulta']);
    expect($setting->usaFirmaDigitalEn('receta'))->toBeTrue();
    expect($setting->usaFirmaDigitalEn('autorizacion'))->toBeFalse();
});

it('sin documentos configurados no aplica la firma', function (): void {
    $setting = new ClinicSetting;
    $setting->firma_digital_documentos = null;

    expect($setting->firmaDigitalDocumentos())->toBe([]);
    expect($setting->usaFirmaDigitalEn('receta'))->toBeFalse();
});
