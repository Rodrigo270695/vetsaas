<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;

final class TenantModuleAccess
{
    public static function isEnabled(?Tenant $tenant, string $module): bool
    {
        if (! in_array($module, TenantModuleRegistry::ALL_KEYS, true)) {
            return true;
        }

        if ($tenant === null) {
            return true;
        }

        $disabled = self::disabledList($tenant);

        return ! in_array($module, $disabled, true);
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array<string, bool>
     */
    public static function filterCapabilities(?Tenant $tenant, array $capabilities): array
    {
        foreach (TenantModuleRegistry::CAPABILITY_MAP as $module => $capability) {
            if (! self::isEnabled($tenant, $module) && array_key_exists($capability, $capabilities)) {
                $capabilities[$capability] = false;
            }
        }

        return $capabilities;
    }

    public static function isTarifasTabEnabled(?Tenant $tenant, string $tab): bool
    {
        foreach (TenantModuleRegistry::TARIFAS_TAB_MAP as $module => $mappedTab) {
            if ($mappedTab === $tab && ! self::isEnabled($tenant, $module)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{enabled: array<string, bool>, deshabilitados: list<string>}
     */
    public static function snapshot(?Tenant $tenant): array
    {
        $disabled = self::disabledList($tenant);
        $enabled = [];

        foreach (TenantModuleRegistry::ALL_KEYS as $key) {
            $enabled[$key] = ! in_array($key, $disabled, true);
        }

        return [
            'enabled' => $enabled,
            'deshabilitados' => $disabled,
        ];
    }

    /**
     * @return list<array{group: string, modules: list<array{key: string, enabled: bool}>}>
     */
    public static function catalogForTenant(?Tenant $tenant): array
    {
        $snapshot = self::snapshot($tenant);
        $enabled = $snapshot['enabled'];
        $groups = [];

        foreach (TenantModuleRegistry::groups() as $group) {
            $modules = [];

            foreach ($group['modules'] as $key) {
                $modules[] = [
                    'key' => $key,
                    'enabled' => $enabled[$key] ?? true,
                ];
            }

            $groups[] = [
                'group' => $group['group'],
                'modules' => $modules,
            ];
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    private static function disabledList(?Tenant $tenant): array
    {
        if ($tenant === null) {
            return [];
        }

        $raw = $tenant->modulos_deshabilitados;

        if (! is_array($raw)) {
            return [];
        }

        return TenantModuleRegistry::validateKeys($raw);
    }
}
