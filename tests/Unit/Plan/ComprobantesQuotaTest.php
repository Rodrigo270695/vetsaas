<?php

declare(strict_types=1);

use App\Support\Plan\ComprobantesQuota;

it('calcula cupo anual como doce veces el mensual', function (): void {
    expect(ComprobantesQuota::includedLimit(100, 'anual'))->toBe(1200)
        ->and(ComprobantesQuota::includedLimit(100, 'mensual'))->toBe(100);
});

it('calcula cargo adicional por bloques de 100 en exceso', function (): void {
    expect(ComprobantesQuota::overageBreakdown(100, 100))
        ->toMatchArray(['units' => 0, 'blocks' => 0, 'cost' => 0.0]);

    expect(ComprobantesQuota::overageBreakdown(101, 100))
        ->toMatchArray(['units' => 1, 'blocks' => 1, 'cost' => 8.0]);

    expect(ComprobantesQuota::overageBreakdown(200, 100))
        ->toMatchArray(['units' => 100, 'blocks' => 1, 'cost' => 8.0]);

    expect(ComprobantesQuota::overageBreakdown(201, 100))
        ->toMatchArray(['units' => 101, 'blocks' => 2, 'cost' => 16.0]);
});

it('asigna semaforo segun porcentaje de uso', function (): void {
    expect(ComprobantesQuota::semaphore(10, 100))->toBe('ok')
        ->and(ComprobantesQuota::semaphore(80, 100))->toBe('caution')
        ->and(ComprobantesQuota::semaphore(95, 100))->toBe('warning')
        ->and(ComprobantesQuota::semaphore(100, 100))->toBe('over')
        ->and(ComprobantesQuota::semaphore(150, 100))->toBe('over')
        ->and(ComprobantesQuota::semaphore(5, null, true))->toBe('unlimited');
});
