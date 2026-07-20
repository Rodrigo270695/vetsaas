<?php

declare(strict_types=1);

namespace App\Support\Plan;

use App\Models\Paciente;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\TenantPlanOverride;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

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

    /**
     * Features que admiten extra/override por tenant (incluye cupo CPE mensual).
     *
     * @var list<string>
     */
    public const OVERRIDABLE_FEATURES = [
        'max_sedes',
        'max_usuarios',
        'max_pacientes',
        'max_propietarios',
        'max_productos',
        'max_comprobantes_mes',
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
        $tenant ??= self::tenant();
        $base = self::planBaseLimit($tenant, $feature);
        $row = self::activeOverride($tenant, $feature);

        if ($row !== null && $row->override !== null) {
            return $row->override < 0 ? null : $row->override;
        }

        if ($base === null) {
            return null;
        }

        $extra = $row !== null ? max(0, (int) $row->extra) : 0;

        return $base + $extra;
    }

    /**
     * Límite del plan sin extras del tenant.
     *
     * @return int|null null = ilimitado / sin plan
     */
    public static function planBaseLimit(?Tenant $tenant, string $feature): ?int
    {
        $plan = self::activePlan($tenant);

        if ($plan === null) {
            return null;
        }

        $value = $plan->resolveFeature($feature);

        if (is_numeric($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 0) {
            return null;
        }

        return $value;
    }

    public static function activeOverride(?Tenant $tenant, string $feature): ?TenantPlanOverride
    {
        if ($tenant === null || $tenant->id === null) {
            return null;
        }

        if (! in_array($feature, self::OVERRIDABLE_FEATURES, true)) {
            return null;
        }

        return TenantPlanOverride::forTenantFeature((string) $tenant->id, $feature);
    }

    /**
     * Extra activo para un feature (0 si no hay).
     */
    public static function extra(?Tenant $tenant, string $feature): int
    {
        $row = self::activeOverride($tenant, $feature);

        if ($row === null || $row->override !== null) {
            return 0;
        }

        return max(0, (int) $row->extra);
    }

    public static function currentCount(string $feature): int
    {
        $model = self::FEATURE_MODELS[$feature] ?? null;

        if ($model === null) {
            return 0;
        }

        try {
            if ($feature === 'max_usuarios' || $feature === 'max_sedes') {
                $tenantId = self::tenant()?->id;

                if ($tenantId === null) {
                    return 0;
                }

                if ($feature === 'max_usuarios') {
                    return User::query()->where('tenant_id', $tenantId)->count();
                }

                // sedes vive en public.*; no usar Schema::hasTable('sedes') con
                // search_path del tenant (current_schema = vet_*), que devolvería
                // false y dejaría used=0 aunque haya sedes.
                return Sede::query()->where('tenant_id', $tenantId)->count();
            }

            if (! self::tableExistsForModel($model)) {
                return 0;
            }

            return $model::query()->count();
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
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
     * @return array<string, array{limit: int|null, used: int, remaining: int|null, reached: bool, unlimited: bool, base: int|null, extra: int, semaphore: string, usage_pct: float|null}>|null
     */
    public static function snapshot(?Tenant $tenant = null): ?array
    {
        $tenant ??= self::tenant();

        if ($tenant === null) {
            return null;
        }

        try {
            $out = [];

            foreach (self::INT_LIMIT_FEATURES as $feature) {
                $base = self::planBaseLimit($tenant, $feature);
                $row = self::activeOverride($tenant, $feature);
                $extra = self::extra($tenant, $feature);
                $precio = $row !== null && $row->isPaid()
                    ? round((float) $row->precio_mensual, 2)
                    : 0.0;
                $limit = self::intLimit($tenant, $feature);
                $used = self::currentCount($feature);
                $unlimited = $limit === null;
                $usagePct = (! $unlimited && $limit > 0)
                    ? round(min(999.9, ($used / $limit) * 100), 1)
                    : null;

                $out[$feature] = [
                    'limit' => $limit,
                    'used' => $used,
                    'remaining' => $unlimited ? null : max(0, $limit - $used),
                    'reached' => ! $unlimited && $used >= $limit,
                    'unlimited' => $unlimited,
                    'base' => $base,
                    'extra' => $extra,
                    'precio_mensual' => $precio,
                    'is_paid_extra' => $precio > 0,
                    'semaphore' => ComprobantesQuota::semaphore($used, $limit, $unlimited),
                    'usage_pct' => $usagePct,
                ];
            }

            return $out;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  class-string  $modelClass
     */
    private static function tableExistsForModel(string $modelClass): bool
    {
        $table = (new $modelClass)->getTable();

        // Modelos con UsesPublicSchema (`public.sedes`, etc.): current_schema()
        // en un request tenant es vet_*, donde esa tabla no existe.
        if (DB::getDriverName() === 'pgsql' && str_contains($table, '.')) {
            [$schema, $name] = explode('.', $table, 2);

            return (bool) DB::selectOne(
                'select 1 from information_schema.tables where table_schema = ? and table_name = ? limit 1',
                [$schema, $name],
            );
        }

        $name = str_contains($table, '.')
            ? substr($table, strrpos($table, '.') + 1)
            : $table;

        return Schema::hasTable($name);
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
