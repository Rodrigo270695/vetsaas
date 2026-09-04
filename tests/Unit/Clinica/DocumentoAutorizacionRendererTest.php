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

it('conserva justificado, fuente y tamaño', function (): void {
    $html = DocumentoAutorizacionRenderer::sanitizeHtml(
        '<p style="text-align:justify;font-family:Arial;font-size:18px">Texto</p>',
    );

    expect($html)->toContain('text-align: justify')
        ->and($html)->toContain('Arial')
        ->and($html)->toContain('font-size: 18px');
});

it('conserva tamaños CSS con palabras clave del editor', function (): void {
    $html = DocumentoAutorizacionRenderer::sanitizeHtml(
        '<p style="font-size:x-large;font-weight:bold">Título</p><span style="font: italic 14px Times New Roman">Cuerpo</span>',
    );

    expect($html)->toContain('font-size: 24px')
        ->and($html)->toContain('font-weight: bold')
        ->and($html)->toContain('italic')
        ->and($html)->toContain('Times New Roman')
        ->and($html)->toContain('font-size: 14px');
});

it('mapea fuentes web a DejaVu para el PDF', function (): void {
    $html = DocumentoAutorizacionRenderer::mapFontsForPdf(
        '<p style="font-family: Arial, Helvetica, sans-serif;">A</p>',
    );

    expect($html)->toContain('dejavu sans')
        ->and($html)->toContain('!important')
        ->and($html)->not->toContain('Arial');
});

it('conserva formato distinto por plantilla tras sanitizar y preparar PDF', function (): void {
    $cirugia = DocumentoAutorizacionRenderer::sanitizeHtml(
        '<p style="font-family:Georgia;font-size:24px;font-weight:bold">Cirugía {{paciente}}</p>'
        .'<ol style="font-size:12px"><li>Cláusula A</li></ol>',
    );
    $vacuna = DocumentoAutorizacionRenderer::sanitizeHtml(
        '<p style="font-family:Courier New;font-size:18px">Vacuna {{paciente}}</p>'
        .'<p style="font-size:12px">Cláusula B</p>',
    );

    expect($cirugia)
        ->toContain('Georgia')
        ->toContain('font-size: 24px')
        ->toContain('font-weight: bold')
        ->toContain('font-size: 12px')
        ->and($vacuna)
        ->toContain('Courier')
        ->toContain('font-size: 18px')
        ->and($cirugia)->not->toContain('Courier')
        ->and($vacuna)->not->toContain('Georgia');

    $cirugiaOtraVez = DocumentoAutorizacionRenderer::sanitizeHtml($cirugia);
    expect($cirugiaOtraVez)->toContain('font-size: 24px')->toContain('Georgia');

    $pdfCirugia = DocumentoAutorizacionRenderer::mapFontsForPdf($cirugia);
    $pdfVacuna = DocumentoAutorizacionRenderer::mapFontsForPdf($vacuna);

    expect($pdfCirugia)->toContain('dejavu serif')->toContain('24px')
        ->and($pdfVacuna)->toContain('dejavu sans mono')->toContain('18px');
});

it('conserva el marcador de logo y quita src arbitrario', function (): void {
    $html = DocumentoAutorizacionRenderer::sanitizeHtml(
        '<p><img class="auth-doc-logo" src="javascript:alert(1)" alt="x"></p><p>Hola</p>',
    );

    expect($html)->toContain('auth-doc-logo')
        ->and($html)->not->toContain('javascript')
        ->and($html)->not->toContain('src=');
});

it('conserva tildes en español', function (): void {
    $html = DocumentoAutorizacionRenderer::sanitizeHtml('<p>autorización clínica años</p>');

    expect($html)->toContain('autorización')
        ->and($html)->toContain('clínica')
        ->and($html)->toContain('años');
});

it('inyecta el src del logo de la clínica', function (): void {
    $html = DocumentoAutorizacionRenderer::applyLogoSrc(
        '<p><img class="auth-doc-logo" alt=""></p>',
        'https://clinica.test/logo.png',
    );

    expect($html)->toContain('src="https://clinica.test/logo.png"');
});
