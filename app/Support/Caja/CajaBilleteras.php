<?php

declare(strict_types=1);

namespace App\Support\Caja;

/**
 * Canales digitales a contar en apertura/cierre (no incluye tarjeta POS).
 */
final class CajaBilleteras
{
    /** @var list<string> */
    public const CODIGOS = ['yape', 'plin', 'transferencia'];

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, string>
     */
    public static function normalize(?array $raw): array
    {
        $out = [];
        foreach (self::CODIGOS as $codigo) {
            $value = $raw[$codigo] ?? 0;
            $out[$codigo] = self::money(is_numeric($value) ? (string) $value : '0');
        }

        return $out;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(string $prefix, bool $required, bool $allowNegative = false): array
    {
        $rules = [
            $prefix => [$required ? 'required' : 'nullable', 'array'],
        ];

        foreach (self::CODIGOS as $codigo) {
            $amount = [
                $required ? 'required' : 'nullable',
                'numeric',
            ];
            if (! $allowNegative) {
                $amount[] = 'min:0';
            }

            $rules[$prefix.'.'.$codigo] = $amount;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function validationAttributes(string $prefix): array
    {
        $out = [];
        foreach (self::CODIGOS as $codigo) {
            $out[$prefix.'.'.$codigo] = __('caja.attributes.'.$prefix.'.'.$codigo);
        }

        return $out;
    }

    private static function money(string $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }
}
