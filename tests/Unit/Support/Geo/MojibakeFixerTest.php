<?php

use App\Support\Geo\MojibakeFixer;

it('repara mojibake típico de ubigeo peruano', function (): void {
    expect(MojibakeFixer::repair('JESÃšS MARÃA'))->toBe('JESÚS MARÍA');
    expect(MojibakeFixer::repair('ANCÃ“N'))->toBe('ANCÓN');
    expect(MojibakeFixer::repair('BREÃ‘A'))->toBe('BREÑA');
    expect(MojibakeFixer::repair('PerÃº'))->toBe('Perú');
    expect(MojibakeFixer::repair('ÃNCASH'))->toBe('ÁNCASH');
});

it('no altera nombres ya correctos', function (): void {
    expect(MojibakeFixer::repair('LIMA'))->toBe('LIMA');
    expect(MojibakeFixer::repair('AREQUIPA'))->toBe('AREQUIPA');
    expect(MojibakeFixer::repair('JESÚS MARÍA'))->toBe('JESÚS MARÍA');
});
