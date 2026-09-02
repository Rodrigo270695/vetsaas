<?php

declare(strict_types=1);

use App\Support\Clinica\PropietarioSearch;

it('parte nombre y apellido en tokens independientes', function (): void {
    expect(PropietarioSearch::tokens('  Rodrigo   Granja  '))
        ->toBe(['Rodrigo', 'Granja']);
});

it('acepta DNI con o sin espacios o guiones', function (): void {
    expect(PropietarioSearch::tokens('77344586'))->toBe(['77344586']);
    expect(PropietarioSearch::tokens('7734-4586'))->toBe(['77344586']);
});

it('ignora letras sueltas que no aportan', function (): void {
    expect(PropietarioSearch::tokens('rodrigo g'))->toBe(['rodrigo']);
});
