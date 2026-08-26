<?php

declare(strict_types=1);

use App\Services\Fel\ApisunatCdrMotivoExtractor;
use App\Services\Fel\ApisunatClient;

it('extrae código y descripción de rechazo desde el CDR SUNAT', function (): void {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ar:ApplicationResponse xmlns:ar="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2">
  <cac:DocumentResponse>
    <cac:Response>
      <cbc:ReferenceID>B001-00000003</cbc:ReferenceID>
      <cbc:ResponseCode>2325</cbc:ResponseCode>
      <cbc:Description>El RUC del emisor no está autorizado a emitir comprobantes electrónicos</cbc:Description>
    </cac:Response>
  </cac:DocumentResponse>
</ar:ApplicationResponse>
XML;

    expect(ApisunatCdrMotivoExtractor::fromXml($xml))
        ->toBe('[2325] El RUC del emisor no está autorizado a emitir comprobantes electrónicos');
});

it('ignora ResponseCode 0 (aceptado) en el CDR', function (): void {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<root>
  <DocumentResponse>
    <Response>
      <ResponseCode>0</ResponseCode>
      <Description>La Factura numero F001-1, ha sido aceptada</Description>
    </Response>
  </DocumentResponse>
</root>
XML;

    expect(ApisunatCdrMotivoExtractor::fromXml($xml))->toBeNull();
});

it('prioriza faults/notes sobre el mensaje genérico de Lucode', function (): void {
    $client = new ApisunatClient;

    $motivo = $client->extraerMotivoRechazo([
        'success' => true,
        'message' => 'El comprobante presenta errores o datos incorrectos',
        'payload' => [
            'estado' => 'RECHAZADO',
            'faults' => [
                ['code' => '2324', 'message' => 'Receptor no existe en padrón'],
            ],
        ],
    ]);

    expect($motivo)->toContain('Receptor no existe en padrón')
        ->and($client->esMensajeGenericoRechazo($motivo))->toBeFalse();
});

it('detecta mensajes genéricos de rechazo Lucode', function (): void {
    $client = new ApisunatClient;

    expect($client->esMensajeGenericoRechazo(
        'El documento fue rechazado por SUNAT, compruebe sus datos o comuníquese con soporte',
    ))->toBeTrue();

    expect($client->esMensajeGenericoRechazo(
        '[2325] El RUC del emisor no está autorizado',
    ))->toBeFalse();
});
