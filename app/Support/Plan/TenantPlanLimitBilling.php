<?php

declare(strict_types=1);

namespace App\Support\Plan;

use App\Models\Tenant;
use App\Models\TenantPlanOverride;

/**
 * Add-ons de límite pagados (extras con precio_mensual) para sumar al cobro de renovación.
 */
final class TenantPlanLimitBilling
{
    /** @var array<string, string> */
    private const FEATURE_LABELS = [
        'max_sedes' => 'Sede(s) extra',
        'max_usuarios' => 'Usuario(s) extra',
        'max_pacientes' => 'Paciente(s) extra',
        'max_propietarios' => 'Propietario(s) extra',
        'max_productos' => 'Producto(s) extra',
        'max_comprobantes_mes' => 'Comprobantes extra (cupo)',
    ];

    /**
     * @return list<array{key: string, feature: string, label: string, amount: float, extra: int}>
     */
    public static function addons(?Tenant $tenant): array
    {
        if ($tenant === null) {
            return [];
        }

        $rows = TenantPlanOverride::query()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->whereNotNull('precio_mensual')
            ->where('precio_mensual', '>', 0)
            ->orderBy('feature')
            ->get();

        $addons = [];

        foreach ($rows as $row) {
            $amount = round((float) $row->precio_mensual, 2);
            if ($amount <= 0) {
                continue;
            }

            // Solo cobramos si el override aporta capacidad (extra o tope).
            if ((int) $row->extra <= 0 && $row->override === null) {
                continue;
            }

            $feature = (string) $row->feature;
            $label = self::FEATURE_LABELS[$feature] ?? $feature;
            if ((int) $row->extra > 0) {
                $label .= ' (+'.(int) $row->extra.')';
            }

            $addons[] = [
                'key' => 'limit_extra:'.$feature,
                'feature' => $feature,
                'label' => $label,
                'amount' => $amount,
                'extra' => (int) $row->extra,
            ];
        }

        return $addons;
    }

    public static function totalAmount(?Tenant $tenant): float
    {
        return round(array_sum(array_column(self::addons($tenant), 'amount')), 2);
    }
}
