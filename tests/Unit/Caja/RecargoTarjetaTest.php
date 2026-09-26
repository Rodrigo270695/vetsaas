<?php

declare(strict_types=1);

use App\Support\Venta\RecargoTarjeta;
use App\Support\Venta\VentaTotales;

it('calcula el recargo sobre la parte pagada con tarjeta', function (): void {
    expect(RecargoTarjeta::monto(100, 5))->toBe(5.0)
        ->and(RecargoTarjeta::monto(100, 4))->toBe(4.0)
        ->and(RecargoTarjeta::monto(60, 5))->toBe(3.0)
        ->and(RecargoTarjeta::monto(100, 0))->toBe(0.0)
        ->and(RecargoTarjeta::clamp(150))->toBe(100.0);
});

it('suma el 5% al total cuando toda la venta se cobra con tarjeta', function (): void {
    $lineas = [[
        'cantidad' => 1,
        'precio_lista' => 100,
        'subtotal' => round(100 / 1.18, 2),
        'descuento_pct' => 0,
    ]];
    $pagos = [[
        'metodo' => 'tarjeta',
        'monto' => 100.0,
        'monto_recibido' => null,
        'vuelto' => null,
    ]];

    $out = RecargoTarjeta::anexar($lineas, $pagos, 5, 18, true, 'gravado');
    $totales = VentaTotales::fromLineas($out['lineas'], 18, true);

    expect($out['aplicado'])->toBeTrue()
        ->and($out['lineas'])->toHaveCount(1)
        ->and($out['lineas'][0]['precio_lista'])->toBe(105.0)
        ->and($out['lineas'][0])->not->toHaveKey('descripcion_snapshot')
        ->and($totales['total'])->toBe(105.0)
        ->and($out['pagos'][0]['monto'])->toBe(105.0);
});

it('en un pago mixto recarga solo el tramo de tarjeta', function (): void {
    $lineas = [[
        'cantidad' => 1,
        'precio_lista' => 100,
        'subtotal' => round(100 / 1.18, 2),
        'descuento_pct' => 0,
    ]];
    $pagos = [
        ['metodo' => 'efectivo', 'monto' => 40.0, 'monto_recibido' => 40.0, 'vuelto' => 0.0],
        ['metodo' => 'tarjeta', 'monto' => 60.0, 'monto_recibido' => null, 'vuelto' => null],
    ];

    $out = RecargoTarjeta::anexar($lineas, $pagos, 5, 18, true, 'gravado');
    $totales = VentaTotales::fromLineas($out['lineas'], 18, true);

    expect($out['lineas'])->toHaveCount(1)
        ->and($out['lineas'][0]['precio_lista'])->toBe(103.0)
        ->and($totales['total'])->toBe(103.0)
        ->and($out['pagos'][0]['monto'])->toBe(40.0)
        ->and($out['pagos'][1]['monto'])->toBe(63.0);
});

it('no altera el total si el cobro no es con tarjeta', function (): void {
    $lineas = [[
        'cantidad' => 1,
        'precio_lista' => 100,
        'subtotal' => round(100 / 1.18, 2),
        'descuento_pct' => 0,
    ]];
    $pagos = [[
        'metodo' => 'efectivo',
        'monto' => 100.0,
        'monto_recibido' => 100.0,
        'vuelto' => 0.0,
    ]];

    $out = RecargoTarjeta::anexar($lineas, $pagos, 5, 18, true, 'gravado');

    expect($out['aplicado'])->toBeFalse()
        ->and($out['lineas'])->toHaveCount(1)
        ->and($out['pagos'][0]['monto'])->toBe(100.0);
});

it('reparte el recargo entre los productos sin crear otra línea', function (): void {
    $lineas = [
        [
            'cantidad' => 1,
            'precio_lista' => 10,
            'subtotal' => round(10 / 1.18, 2),
            'descuento_pct' => 0,
            'descripcion_snapshot' => 'Ropa',
            'igv_tipo_snapshot' => 'gravado',
        ],
        [
            'cantidad' => 1,
            'precio_lista' => 5,
            'subtotal' => round(5 / 1.18, 2),
            'descuento_pct' => 0,
            'descripcion_snapshot' => 'Collar',
            'igv_tipo_snapshot' => 'gravado',
        ],
    ];
    $pagos = [[
        'metodo' => 'tarjeta',
        'monto' => 15.0,
        'monto_recibido' => null,
        'vuelto' => null,
    ]];

    $out = RecargoTarjeta::anexar($lineas, $pagos, 4, 18, true, 'gravado');
    $totales = VentaTotales::fromLineas($out['lineas'], 18, true);
    $descripciones = array_column($out['lineas'], 'descripcion_snapshot');

    expect($out['lineas'])->toHaveCount(2)
        ->and($descripciones)->toBe(['Ropa', 'Collar'])
        ->and($out['lineas'][0]['precio_lista'])->toBe(10.4)
        ->and($out['lineas'][1]['precio_lista'])->toBe(5.2)
        ->and($totales['total'])->toBe(15.6)
        ->and($out['pagos'][0]['monto'])->toBe(15.6);
});
