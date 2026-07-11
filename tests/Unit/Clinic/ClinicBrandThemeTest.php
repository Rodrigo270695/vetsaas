<?php

declare(strict_types=1);

use App\Support\Clinic\ClinicBrandTheme;

it('generates brand css block from clinic colors', function (): void {
    $css = ClinicBrandTheme::rootCssBlock('#0A396B', '#2C59DD');

    expect($css)
        ->not->toBeNull()
        ->toContain('--brand-600: #0A396B')
        ->toContain('--brand-700:')
        ->toContain('--primary-foreground:');
});

it('returns null when no colors are configured', function (): void {
    expect(ClinicBrandTheme::rootCssBlock(null, null))->toBeNull();
});
