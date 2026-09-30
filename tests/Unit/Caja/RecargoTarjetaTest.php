<?php

declare(strict_types=1);

use App\Support\Venta\RecargoTarjeta;

it('calcula el recargo sobre la parte pagada con tarjeta', function (): void {
    expect(RecargoTarjeta::monto(100, 5))->toBe(5.0)
        ->and(RecargoTarjeta::monto(100, 4))->toBe(4.0)
        ->and(RecargoTarjeta::monto(60, 5))->toBe(3.0)
        ->and(RecargoTarjeta::monto(100, 0))->toBe(0.0)
        ->and(RecargoTarjeta::clamp(150))->toBe(100.0);
});

it('suma el recargo al pago con tarjeta y deja el precio del producto', function (): void {
    $pagos = [[
        'metodo' => 'tarjeta',
        'monto' => 100.0,
        'monto_recibido' => null,
        'vuelto' => null,
    ]];

    $out = RecargoTarjeta::aplicarAlPago($pagos, 5);

    expect($out['aplicado'])->toBeTrue()
        ->and($out['monto'])->toBe(5.0)
        ->and($out['pagos'][0]['monto'])->toBe(105.0);
});

it('en un pago mixto recarga solo el tramo de tarjeta', function (): void {
    $pagos = [
        ['metodo' => 'efectivo', 'monto' => 40.0, 'monto_recibido' => 40.0, 'vuelto' => 0.0],
        ['metodo' => 'tarjeta', 'monto' => 60.0, 'monto_recibido' => null, 'vuelto' => null],
    ];

    $out = RecargoTarjeta::aplicarAlPago($pagos, 5);

    expect($out['monto'])->toBe(3.0)
        ->and($out['pagos'][0]['monto'])->toBe(40.0)
        ->and($out['pagos'][1]['monto'])->toBe(63.0);
});

it('no altera el cobro si no es con tarjeta', function (): void {
    $pagos = [[
        'metodo' => 'efectivo',
        'monto' => 100.0,
        'monto_recibido' => 100.0,
        'vuelto' => 0.0,
    ]];

    $out = RecargoTarjeta::aplicarAlPago($pagos, 5);

    expect($out['aplicado'])->toBeFalse()
        ->and($out['monto'])->toBe(0.0)
        ->and($out['pagos'][0]['monto'])->toBe(100.0);
});
