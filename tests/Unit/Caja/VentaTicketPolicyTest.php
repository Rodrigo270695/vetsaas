<?php

use App\Models\ClinicSetting;
use App\Models\Venta;
use App\Support\Caja\VentaTicketPolicy;

it('permite ticket en venta pagada sin FEL activo', function (): void {
    $venta = new Venta(['estado' => Venta::ESTADO_PAGADO, 'fel_estado' => Venta::FEL_SIN_CPE]);
    $clinic = new ClinicSetting(['emite_comprobantes_sunat' => false, 'nubefact_configurado' => false]);

    expect(VentaTicketPolicy::puedeImprimir($venta, $clinic, null))->toBeTrue();
});

it('permite ticket tras CPE emitido cuando FEL está configurado', function (): void {
    $venta = new Venta(['estado' => Venta::ESTADO_PAGADO, 'fel_estado' => Venta::FEL_EMITIDO]);
    $clinic = new ClinicSetting([
        'emite_comprobantes_sunat' => true,
        'nubefact_configurado' => true,
    ]);

    expect(VentaTicketPolicy::puedeImprimir($venta, $clinic, null))->toBeTrue();
});
