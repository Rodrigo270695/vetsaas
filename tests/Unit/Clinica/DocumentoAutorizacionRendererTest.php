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

it('detecta HTML y conserva negrita y alineación segura', function (): void {
    $html = DocumentoAutorizacionRenderer::sanitizeHtml(
        '<p style="text-align:center;color:red" onclick="alert(1)"><strong>Hola</strong><script>x</script></p>',
    );

    expect($html)->toContain('<strong>Hola</strong>')
        ->and($html)->toContain('text-align: center')
        ->and($html)->not->toContain('onclick')
        ->and($html)->not->toContain('script')
        ->and($html)->not->toContain('color:red');
});

it('convierte texto plano a HTML escapado', function (): void {
    expect(DocumentoAutorizacionRenderer::toSafeHtml("A < B\nC"))
        ->toBe("A &lt; B<br>\nC");
});
