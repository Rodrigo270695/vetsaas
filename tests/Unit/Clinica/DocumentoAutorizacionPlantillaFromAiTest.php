<?php

declare(strict_types=1);

use App\Services\Clinica\DocumentoAutorizacionPlantillaFromAiService;
use App\Support\Clinica\DocumentoAutorizacionRenderer;

it('decodifica JSON de la IA aunque venga en un bloque markdown', function (): void {
    $ai = app(DocumentoAutorizacionPlantillaFromAiService::class);
    $parsed = $ai->decodeJsonObject("```json\n{\"nombre\":\"Cirugía\",\"cuerpo\":\"<p>Hola</p>\"}\n```");

    expect($parsed['nombre'])->toBe('Cirugía')
        ->and($parsed['cuerpo'])->toBe('<p>Hola</p>');
});

it('antepone el logo de clínica si la IA no lo incluyó', function (): void {
    $ai = app(DocumentoAutorizacionPlantillaFromAiService::class);
    $html = $ai->ensureLogo('<p>Yo, {{propietario}}</p>');

    expect($html)->toContain('auth-doc-logo')
        ->and($html)->toContain('{{propietario}}');
});

it('el sanitizado conserva variables de plantilla', function (): void {
    $html = DocumentoAutorizacionRenderer::sanitizeHtml(
        '<p>Yo {{propietario}} titular de {{paciente}} por {{motivo}}</p>',
    );

    expect($html)->toContain('{{propietario}}')
        ->and($html)->toContain('{{paciente}}')
        ->and($html)->toContain('{{motivo}}');
});
