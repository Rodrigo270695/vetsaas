<?php

declare(strict_types=1);

use App\Models\CajaSesion;
use Illuminate\Support\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('permite reaperturar solo antes de cumplirse 24 horas del cierre', function (): void {
    Carbon::setTestNow('2026-09-07 09:59:59');
    expect(CajaSesion::estaCierreDentroDeVentanaReabrir(
        CajaSesion::ESTADO_CERRADA,
        '2026-09-06 10:00:00',
    ))->toBeTrue();

    Carbon::setTestNow('2026-09-07 10:00:00');
    expect(CajaSesion::estaCierreDentroDeVentanaReabrir(
        CajaSesion::ESTADO_CERRADA,
        '2026-09-06 10:00:00',
    ))->toBeFalse();
});

it('no permite reaperturar una sesión abierta o sin fecha de cierre', function (): void {
    expect(CajaSesion::estaCierreDentroDeVentanaReabrir(
        CajaSesion::ESTADO_ABIERTA,
        '2026-09-06 10:00:00',
    ))->toBeFalse();

    expect(CajaSesion::estaCierreDentroDeVentanaReabrir(
        CajaSesion::ESTADO_CERRADA,
        null,
    ))->toBeFalse();
});
