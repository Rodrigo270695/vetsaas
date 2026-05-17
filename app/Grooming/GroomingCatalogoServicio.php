<?php

namespace App\Grooming;

/**
 * Catálogo de tipos de servicio de peluquería clínica / estética.
 *
 * Códigos estables (slug) para informes, KPIs y reglas de negocio; las etiquetas
 * legibles viven en i18n del front (`grooming.tipos_servicio.*`).
 */
final class GroomingCatalogoServicio
{
    public const OTRO_PERSONALIZADO = 'otro_personalizado';

    /**
     * @return list<array{grupo: string, items: list<string>}>
     */
    public static function grupos(): array
    {
        return [
            [
                'grupo' => 'banos',
                'items' => [
                    'bano_higienico',
                    'bano_higienico_pelo_largo',
                    'bano_desenredo_liviano',
                    'bano_desenredo_severo',
                    'bano_antiparasitario',
                    'bano_medicado_dermatologico',
                    'bano_hipoalergenico',
                    'bano_cachorro',
                    'bano_gato',
                    'bano_control_olor',
                ],
            ],
            [
                'grupo' => 'cortes',
                'items' => [
                    'corte_tijera_completo',
                    'corte_maquina_uniforme',
                    'corte_higienico_zonas',
                    'corte_deslanado_strip',
                    'corte_deslanado_mano',
                    'trimming_cabeza_patas',
                    'corte_sanitario_prequirurgico',
                ],
            ],
            [
                'grupo' => 'combos',
                'items' => [
                    'combo_bano_corte',
                    'combo_bano_desenredo_corte',
                    'combo_bano_corte_deslanado',
                    'combo_spa_completo',
                ],
            ],
            [
                'grupo' => 'extras',
                'items' => [
                    'cepillado_seco',
                    'limpieza_oidos',
                    'corte_unas',
                    'limpieza_gl_perianales',
                    'hidratacion_mascarilla',
                ],
            ],
            [
                'grupo' => 'especiales',
                'items' => [
                    'consulta_pre_estetica',
                    self::OTRO_PERSONALIZADO,
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        $out = [];

        foreach (self::grupos() as $bloque) {
            foreach ($bloque['items'] as $slug) {
                $out[] = $slug;
            }
        }

        return $out;
    }

    public static function esSlugValido(string $slug): bool
    {
        return in_array($slug, self::slugs(), true);
    }

    /**
     * Duración orientativa (minutos) por tipo de servicio para cuadrar agenda y
     * reducir fricción en recepción. El usuario puede ajustarla siempre.
     *
     * @return array<string, positive-int>
     */
    public static function duracionesSugeridas(): array
    {
        return [
            'bano_higienico' => 45,
            'bano_higienico_pelo_largo' => 75,
            'bano_desenredo_liviano' => 90,
            'bano_desenredo_severo' => 150,
            'bano_antiparasitario' => 65,
            'bano_medicado_dermatologico' => 60,
            'bano_hipoalergenico' => 50,
            'bano_cachorro' => 40,
            'bano_gato' => 55,
            'bano_control_olor' => 50,
            'corte_tijera_completo' => 90,
            'corte_maquina_uniforme' => 55,
            'corte_higienico_zonas' => 40,
            'corte_deslanado_strip' => 120,
            'corte_deslanado_mano' => 90,
            'trimming_cabeza_patas' => 45,
            'corte_sanitario_prequirurgico' => 25,
            'combo_bano_corte' => 105,
            'combo_bano_desenredo_corte' => 140,
            'combo_bano_corte_deslanado' => 160,
            'combo_spa_completo' => 120,
            'cepillado_seco' => 30,
            'limpieza_oidos' => 15,
            'corte_unas' => 20,
            'limpieza_gl_perianales' => 25,
            'hidratacion_mascarilla' => 20,
            'consulta_pre_estetica' => 25,
            self::OTRO_PERSONALIZADO => 60,
        ];
    }

    public static function duracionSugeridaPara(string $slug): int
    {
        return self::duracionesSugeridas()[$slug] ?? 60;
    }
}
