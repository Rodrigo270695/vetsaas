<?php

declare(strict_types=1);

use App\Models\VeterinariaProspecto;
use App\Services\Prospectos\VeterinariaProspectoRutaService;
use Illuminate\Support\Collection;

it('ordena la ruta por vecino más cercano desde Lambayeque', function (): void {
    $originLat = -6.7018;
    $originLng = -79.9061;

    $lejos = new VeterinariaProspecto([
        'id' => '11111111-1111-1111-1111-111111111111',
        'nombre' => 'Piura Vet',
        'lat' => -5.1945,
        'lng' => -80.6328,
    ]);
    $cerca = new VeterinariaProspecto([
        'id' => '22222222-2222-2222-2222-222222222222',
        'nombre' => 'Chiclayo Vet',
        'lat' => -6.7714,
        'lng' => -79.8409,
    ]);

    $ruta = (new VeterinariaProspectoRutaService)->ordenar(
        new Collection([$lejos, $cerca]),
        $originLat,
        $originLng,
        10,
    );

    expect($ruta)->toHaveCount(2)
        ->and($ruta[0]['nombre'])->toBe('Chiclayo Vet')
        ->and($ruta[1]['nombre'])->toBe('Piura Vet');
});
