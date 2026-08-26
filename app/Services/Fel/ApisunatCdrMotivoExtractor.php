<?php

declare(strict_types=1);

namespace App\Services\Fel;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

/**
 * Extrae el motivo SUNAT desde el XML del CDR (ApplicationResponse).
 *
 * Lucode suele devolver solo un mensaje genérico en /status; el detalle
 * (código + descripción) está en el CDR.
 */
final class ApisunatCdrMotivoExtractor
{
    /**
     * @return string|null Motivo legible, o null si no se pudo parsear.
     */
    public static function fromXml(string $xml): ?string
    {
        $trimmed = trim($xml);
        if ($trimmed === '' || ! str_contains($trimmed, '<')) {
            return null;
        }

        // Algunos CDR vienen empaquetados en ZIP (PK…).
        if (str_starts_with($trimmed, 'PK')) {
            $fromZip = self::xmlDesdeZip($trimmed);
            if ($fromZip === null) {
                return null;
            }
            $trimmed = $fromZip;
        }

        try {
            $dom = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($trimmed);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if (! $loaded) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $parts = [];

        foreach ($xpath->query('//*[local-name()="DocumentResponse"]/*[local-name()="Response"]') ?: [] as $response) {
            if (! $response instanceof DOMElement) {
                continue;
            }
            $code = self::childLocalText($response, 'ResponseCode');
            $desc = self::childLocalText($response, 'Description');
            if ($desc === null || $desc === '') {
                continue;
            }
            // 0 / 00 / 0000 = aceptado; no es motivo de rechazo.
            if ($code !== null && preg_match('/^0+$/', $code) === 1) {
                continue;
            }
            $parts[] = $code !== null && $code !== '' ? "[{$code}] {$desc}" : $desc;
        }

        if ($parts === []) {
            foreach ($xpath->query('//*[local-name()="StatusReason"]') ?: [] as $node) {
                $text = trim((string) $node->textContent);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        if ($parts === []) {
            foreach ($xpath->query('//*[local-name()="Note"]') ?: [] as $node) {
                $text = trim((string) $node->textContent);
                if ($text !== '' && ! self::esNotaIrrelevante($text)) {
                    $parts[] = $text;
                }
            }
        }

        if ($parts === []) {
            return null;
        }

        $joined = implode(' · ', array_values(array_unique($parts)));

        return mb_substr($joined, 0, 2000);
    }

    private static function childLocalText(DOMElement $parent, string $localName): ?string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                $text = trim($child->textContent);

                return $text === '' ? null : $text;
            }
        }

        return null;
    }

    private static function esNotaIrrelevante(string $text): bool
    {
        $lower = mb_strtolower($text);

        return str_contains($lower, 'la factura electrónica ha sido')
            || str_contains($lower, 'el comprobante electrónico ha sido aceptado');
    }

    private static function xmlDesdeZip(string $binary): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cdr');
        if ($tmp === false) {
            return null;
        }

        try {
            file_put_contents($tmp, $binary);
            $zip = new \ZipArchive;
            if ($zip->open($tmp) !== true) {
                return null;
            }
            $xml = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (! str_ends_with(mb_strtolower($name), '.xml')) {
                    continue;
                }
                $content = $zip->getFromIndex($i);
                if (is_string($content) && str_contains($content, '<')) {
                    $xml = $content;
                    break;
                }
            }
            $zip->close();

            return $xml;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }
}
