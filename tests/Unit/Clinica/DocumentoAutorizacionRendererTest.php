<?php

declare(strict_types=1);

use App\Support\Clinica\DocumentoAutorizacionRenderer;

it('sustituye las variables de la plantilla', function (): void {
    $texto = DocumentoAutorizacionRenderer::render(
        'Yo, {{propietario}}, titular de {{paciente}} en {{clinica}}.',
        [
            'propietario' => 'Ana Pérez',
            'paciente' => 'Pelotito',
            'clinica' => 'AlmaPet',
        ],
    );

    expect($texto)->toBe('Yo, Ana Pérez, titular de Pelotito en AlmaPet.');
});

it('deja un marcador desconocido sin tocar', function (): void {
    expect(DocumentoAutorizacionRenderer::render('{{foo}} y {{paciente}}', [
        'paciente' => 'Luna',
    ]))->toBe('{{foo}} y Luna');
});
