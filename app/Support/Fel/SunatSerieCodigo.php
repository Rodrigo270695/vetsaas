<?php

namespace App\Support\Fel;

/**
 * Normaliza series SUNAT / Nubefact (4 caracteres alfanuméricos, sin guiones).
 *
 * @see https://www.nubefact.com/integracion — campo JSON `serie`: "B001"
 */
final class SunatSerieCodigo
{
    public static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $limpio = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($valor)) ?? '');

        if ($limpio === '') {
            return null;
        }

        return mb_substr($limpio, 0, 4);
    }

    public static function esBoletaValida(string $serie): bool
    {
        return (bool) preg_match('/^B[A-Z0-9]{3}$/', $serie);
    }

    public static function esFacturaValida(string $serie): bool
    {
        return (bool) preg_match('/^F[A-Z0-9]{3}$/', $serie);
    }
}
