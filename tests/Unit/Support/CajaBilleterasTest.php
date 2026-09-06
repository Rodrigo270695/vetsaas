<?php

declare(strict_types=1);

use App\Support\Caja\CajaBilleteras;

it('normaliza montos de yape, plin y transferencia', function (): void {
    expect(CajaBilleteras::normalize([
        'yape' => '10.5',
        'plin' => 3,
        'otro' => 99,
    ]))->toBe([
        'yape' => '10.50',
        'plin' => '3.00',
        'transferencia' => '0.00',
    ]);
});

it('rellena ceros si no hay saldos', function (): void {
    expect(CajaBilleteras::normalize(null))->toBe([
        'yape' => '0.00',
        'plin' => '0.00',
        'transferencia' => '0.00',
    ]);
});
