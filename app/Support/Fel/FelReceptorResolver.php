<?php

namespace App\Support\Fel;

use App\Models\FelSerie;
use App\Models\Propietario;

/**
 * Datos del receptor SUNAT a partir del propietario (sin decidir boleta/factura).
 */
final class FelReceptorResolver
{
    /**
     * @return array{
     *     tipo_doc: int,
     *     num_doc: string,
     *     nombre: string,
     * }
     */
    public static function datosReceptor(?Propietario $propietario): array
    {
        $nombre = 'CLIENTES VARIOS';
        $numDoc = '00000000';
        $tipoDoc = 1;

        if ($propietario !== null) {
            $denominacion = trim((string) ($propietario->razon_social ?? ''))
                ?: trim(implode(' ', array_filter([$propietario->nombres, $propietario->apellidos])));
            if ($denominacion !== '') {
                $nombre = mb_substr($denominacion, 0, 200);
            }

            $digits = preg_replace('/\D+/', '', (string) $propietario->numero_documento) ?? '';
            $tipoDoc = self::tipoDocSunatDesdePropietario($propietario, $digits);
            if ($digits !== '') {
                $numDoc = match ($tipoDoc) {
                    6 => strlen($digits) >= 11 ? substr($digits, 0, 11) : $digits,
                    1 => strlen($digits) >= 8 ? substr($digits, 0, 8) : $digits,
                    default => mb_substr($digits, 0, 15),
                };
            }
        }

        return [
            'tipo_doc' => $tipoDoc,
            'num_doc' => $numDoc,
            'nombre' => $nombre,
        ];
    }

    public static function etiquetaTipo(int $tipoComprobante): string
    {
        return $tipoComprobante === FelSerie::TIPO_FACTURA ? 'factura' : 'boleta';
    }

    private static function tipoDocSunatDesdePropietario(Propietario $propietario, string $digits): int
    {
        $tipo = strtoupper(trim((string) ($propietario->tipo_documento ?? '')));

        return match ($tipo) {
            'RUC' => 6,
            'CE' => 4,
            'PAS' => 7,
            'DNI' => 1,
            default => strlen($digits) === 11 ? 6 : 1,
        };
    }
}
