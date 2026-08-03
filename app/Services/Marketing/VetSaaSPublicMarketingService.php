<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Payload público para la landing Orvae de VetSaaS (planes + conteo clínicas).
 */
final class VetSaaSPublicMarketingService
{
    /**
     * @return array{
     *     clinics_count: int,
     *     clinics_display: int,
     *     clinics_label: string,
     *     plans: list<array<string, mixed>>
     * }
     */
    public function payload(): array
    {
        return Cache::remember('vetsaas.public.marketing', 600, function (): array {
            $count = $this->activeClinicsCount();
            $display = self::marketingDisplayCount($count);

            return [
                'clinics_count' => $count,
                'clinics_display' => $display,
                'clinics_label' => $display.'+',
                'plans' => $this->publicPlans(),
            ];
        });
    }

    public function forgetCache(): void
    {
        Cache::forget('vetsaas.public.marketing');
    }

    /**
     * 42 → 100, 100 → 100, 101 → 200 (siempre un bucket de 100 por encima del tramo real).
     */
    public static function marketingDisplayCount(int $actual): int
    {
        if ($actual <= 0) {
            return 100;
        }

        return (int) (ceil($actual / 100) * 100);
    }

    private function activeClinicsCount(): int
    {
        return Tenant::query()
            ->whereIn('estado', ['active', 'trial'])
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publicPlans(): array
    {
        $plans = Plan::query()
            ->where('activo', true)
            ->where('es_publico', true)
            ->with('features')
            ->orderBy('orden')
            ->get();

        $rows = [];

        foreach ($plans as $plan) {
            $features = [];
            foreach ($plan->features as $feature) {
                $features[$feature->feature] = [
                    'int' => $feature->valor_int,
                    'bool' => $feature->valor_bool,
                    'str' => $feature->valor_str,
                ];
            }

            $rows[] = [
                'codigo' => $plan->codigo,
                'nombre' => $plan->nombre,
                'descripcion' => $plan->descripcion,
                'badge' => $plan->badge,
                'color_hex' => $plan->color_hex,
                'features' => $features,
                'highlights' => $this->marketingHighlights($plan->codigo, $features),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, array{int: mixed, bool: mixed, str: mixed}>  $features
     * @return list<string>
     */
    private function marketingHighlights(string $codigo, array $features): array
    {
        $lines = [];

        $usuarios = $features['max_usuarios']['int'] ?? null;
        $lines[] = $this->limitLine('Usuarios', $usuarios);

        $pacientes = $features['max_pacientes']['int'] ?? null;
        $lines[] = $this->limitLine('Pacientes', $pacientes);

        $citas = $features['max_citas_mes']['int'] ?? null;
        $lines[] = $this->limitLine('Citas / mes', $citas);

        if (($features['historia_clinica']['bool'] ?? false) === true) {
            $lines[] = 'Historia clínica SOAP';
        }

        if (($features['modulo_stock']['bool'] ?? false) === true) {
            $lines[] = 'Inventario y stock';
        }

        if (($features['modulo_grooming']['bool'] ?? false) === true) {
            $lines[] = 'Grooming';
        }

        if (($features['modulo_laboratorio']['bool'] ?? false) === true) {
            $lines[] = 'Laboratorio';
        }

        if (($features['modulo_guarderia']['bool'] ?? false) === true) {
            $lines[] = 'Hotel / guardería';
        }

        if (($features['factura_electronica']['bool'] ?? false) === true) {
            $lines[] = 'Facturación electrónica SUNAT';
        }

        $wa = $features['max_wa_mes']['int'] ?? null;
        if (is_int($wa) && $wa === -1) {
            $lines[] = 'WhatsApp ilimitado';
        } elseif (is_int($wa) && $wa > 0) {
            $lines[] = "WhatsApp ({$wa}/mes)";
        }

        if (($features['multi_sede']['bool'] ?? false) === true) {
            $lines[] = 'Multi-sede';
        }

        $soporte = (string) ($features['soporte_tipo']['str'] ?? '');
        $lines[] = match ($soporte) {
            'whatsapp_prioritario' => 'Soporte WhatsApp prioritario',
            'whatsapp' => 'Soporte por WhatsApp',
            'email' => 'Soporte por correo',
            default => 'Documentación de ayuda',
        };

        if ($codigo === 'free') {
            array_unshift($lines, 'Actívate gratis en minutos');
        }

        return array_values(array_filter($lines));
    }

    private function limitLine(string $label, mixed $value): string
    {
        if (! is_int($value) && ! is_float($value)) {
            return $label;
        }

        $n = (int) $value;
        if ($n === -1) {
            return "{$label} ilimitados";
        }

        return "{$label}: ".number_format($n, 0, '.', ',');
    }
}
