<?php

declare(strict_types=1);

use App\Models\CajaEgreso;

it('normaliza el origen del egreso y cae a efectivo si es inválido', function (): void {
    expect(CajaEgreso::normalizeMedio('YAPE'))->toBe(CajaEgreso::MEDIO_YAPE);
    expect(CajaEgreso::normalizeMedio('plin'))->toBe(CajaEgreso::MEDIO_PLIN);
    expect(CajaEgreso::normalizeMedio('tarjeta'))->toBe(CajaEgreso::MEDIO_EFECTIVO);
    expect(CajaEgreso::normalizeMedio(null))->toBe(CajaEgreso::MEDIO_EFECTIVO);
});

it('etiqueta los orígenes de egreso', function (): void {
    expect(CajaEgreso::labelMedio('yape'))->toBe('Yape');
    expect(CajaEgreso::labelMedio('efectivo'))->toBe('Efectivo');
    expect(CajaEgreso::labelMedio('transferencia'))->toBe('Transferencia');
});
