<?php

declare(strict_types=1);

namespace App\Support\Clinica;

/**
 * Opciones del triaje. Las claves se guardan en JSON y la vista las traduce.
 */
final class TriajeCatalogo
{
    /** @var array<string, list<string>> */
    public const SECCIONES = [
        'piel' => ['bien', 'costras', 'heridas', 'roja', 'eczemas', 'hematomas', 'caspa', 'alergia', 'masa'],
        'oidos' => ['bien', 'hematomas', 'mal_olor', 'inflamados', 'dolor', 'sangrado', 'cerumen'],
        'ojos' => ['bien', 'leganas', 'enrojecimiento', 'lagrimeo'],
        'dientes' => ['bien', 'gingivitis', 'perdida', 'mal_aliento', 'sarro', 'flojos', 'sangrado'],
        'pelaje' => ['bien', 'caida', 'opaco', 'brilloso', 'mal_olor', 'motoso', 'manchado', 'alopecia'],
        'pulgas' => ['si', 'no', 'gran_cantidad', 'poca_cantidad'],
        'garrapatas' => ['si', 'no', 'gran_cantidad', 'poca_cantidad'],
        'desparasitacion' => ['si_registra', 'no_registra', 'al_dia', 'pendiente'],
        'vacunas' => ['si_registra', 'no_registra', 'pendiente'],
    ];

    /** @var list<string> */
    public const RECOMENDACIONES = [
        'consulta_medica',
        'aplicar_vacuna',
        'antipulgas',
        'tratamiento',
        'bano_medicado',
        'alimento',
        'bajar_peso',
        'profilaxis',
        'desparasitacion_interna',
        'examenes',
        'bano_antipulgas',
        'control_anual',
        'vitaminas',
        'subir_peso',
    ];

    /** @var list<string> */
    private const NEUTRAS = ['bien', 'no', 'si_registra', 'al_dia'];

    /** @var array<string, string> */
    private const SECCION_CORTA = [
        'piel' => 'Piel',
        'oidos' => 'Oídos',
        'ojos' => 'Ojos',
        'dientes' => 'Dientes',
        'pelaje' => 'Pelaje',
        'pulgas' => 'Pulgas',
        'garrapatas' => 'Garrapatas',
        'desparasitacion' => 'Desparasitación',
        'vacunas' => 'Vacunas',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function filtrarHallazgos(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $out = [];
        foreach (self::SECCIONES as $seccion => $permitidas) {
            $marcadas = $input[$seccion] ?? null;
            if (! is_array($marcadas)) {
                continue;
            }

            $limpias = [];
            foreach ($marcadas as $item) {
                if (is_string($item) && in_array($item, $permitidas, true) && ! in_array($item, $limpias, true)) {
                    $limpias[] = $item;
                }
            }

            if ($limpias !== []) {
                $out[$seccion] = $limpias;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function filtrarRecomendaciones(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $out = [];
        foreach ($input as $item) {
            if (is_string($item) && in_array($item, self::RECOMENDACIONES, true) && ! in_array($item, $out, true)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, list<string>>  $hallazgos
     */
    public static function resumen(array $hallazgos): ?string
    {
        $partes = [];
        foreach (self::SECCIONES as $seccion => $permitidas) {
            $marcadas = $hallazgos[$seccion] ?? [];
            if (! is_array($marcadas)) {
                continue;
            }

            $alerta = array_values(array_filter(
                $marcadas,
                static fn (mixed $item): bool => is_string($item)
                    && in_array($item, $permitidas, true)
                    && ! in_array($item, self::NEUTRAS, true),
            ));
            if ($alerta === []) {
                continue;
            }

            $partes[] = self::SECCION_CORTA[$seccion] ?? $seccion;
            if (count($partes) >= 3) {
                break;
            }
        }

        if ($partes === []) {
            return null;
        }

        return implode(' · ', $partes);
    }
}
