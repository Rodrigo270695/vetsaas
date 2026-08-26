<?php

declare(strict_types=1);

use App\Models\FelDocument;
use App\Models\Venta;
use App\Services\Fel\ApisunatClient;
use App\Services\Fel\FelDocumentStatusSyncService;

it('mapea estados Lucode a estados locales de forma estricta', function (): void {
    $service = new FelDocumentStatusSyncService(app(ApisunatClient::class));

    expect($service->mapSunatToLocal('ACEPTADO'))->toBe([
        'fel_document' => FelDocument::ESTADO_EMITIDO,
        'venta' => Venta::FEL_EMITIDO,
    ]);

    expect($service->mapSunatToLocal('PENDIENTE'))->toBe([
        'fel_document' => FelDocument::ESTADO_PENDIENTE,
        'venta' => Venta::FEL_PENDIENTE,
    ]);

    expect($service->mapSunatToLocal('RECHAZADO'))->toBe([
        'fel_document' => FelDocument::ESTADO_RECHAZADO,
        'venta' => Venta::FEL_RECHAZADO,
    ]);

    expect($service->mapSunatToLocal('EXCEPCION'))->toBe([
        'fel_document' => FelDocument::ESTADO_RECHAZADO,
        'venta' => Venta::FEL_RECHAZADO,
    ]);

    expect($service->mapSunatToLocal('desconocido'))->toBe([
        'fel_document' => FelDocument::ESTADO_PENDIENTE,
        'venta' => Venta::FEL_PENDIENTE,
    ]);
});

it('extrae estado desde payload de consulta /status', function (): void {
    $client = app(ApisunatClient::class);

    expect($client->extraerEstado([
        'success' => true,
        'payload' => ['estado' => 'pendiente'],
    ]))->toBe('PENDIENTE');

    expect($client->extraerEstado([
        'success' => true,
        'payload' => [],
    ]))->toBeNull();
});
