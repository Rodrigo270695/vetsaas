<?php

use App\Models\FelDocument;
use App\Support\Fel\FelDocumentApisunatModeResolver;

it('resuelve modo desde columna apisunat_mode', function (): void {
    $doc = new FelDocument(['apisunat_mode' => 'produccion']);

    expect(FelDocumentApisunatModeResolver::resolve($doc))->toBe('produccion');
});

it('resuelve modo desde metadata _vetsaas_emission_mode del payload', function (): void {
    $doc = new FelDocument([
        'apisunat_payload' => [
            '_vetsaas_emission_mode' => 'sandbox',
            'success' => true,
        ],
    ]);

    expect(FelDocumentApisunatModeResolver::resolve($doc))->toBe('sandbox');
});

it('resuelve modo desde _vetsaas_api_base sandbox', function (): void {
    $doc = new FelDocument([
        'apisunat_payload' => [
            '_vetsaas_api_base' => 'https://sandbox.apisunat.pe/api/v3/documents',
        ],
        'nubefact_id' => 'apisunat:ACEPTADO',
    ]);

    expect(FelDocumentApisunatModeResolver::resolve($doc))->toBe('sandbox');
});

it('resuelve modo desde URLs de producción en el payload', function (): void {
    $doc = new FelDocument([
        'apisunat_payload' => [
            'payload' => [
                'pdf' => ['ticket' => 'https://app.apisunat.pe/pdf/ticket/demo'],
            ],
        ],
        'nubefact_id' => 'apisunat:ACEPTADO',
    ]);

    expect(FelDocumentApisunatModeResolver::resolve($doc))->toBe('produccion');
});

it('identifica documentos APISUNAT por nubefact_id', function (): void {
    $doc = new FelDocument(['nubefact_id' => 'apisunat:ACEPTADO']);

    expect(FelDocumentApisunatModeResolver::isApisunatDocument($doc))->toBeTrue();
});

it('retorna null para comprobantes legacy sin APISUNAT', function (): void {
    $doc = new FelDocument(['nubefact_id' => 'NUBEFACT-LEGACY-001']);

    expect(FelDocumentApisunatModeResolver::resolve($doc))->toBeNull();
});
