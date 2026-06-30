<?php

use App\Services\Sales\SalesBotService;

it('calcula índice de novedad con uuid sin error numérico', function (): void {
    $uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

    $index = SalesBotService::noveltyIndexForConversation($uuid, 5);

    expect($index)->toBeGreaterThanOrEqual(0)
        ->and($index)->toBeLessThan(5);
});

it('devuelve cero si no hay novedades', function (): void {
    expect(SalesBotService::noveltyIndexForConversation('any-id', 0))->toBe(0);
});
