<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * Centroides aproximados de departamentos del Perú (WGS84).
 * Fallback para el mapa cuando el tenant aún no aceptó GPS.
 */
final class PeruDepartamentoCentroids
{
    /** @var array<string, array{lat: float, lng: float}> */
    private const CENTROIDS = [
        'AMAZONAS' => ['lat' => -5.0646, 'lng' => -78.0498],
        'ANCASH' => ['lat' => -9.3250, 'lng' => -77.5280],
        'APURIMAC' => ['lat' => -14.0500, 'lng' => -73.0800],
        'AREQUIPA' => ['lat' => -16.4090, 'lng' => -71.5375],
        'AYACUCHO' => ['lat' => -13.1631, 'lng' => -74.2236],
        'CAJAMARCA' => ['lat' => -7.1617, 'lng' => -78.5128],
        'CALLAO' => ['lat' => -12.0500, 'lng' => -77.1300],
        'CUSCO' => ['lat' => -13.5319, 'lng' => -71.9675],
        'HUANCAVELICA' => ['lat' => -12.7870, 'lng' => -74.9730],
        'HUANUCO' => ['lat' => -9.9306, 'lng' => -76.2422],
        'ICA' => ['lat' => -14.0678, 'lng' => -75.7286],
        'JUNIN' => ['lat' => -11.1580, 'lng' => -75.9930],
        'LA LIBERTAD' => ['lat' => -8.1116, 'lng' => -79.0288],
        'LAMBAYEQUE' => ['lat' => -6.7714, 'lng' => -79.8409],
        'LIMA' => ['lat' => -12.0464, 'lng' => -77.0428],
        'LORETO' => ['lat' => -3.7491, 'lng' => -73.2538],
        'MADRE DE DIOS' => ['lat' => -12.5933, 'lng' => -69.1890],
        'MOQUEGUA' => ['lat' => -17.1930, 'lng' => -70.9350],
        'PASCO' => ['lat' => -10.6830, 'lng' => -76.2560],
        'PIURA' => ['lat' => -5.1945, 'lng' => -80.6328],
        'PUNO' => ['lat' => -15.8402, 'lng' => -70.0219],
        'SAN MARTIN' => ['lat' => -6.4850, 'lng' => -76.3650],
        'TACNA' => ['lat' => -18.0066, 'lng' => -70.2463],
        'TUMBES' => ['lat' => -3.5669, 'lng' => -80.4515],
        'UCAYALI' => ['lat' => -8.3791, 'lng' => -74.5539],
    ];

    /**
     * @return array{lat: float, lng: float}|null
     */
    public static function forName(?string $departamento): ?array
    {
        if ($departamento === null || trim($departamento) === '') {
            return null;
        }

        $key = self::normalize($departamento);

        return self::CENTROIDS[$key] ?? null;
    }

    /**
     * Etiqueta canónica para reportes (evita "Lima" vs "LIMA").
     */
    public static function canonicalLabel(?string $departamento): ?string
    {
        if ($departamento === null || trim($departamento) === '') {
            return null;
        }

        $key = self::normalize($departamento);

        if (isset(self::CENTROIDS[$key])) {
            return $key;
        }

        return $key !== '' ? $key : null;
    }

    public static function normalize(string $name): string
    {
        $upper = mb_strtoupper(trim($name), 'UTF-8');
        $map = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
            'Ü' => 'U',
        ];

        return strtr($upper, $map);
    }
}
