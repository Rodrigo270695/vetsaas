<?php

declare(strict_types=1);

use App\Services\Reviews\TenantProductReviewService;

it('arma la línea profesional de cargo y clínica', function (): void {
    $service = new TenantProductReviewService;

    expect($service->roleLine('Recepcionista', 'Clínica Sol Bello'))
        ->toBe('Recepcionista de Clínica Sol Bello')
        ->and($service->sanitizeComment("  Hola   <b>mundo</b> \n nuevo  "))
        ->toBe('Hola mundo nuevo');
});
