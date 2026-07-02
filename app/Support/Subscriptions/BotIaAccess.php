<?php

declare(strict_types=1);

namespace App\Support\Subscriptions;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

/**
 * Acceso al panel Asistente IA: permiso explícito o fallback para clínicas
 * cuyo rol aún no incluye `comunicaciones-bot-ia.*` tras el deploy.
 */
final class BotIaAccess
{
    public static function isActiveForTenant(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        $subscription = $tenant->subscriptions()
            ->orderByDesc('created_at')
            ->first();

        return SubscriptionBotIaAddon::isActive($subscription);
    }

    public static function isActiveForCurrentTenant(): bool
    {
        $tenantId = tenant_id();

        if ($tenantId === null) {
            return false;
        }

        return self::isActiveForTenant(Tenant::query()->find($tenantId));
    }

    public static function userCanView(?User $user, ?Tenant $tenant = null): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasPermissionTo('comunicaciones-bot-ia.view')) {
            return true;
        }

        if (self::userHasViewFallback($user)) {
            return true;
        }

        $tenant ??= self::resolveCurrentTenant();

        if (! self::isActiveForTenant($tenant) && self::userHasComunicacionesAccess($user)) {
            return true;
        }

        return false;
    }

    public static function userCanManage(?User $user, ?Tenant $tenant = null): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasPermissionTo('comunicaciones-bot-ia.manage')) {
            return true;
        }

        $tenant ??= self::resolveCurrentTenant();

        return self::isActiveForTenant($tenant) && self::userHasManageFallback($user);
    }

    public static function userHasViewFallback(User $user): bool
    {
        return $user->hasPermissionTo('config-general.view')
            || $user->hasPermissionTo('config-general.update')
            || $user->hasPermissionTo('comunicaciones-cola.manage');
    }

    public static function userHasComunicacionesAccess(User $user): bool
    {
        return $user->hasPermissionTo('comunicaciones-cola.view')
            || $user->hasPermissionTo('comunicaciones-historico.view')
            || $user->hasPermissionTo('comunicaciones-cola.manage')
            || $user->hasPermissionTo('config-general.view')
            || $user->hasPermissionTo('config-general.update');
    }

    public static function userHasManageFallback(User $user): bool
    {
        return $user->hasPermissionTo('config-general.update')
            || $user->hasPermissionTo('comunicaciones-cola.manage');
    }

    /**
     * @return array{activo: bool, precio_mensual: string|null}
     */
    public static function navPayload(?Tenant $tenant): array
    {
        if ($tenant === null) {
            return ['activo' => false, 'precio_mensual' => null];
        }

        $subscription = $tenant->subscriptions()
            ->orderByDesc('created_at')
            ->first();

        $active = SubscriptionBotIaAddon::isActive($subscription);

        return [
            'activo' => $active,
            'precio_mensual' => $active
                ? SubscriptionBotIaAddon::precioMensual($subscription)
                : null,
        ];
    }

    private static function resolveCurrentTenant(): ?Tenant
    {
        $tenantId = tenant_id();

        if ($tenantId === null) {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }
}
