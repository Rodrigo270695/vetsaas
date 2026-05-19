<?php

use App\Support\Fel\SunatSerieCodigo;

it('normaliza series SUNAT sin guión', function (): void {
    expect(SunatSerieCodigo::normalizar('b-001'))->toBe('B001');
    expect(SunatSerieCodigo::normalizar(' B001 '))->toBe('B001');
    expect(SunatSerieCodigo::normalizar('f001'))->toBe('F001');
});

it('valida prefijos de boleta y factura', function (): void {
    expect(SunatSerieCodigo::esBoletaValida('B001'))->toBeTrue();
    expect(SunatSerieCodigo::esBoletaValida('F001'))->toBeFalse();
    expect(SunatSerieCodigo::esFacturaValida('F001'))->toBeTrue();
});
