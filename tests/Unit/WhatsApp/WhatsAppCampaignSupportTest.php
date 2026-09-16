<?php

declare(strict_types=1);

use App\Support\WhatsApp\PeruMobilePhone;
use App\Support\WhatsApp\WhatsAppCampaignMessageRenderer;

it('acepta celular peruano de 9 dígitos o con 51', function (): void {
    expect(PeruMobilePhone::normalized('987654321'))->toBe('51987654321')
        ->and(PeruMobilePhone::normalized('+51 987 654 321'))->toBe('51987654321')
        ->and(PeruMobilePhone::isValid('014445555'))->toBeFalse()
        ->and(PeruMobilePhone::isValid(null))->toBeFalse();
});

it('interpola variables de campaña', function (): void {
    $text = WhatsAppCampaignMessageRenderer::render(
        'Hola {nombre}, de {clinica} para {mascota}',
        [
            'nombre' => 'María',
            'clinica' => 'Honus',
            'mascota' => 'Max',
        ],
    );

    expect($text)->toBe('Hola María, de Honus para Max')
        ->and(WhatsAppCampaignMessageRenderer::joinPetNames(['Max', 'Luna', 'Toby']))
        ->toBe('Max, Luna y Toby');
});
