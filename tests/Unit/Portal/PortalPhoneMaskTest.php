<?php

declare(strict_types=1);

use App\Support\Portal\PortalPhoneMask;

it('enmascara el celular dejando tres dígitos', function (): void {
    expect(PortalPhoneMask::mask('999888777'))->toBe('***777');
    expect(PortalPhoneMask::mask('+51 999 888 123'))->toBe('***123');
    expect(PortalPhoneMask::mask(null))->toBe('tu celular');
});
