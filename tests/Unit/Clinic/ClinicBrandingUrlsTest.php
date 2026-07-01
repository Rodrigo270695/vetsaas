<?php

declare(strict_types=1);

use App\Support\Clinic\ClinicBrandingUrls;
use Tests\TestCase;

uses(TestCase::class);

it('usa logo.png como fallback por defecto', function (): void {
    expect(ClinicBrandingUrls::default())->toContain('logo.png');
});

it('incluye colores de marca en el payload compartido', function (): void {
    $payload = ClinicBrandingUrls::sharedPayload(null);

    expect($payload)
        ->toHaveKeys(['logo_url', 'updated_at', 'color_primario', 'color_secundario'])
        ->and($payload['color_primario'])->toBeNull()
        ->and($payload['color_secundario'])->toBeNull();
});
