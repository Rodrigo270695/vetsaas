<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\TenantRolesSeeder;

/**
 * Helpers RBAC por tenant (Spatie teams) para tests Feature.
 */
final class TenantRbac
{
    public static function seedAndAssign(User $user, string $role = 'admin_clinica'): void
    {
        $tenantId = $user->tenant_id;
        if ($tenantId === null || $tenantId === '') {
            throw new \InvalidArgumentException('Usuario sin tenant_id; no se puede asignar rol de clínica.');
        }

        $tenantId = (string) $tenantId;
        (new TenantRolesSeeder)->seedForTenant($tenantId);

        $previous = getPermissionsTeamId();
        setPermissionsTeamId($tenantId);

        try {
            $user->assignRole($role);
        } finally {
            setPermissionsTeamId($previous);
        }
    }
}
