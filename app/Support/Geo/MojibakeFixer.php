<?php

namespace App\Support\Geo;

/**
 * Repara texto UTF-8 mal importado (mojibake típico: JESÃšS → JESÚS).
 *
 * Ocurre cuando un dump UTF-8 se ejecutó con cliente/terminal en Windows-1252
 * o cuando el archivo .sql no estaba guardado en UTF-8.
 */
final class MojibakeFixer
{
    private const MOJIBAKE_PATTERN = '/[ÃÂÐÑ]/u';

    public static function looksCorrupted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return preg_match(self::MOJIBAKE_PATTERN, $value) === 1;
    }

    public static function repair(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! self::looksCorrupted($value)) {
            return $value;
        }

        $bytes = @mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
        if ($bytes === false) {
            return $value;
        }

        $fixed = @mb_convert_encoding($bytes, 'UTF-8', 'UTF-8');
        if ($fixed === false || $fixed === '') {
            return $value;
        }

        if (self::looksMoreCorrupted($fixed, $value)) {
            return $value;
        }

        return $fixed;
    }

    private static function looksMoreCorrupted(string $candidate, string $original): bool
    {
        $badCandidate = preg_match_all(self::MOJIBAKE_PATTERN, $candidate);
        $badOriginal = preg_match_all(self::MOJIBAKE_PATTERN, $original);

        return $badCandidate > $badOriginal;
    }
}
