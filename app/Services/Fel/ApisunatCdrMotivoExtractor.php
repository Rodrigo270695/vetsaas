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
        $trimmed = self::normalizarEntrada($xml);
        if ($trimmed === null) {
            return null;
        }

        $fromDom = self::desdeDom($trimmed);
        if ($fromDom !== null) {
            return $fromDom;
        }

        return self::desdeRegex($trimmed);
    }

    private static function normalizarEntrada(string $xml): ?string
    {
        $trimmed = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        $trimmed = trim($trimmed);
        if ($trimmed === '') {
            return null;
        }

        // gzip
        if (str_starts_with($trimmed, "\x1f\x8b")) {
            $decoded = @gzdecode($trimmed);
            if (! is_string($decoded) || $decoded === '') {
                return null;
            }
            $trimmed = trim($decoded);
        }

        // ZIP (CDR SUNAT clásico)
        if (str_starts_with($trimmed, 'PK')) {
            $fromZip = self::xmlDesdeZip($trimmed);
            if ($fromZip === null) {
                return null;
            }
            $trimmed = trim($fromZip);
        }

        if (! str_contains($trimmed, '<')) {
            return null;
        }

        return $trimmed;
    }

    private static function desdeDom(string $xml): ?string
    {
        try {
            $dom = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml);
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

        foreach ($xpath->query('//*[local-name()="Response"]') ?: [] as $response) {
            if (! $response instanceof DOMElement) {
                continue;
            }
            $code = self::childLocalText($response, 'ResponseCode');
            $desc = self::childLocalText($response, 'Description');
            if ($desc === null || $desc === '') {
                continue;
            }
            if ($code !== null && preg_match('/^0+$/', $code) === 1) {
                continue;
            }
            $parts[] = $code !== null && $code !== '' ? "[{$code}] {$desc}" : $desc;
        }

        if ($parts === []) {
            foreach ($xpath->query('//*[local-name()="Status"]') ?: [] as $status) {
                if (! $status instanceof DOMElement) {
                    continue;
                }
                $code = self::childLocalText($status, 'StatusReasonCode');
                $desc = self::childLocalText($status, 'StatusReason');
                if ($desc === null || $desc === '') {
                    continue;
                }
                if ($code !== null && preg_match('/^0+$/', $code) === 1) {
                    continue;
                }
                $parts[] = $code !== null && $code !== '' ? "[{$code}] {$desc}" : $desc;
            }
        }

        if ($parts === []) {
            foreach ($xpath->query('//*[local-name()="StatusReason"]') ?: [] as $node) {
                $text = trim(html_entity_decode((string) $node->textContent, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        if ($parts === []) {
            foreach ($xpath->query('//*[local-name()="Note"]') ?: [] as $node) {
                $text = trim(html_entity_decode((string) $node->textContent, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                if ($text !== '' && ! self::esNotaIrrelevante($text)) {
                    $parts[] = $text;
                }
            }
        }

        if ($parts === []) {
            return null;
        }

        return mb_substr(implode(' · ', array_values(array_unique($parts))), 0, 2000);
    }

    private static function desdeRegex(string $xml): ?string
    {
        $parts = [];

        if (preg_match_all(
            '/<(?:\w+:)?ResponseCode[^>]*>\s*([^<\s]+)\s*<\/(?:\w+:)?ResponseCode>/iu',
            $xml,
            $codes,
        ) && preg_match_all(
            '/<(?:\w+:)?Description[^>]*>\s*(.*?)\s*<\/(?:\w+:)?Description>/ius',
            $xml,
            $descs,
        )) {
            $n = min(count($codes[1]), count($descs[1]));
            for ($i = 0; $i < $n; $i++) {
                $code = trim(html_entity_decode((string) $codes[1][$i], ENT_QUOTES | ENT_XML1, 'UTF-8'));
                $desc = trim(html_entity_decode(strip_tags((string) $descs[1][$i]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                if ($desc === '' || preg_match('/^0+$/', $code) === 1) {
                    continue;
                }
                $parts[] = $code !== '' ? "[{$code}] {$desc}" : $desc;
            }
        }

        if ($parts === [] && preg_match_all(
            '/<(?:\w+:)?StatusReason(?:Code)?[^>]*>\s*(.*?)\s*<\/(?:\w+:)?StatusReason(?:Code)?>/ius',
            $xml,
            $reasons,
        )) {
            foreach ($reasons[1] as $raw) {
                $text = trim(html_entity_decode(strip_tags((string) $raw), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                if ($text !== '' && ! preg_match('/^0+$/', $text)) {
                    $parts[] = $text;
                }
            }
        }

        if ($parts === []) {
            return null;
        }

        return mb_substr(implode(' · ', array_values(array_unique($parts))), 0, 2000);
    }

    private static function childLocalText(DOMElement $parent, string $localName): ?string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                $text = trim(html_entity_decode($child->textContent, ENT_QUOTES | ENT_XML1, 'UTF-8'));

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
