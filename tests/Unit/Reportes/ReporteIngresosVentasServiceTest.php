<?php

declare(strict_types=1);

use App\Models\FelSerie;
use App\Services\Reportes\ReporteIngresosVentasService;

it('normaliza tipos y métodos de pago del reporte de ingresos', function (): void {
    $service = new ReporteIngresosVentasService;

    expect($service->normalizeTipos(null))->toBe(['ticket', 'boleta', 'factura'])
        ->and($service->normalizeTipos(['boleta', 'BOLETA', 'x']))->toBe(['boleta'])
        ->and($service->normalizeMetodos(['yape', 'efectivo', 'bitcoin']))->toBe(['yape', 'efectivo']);
});

it('clasifica ticket boleta y factura', function (): void {
    $service = new ReporteIngresosVentasService;

    expect($service->tipoKey(null))->toBe('ticket')
        ->and($service->tipoKey(FelSerie::TIPO_TICKET))->toBe('ticket')
        ->and($service->tipoKey(FelSerie::TIPO_BOLETA))->toBe('boleta')
        ->and($service->tipoKey(FelSerie::TIPO_FACTURA))->toBe('factura');
});

it('usa esta semana cuando no hay fechas', function (): void {
    config(['app.timezone' => 'America/Lima']);
    $service = new ReporteIngresosVentasService;
    [$periodo, $start, $end] = $service->resolveRange(null, null, null);

    expect($periodo)->toBe('semana')
        ->and($start->toDateString())->toBe(now('America/Lima')->startOfWeek()->toDateString())
        ->and($end->toDateString())->toBe(now('America/Lima')->endOfWeek()->toDateString());
});
