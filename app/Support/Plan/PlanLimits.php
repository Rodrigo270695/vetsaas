<?php

declare(strict_types=1);

namespace App\Support\Plan;

use App\Models\Paciente;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantManager;

/**
 * Límites numéricos y módulos del plan activo del tenant.
 *
 * -1 (o menor) en `plan_features.valor_int` = ilimitado.
 */
final class PlanLimits
{
    /** @var list<string> */
    public const INT_LIMIT_FEATURES = [
        'max_sedes',
        'max_usuarios',
        'max_pacientes',
        'max_propietarios',
        'max_productos',
    ];

    /** @var array<string, class-string> */
    private const FEATURE_MODELS = [
        'max_sedes' => Sede::class,
        'max_usuarios' => User::class,
        'max_pacientes' => Paciente::class,
        'max_propietarios' => Propietario::class,
        'max_productos' => Producto::class,
    ];

    public static function tenant(): ?Tenant
    {
        return app(TenantManager::class)->current()?->tenant;
    }

    public static function activePlan(?Tenant $tenant = null): ?Plan
    {
        $tenant ??= self::tenant();

        if ($tenant === null) {
            return null;
        }

        $plan = $tenant->activeSubscription()?->plan;

        if ($plan !== null) {
            return $plan;
        }

        // Sin suscripción activa (p. ej. demos locales): límites del plan free.
        return Plan::query()
            ->where('codigo', 'free')
            ->where('activo', true)
            ->first();
    }

    /**
     * @return int|null null = ilimitado
     */
    public static function intLimit(?Tenant $tenant, string $feature): ?int
    {
        $plan = self::activePlan($tenant);

        if ($plan === null) {
            return null;
        }

        $value = $plan->resolveFeature($feature);

        if (! is_int($value) || $value < 0) {
            return null;
        }

        return $value;
    }

    public static function currentCount(string $feature): int
    {
        $model = self::FEATURE_MODELS[$feature] ?? null;

        if ($model === null) {
            return 0;
        }

        if ($feature === 'max_usuarios' || $feature === 'max_sedes') {
            $tenantId = self::tenant()?->id;

            if ($tenantId === null) {
                return 0;
            }

            if ($feature === 'max_usuarios') {
                return User::query()->where('tenant_id', $tenantId)->count();
            }

            return Sede::query()->where('tenant_id', $tenantId)->count();
        }

        return $model::query()->count();
    }

    public static function wouldExceed(
        string $feature,
        ?Tenant $tenant = null,
        ?int $currentCount = null,
        int $adding = 1,
    ): bool {
        $limit = self::intLimit($tenant, $feature);

        if ($limit === null) {
            return false;
        }

        $currentCount ??= self::currentCount($feature);

        return ($currentCount + $adding) > $limit;
    }

    public static function isReached(string $feature, ?Tenant $tenant = null): bool
    {
        return self::wouldExceed($feature, $tenant, null, 1);
    }

    public static function message(string $feature, ?int $limit = null): string
    {
        $limit ??= self::intLimit(self::tenant(), $feature);

        $key = 'plan.limits.'.$feature;

        return __($key, [
            'limit' => $limit ?? 0,
            'plan' => self::activePlan()?->nombre ?? __('plan.limits.unknown_plan'),
        ]);
    }

    /**
     * Consumo vs límite para Inertia (botones deshabilitados, barras, etc.).
     *
     * @return array<string, array{limit: int|null, used: int, remaining: int|null, reached: bool, unlimited: bool}>|null
     */
    public static function snapshot(?Tenant $tenant = null): ?array
    {
        $tenant ??= self::tenant();

        if ($tenant === null) {
            return null;
        }

        $out = [];

        foreach (self::INT_LIMIT_FEATURES as $feature) {
            $limit = self::intLimit($tenant, $feature);
            $used = self::currentCount($feature);
            $unlimited = $limit === null;

            $out[$feature] = [
                'limit' => $limit,
                'used' => $used,
                'remaining' => $unlimited ? null : max(0, $limit - $used),
                'reached' => ! $unlimited && $used >= $limit,
                'unlimited' => $unlimited,
            ];
        }

        return $out;
    }

    public static function moduleEnabled(?Tenant $tenant, string $feature): bool
    {
        $plan = self::activePlan($tenant);

        if ($plan === null) {
            return false;
        }

        $meta = Plan::FEATURE_CATALOG[$feature] ?? null;

        if (($meta['type'] ?? null) !== 'bool') {
            return false;
        }

        return (bool) $plan->resolveFeature($feature);
    }
}
