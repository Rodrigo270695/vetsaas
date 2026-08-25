<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Payload público para la landing Orvae de VetSaaS (planes + conteo clínicas).
 * Los planes se diferencian por cantidades; los módulos están en todos.
 */
final class VetSaaSPublicMarketingService
{
    /** @var list<string> */
    private const LIMIT_KEYS = [
        'max_sedes',
        'max_usuarios',
        'max_pacientes',
        'max_propietarios',
        'max_productos',
        'max_comprobantes_mes',
    ];

    /**
     * @return array{
     *     clinics_count: int,
     *     clinics_display: int,
     *     clinics_label: string,
     *     plans: list<array<string, mixed>>,
     *     comparison: list<array<string, string>>
     * }
     */
    public function payload(): array
    {
        return Cache::remember('vetsaas.public.marketing', 600, function (): array {
            $count = $this->activeClinicsCount();
            $display = self::marketingDisplayCount($count);
            $plans = $this->publicPlans();

            return [
                'clinics_count' => $count,
                'clinics_display' => $display,
                'clinics_label' => $display.'+',
                'plans' => $plans,
                'comparison' => $this->buildComparison($plans),
                'modules_note' => 'Todos los módulos (historia clínica, agenda, inventario, grooming, hotel, laboratorio, caja) están incluidos en todos los planes. Lo que cambia es la cantidad.',
            ];
        });
    }

    public function forgetCache(): void
    {
        Cache::forget('vetsaas.public.marketing');
    }

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

            $limits = [];
            foreach (self::LIMIT_KEYS as $key) {
                $limits[$key] = is_int($features[$key]['int'] ?? null)
                    ? (int) $features[$key]['int']
                    : 0;
            }

            $rows[] = [
                'codigo' => $plan->codigo,
                'nombre' => $plan->nombre,
                'descripcion' => $plan->descripcion,
                'badge' => $plan->badge,
                'color_hex' => $plan->color_hex,
                'trial_days' => (int) $plan->trial_days,
                'referral_reward_days' => (int) $plan->referral_reward_days,
                'limits' => $limits,
                'features' => $features,
                'highlights' => $this->marketingHighlights(
                    $plan->codigo,
                    $limits,
                    (int) $plan->referral_reward_days,
                ),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     * @return list<array<string, string>>
     */
    private function buildComparison(array $plans): array
    {
        $labels = [
            'max_sedes' => 'Sedes',
            'max_usuarios' => 'Usuarios',
            'max_pacientes' => 'Pacientes',
            'max_propietarios' => 'Propietarios',
            'max_productos' => 'Productos',
            'max_comprobantes_mes' => 'Comprobantes SUNAT / mes',
        ];

        $byCodigo = [];
        foreach ($plans as $plan) {
            $codigo = strtolower((string) ($plan['codigo'] ?? ''));
            if ($codigo !== '') {
                $byCodigo[$codigo] = is_array($plan['limits'] ?? null) ? $plan['limits'] : [];
            }
        }

        $codigos = array_values(array_intersect(
            ['free', 'starter', 'pro', 'clinica'],
            array_keys($byCodigo),
        ));
        if ($codigos === []) {
            $codigos = array_keys($byCodigo);
        }

        $rows = [];
        foreach ($labels as $key => $label) {
            $row = ['key' => $key, 'label' => $label];
            foreach ($codigos as $codigo) {
                $row[$codigo] = $this->formatLimit((int) ($byCodigo[$codigo][$key] ?? 0));
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $limits
     * @return list<string>
     */
    private function marketingHighlights(string $codigo, array $limits, int $referralRewardDays = 0): array
    {
        $lines = ['Todos los módulos incluidos'];

        $lines[] = $this->limitLine('Sedes', $limits['max_sedes'] ?? 0);
        $lines[] = $this->limitLine('Usuarios', $limits['max_usuarios'] ?? 0);
        $lines[] = $this->limitLine('Pacientes', $limits['max_pacientes'] ?? 0);
        $lines[] = $this->limitLine('Propietarios', $limits['max_propietarios'] ?? 0);
        $lines[] = $this->limitLine('Productos', $limits['max_productos'] ?? 0);
        $lines[] = $this->limitLine('Comprobantes SUNAT / mes', $limits['max_comprobantes_mes'] ?? 0);

        if ($referralRewardDays > 0) {
            $lines[] = $this->referralRewardLine($referralRewardDays);
        }

        if ($codigo === 'free') {
            array_unshift($lines, 'Actívate gratis en minutos');
        }

        return array_values(array_filter($lines));
    }

    private function referralRewardLine(int $days): string
    {
        if ($days >= 28) {
            return 'Referido: 1 mes gratis para quien te invita';
        }

        return 'Referido: '.$days.' días gratis para quien te invita';
    }

    private function limitLine(string $label, mixed $value): string
    {
        return $label.': '.$this->formatLimit((int) $value);
    }

    private function formatLimit(int $value): string
    {
        if ($value === -1) {
            return 'Ilimitado';
        }

        return number_format($value, 0, '.', ',');
    }
}
