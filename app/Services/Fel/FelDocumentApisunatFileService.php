<?php

declare(strict_types=1);

namespace App\Services\Fel;

use App\Models\ClinicSetting;
use App\Models\FelDocument;
use App\Support\Fel\ApisunatCredentialResolver;
use App\Support\Fel\FelDocumentPdfUrls;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Descarga archivos CPE (PDF/XML/CDR) desde Lucode (APISUNAT) usando el payload y token de la clínica.
 */
final class FelDocumentApisunatFileService
{
    public function __construct(
        private readonly ApisunatClient $apisunat,
    ) {}

    /**
     * Enlaces Lucode/APISUNAT del comprobante (payload de emisión + columnas en BD).
     *
     * @return array{pdf: ?string, pdf_a4: ?string, xml: ?string, cdr: ?string}
     */
    public function enlaces(FelDocument $documento): array
    {
        $pdf = filled($documento->url_pdf) ? (string) $documento->url_pdf : null;
        $xml = filled($documento->url_xml) ? (string) $documento->url_xml : null;
        $cdr = filled($documento->url_cdr) ? (string) $documento->url_cdr : null;

        $payload = $documento->apisunat_payload;
        if (is_array($payload)) {
            $extraidos = $this->apisunat->extraerEnlaces($payload);
            $pdf = $extraidos['pdf'] ?? $pdf;
            $xml = $extraidos['xml'] ?? $xml;
            $cdr = $extraidos['cdr'] ?? $cdr;
        }

        return [
            'pdf' => $pdf,
            'pdf_a4' => FelDocumentPdfUrls::pdfA4FromTicket($pdf),
            'xml' => $xml,
            'cdr' => $cdr,
        ];
    }

    /**
     * Descarga bytes de un archivo Lucode/APISUNAT.
     *
     * @param  'pdf_ticket'|'pdf_a4'|'xml'|'cdr'  $tipo
     */
    public function descargar(FelDocument $documento, ClinicSetting $clinic, string $tipo): string
    {
        $enlaces = $this->enlaces($documento);

        $url = match ($tipo) {
            'pdf_ticket' => $enlaces['pdf'],
            'pdf_a4' => $enlaces['pdf_a4'],
            'xml' => $enlaces['xml'],
            'cdr' => $enlaces['cdr'],
            default => null,
        };

        if (! is_string($url) || trim($url) === '') {
            throw new RuntimeException('El archivo Lucode '.$tipo.' no está disponible para este comprobante.');
        }

        $label = match ($tipo) {
            'pdf_ticket' => 'PDF',
            'pdf_a4' => 'PDF A4',
            'xml' => 'XML',
            'cdr' => 'CDR',
            default => $tipo,
        };

        return $this->fetchLucodeUrl(trim($url), $clinic, $label, $tipo);
    }

    private function fetchLucodeUrl(
        string $url,
        ClinicSetting $clinic,
        string $label,
        string $tipo,
    ): string {
        if (! ApisunatCredentialResolver::estaConfigurado($clinic)) {
            throw new RuntimeException('Lucode (APISUNAT) no está configurado en la clínica.');
        }

        $credenciales = ApisunatCredentialResolver::fromClinicSetting($clinic);

        try {
            $response = Http::timeout(45)
                ->withToken($credenciales['token'])
                ->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Error al descargar '.$label.' desde Lucode: '.$e->getMessage(),
                0,
                $e,
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'Lucode devolvió HTTP '.$response->status().' al descargar '.$label.'.',
            );
        }

        $body = (string) $response->body();
        if ($body === '') {
            throw new RuntimeException('El archivo '.$label.' de Lucode está vacío.');
        }

        if ($tipo === 'pdf_ticket' || $tipo === 'pdf_a4') {
            if (! str_starts_with($body, '%PDF')) {
                throw new RuntimeException(
                    'Lucode no devolvió un PDF válido para '.$label.'. Revisa el token y el modo sandbox/producción.',
                );
            }
        }

        if (($tipo === 'xml' || $tipo === 'cdr') && ! str_contains($body, '<?xml')) {
            throw new RuntimeException(
                'Lucode no devolvió un XML válido para '.$label.'.',
            );
        }

        return $body;
    }
}
