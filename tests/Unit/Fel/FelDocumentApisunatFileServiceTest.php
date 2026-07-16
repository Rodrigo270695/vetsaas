<?php

declare(strict_types=1);

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Services\Fel\ApisunatClient;
use App\Services\Fel\FelDocumentApisunatFileService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

it('extrae enlaces Lucode desde apisunat_payload', function (): void {
    $doc = new FelDocument([
        'url_pdf' => 'https://legacy.test/old.pdf',
        'url_xml' => null,
        'apisunat_payload' => [
            'payload' => [
                'pdf' => ['ticket' => 'https://sandbox.apisunat.pe/pdf/ticket/abc'],
                'xml' => 'https://sandbox.apisunat.pe/xml/abc.xml',
                'cdr' => 'https://sandbox.apisunat.pe/cdr/abc.xml',
            ],
        ],
    ]);

    $service = new FelDocumentApisunatFileService(new ApisunatClient);
    $enlaces = $service->enlaces($doc);

    expect($enlaces['pdf'])->toBe('https://sandbox.apisunat.pe/pdf/ticket/abc')
        ->and($enlaces['pdf_a4'])->toBe('https://sandbox.apisunat.pe/pdf/a4/abc')
        ->and($enlaces['xml'])->toBe('https://sandbox.apisunat.pe/xml/abc.xml')
        ->and($enlaces['cdr'])->toBe('https://sandbox.apisunat.pe/cdr/abc.xml');
});

it('descarga archivos Lucode con token APISUNAT de la clínica', function (): void {
    Http::fake([
        'sandbox.apisunat.pe/pdf/ticket/abc' => Http::response('%PDF-1.4 fake', 200),
    ]);

    $doc = new FelDocument([
        'url_pdf' => 'https://sandbox.apisunat.pe/pdf/ticket/abc',
    ]);

    $clinic = new ClinicSetting([
        'apisunat_configurado' => true,
        'apisunat_token_enc' => Crypt::encryptString('token-lucode-test'),
        'apisunat_mode' => 'sandbox',
    ]);

    $binary = (new FelDocumentApisunatFileService(new ApisunatClient))
        ->descargar($doc, $clinic, 'pdf_ticket');

    expect($binary)->toStartWith('%PDF');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://sandbox.apisunat.pe/pdf/ticket/abc'
            && $request->hasHeader('Authorization', 'Bearer token-lucode-test');
    });
});
