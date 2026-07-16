<?php

declare(strict_types=1);

namespace App\Support\Fel;

/**
 * URLs de PDF de comprobantes APISUNAT (ticket térmico / A4).
 */
final class FelDocumentPdfUrls
{
    public static function pdfA4FromTicket(?string $ticketUrl): ?string
    {
        if ($ticketUrl === null || $ticketUrl === '') {
            return null;
        }

        if (str_contains($ticketUrl, '/pdf/a4/')) {
            return $ticketUrl;
        }

        if (str_contains($ticketUrl, '/pdf/ticket/')) {
            return str_replace('/pdf/ticket/', '/pdf/a4/', $ticketUrl);
        }

        return null;
    }
}
