<?php

declare(strict_types=1);

namespace App\Support\WhatsApp;

final class WhatsAppCampaignMessageRenderer
{
    /**
     * @param  array{nombre?: string, nombre_completo?: string, propietario?: string, mascota?: string, clinica?: string}  $vars
     */
    public static function render(string $template, array $vars): string
    {
        $pairs = [];
        foreach ($vars as $key => $value) {
            $text = trim($value);
            $pairs['{'.$key.'}'] = $text;
            $pairs['{{'.$key.'}}'] = $text;
        }

        return trim(strtr($template, $pairs));
    }

    /**
     * @param  list<string>  $nombres
     */
    public static function joinPetNames(array $nombres): string
    {
        $clean = array_values(array_filter(array_map(
            static fn (string $n): string => trim($n),
            $nombres,
        )));

        $count = count($clean);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $clean[0];
        }
        if ($count === 2) {
            return $clean[0].' y '.$clean[1];
        }

        $last = array_pop($clean);

        return implode(', ', $clean).' y '.$last;
    }
}
