<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

/**
 * Ciclos de facturación de suscripción VetSaaS.
 *
 * Cupo CPE del periodo = max_comprobantes_mes × months(ciclo)
 * (PlanLimits ya incluye extras/regalos del tenant).
 */
final class SubscriptionCiclo
{
    public const MENSUAL = 'mensual';

    public const TRIMESTRAL = 'trimestral';

    public const SEMESTRAL = 'semestral';

    public const ANUAL = 'anual';

    /** @var list<string> */
    public const ALL = [
        self::MENSUAL,
        self::TRIMESTRAL,
        self::SEMESTRAL,
        self::ANUAL,
    ];

    public static function isValid(?string $ciclo): bool
    {
        return is_string($ciclo) && in_array($ciclo, self::ALL, true);
    }

    public static function normalize(?string $ciclo, string $default = self::MENSUAL): string
    {
        return self::isValid($ciclo) ? (string) $ciclo : $default;
    }

    public static function months(string $ciclo): int
    {
        return match (self::normalize($ciclo)) {
            self::TRIMESTRAL => 3,
            self::SEMESTRAL => 6,
            self::ANUAL => 12,
            default => 1,
        };
    }

    /**
     * Precio sugerido desde el catálogo del plan (sin precios trimestral/semestral propios).
     * Trimestral/semestral ≈ mensual × meses; anual usa precio_anual si existe.
     */
    public static function suggestedPriceFromPlan(float $precioMensual, ?float $precioAnual, string $ciclo): float
    {
        $ciclo = self::normalize($ciclo);

        if ($ciclo === self::ANUAL) {
            return (float) ($precioAnual ?? $precioMensual * 12);
        }

        return round($precioMensual * self::months($ciclo), 2);
    }
}
