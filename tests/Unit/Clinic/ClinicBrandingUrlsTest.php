<?php

declare(strict_types=1);

use App\Support\Clinic\ClinicBrandingUrls;
use Tests\TestCase;

uses(TestCase::class);

it('usa logo.png como fallback por defecto', function (): void {
    expect(ClinicBrandingUrls::default())->toContain('logo.png');
});
