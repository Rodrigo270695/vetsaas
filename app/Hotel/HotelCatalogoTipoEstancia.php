<?php

namespace App\Hotel;

/**
 * Catálogo de tipos de hospedaje / guardería (slugs estables).
 *
 * Etiquetas en i18n front: `hotel.tipos_estancia.*`.
 */
final class HotelCatalogoTipoEstancia
{
    public const OTRO_PERSONALIZADO = 'otro_personalizado';

    /**
     * @return list<array{grupo: string, items: list<string>}>
     */
    public static function grupos(): array
    {
        return [
            [
                'grupo' => 'habitaciones',
                'items' => [
                    'habitacion_estandar',
                    'habitacion_grande',
                    'suite_mascotas',
                ],
            ],
            [
                'grupo' => 'pension',
                'items' => [
                    'pension_completa',
                    'pension_media',
                    'solo_alojamiento',
                ],
            ],
            [
                'grupo' => 'guarderia',
                'items' => [
                    'guarderia_dia',
                    'media_jornada',
                    'fin_semana',
                ],
            ],
            [
                'grupo' => 'especiales',
                'items' => [
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
}
