<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Services\Subscriptions\TenantSubscriptionAccess;
use App\Tenancy\TenantManager;
use Closure;

/**
 * Ejecuta un callback por cada tenant con acceso activo (suscripción vigente).
 */
final class ActiveTenantIterator
{
    public function __construct(
        private readonly TenantManager $manager,
        private readonly TenantSubscriptionAccess $access,
    ) {}

    /**
     * @param  Closure(Tenant): void  $callback
     */
    public function each(Closure $callback): void
    {
        Tenant::query()
            ->whereIn('estado', ['trial', 'active'])
            ->orderBy('slug')
            ->each(function (Tenant $tenant) use ($callback): void {
                if (! $this->access->allowsAccess($tenant)) {
                    return;
                }

                if (! is_string($tenant->slug) || $tenant->slug === '') {
                    return;
                }

                $this->manager->runForSlug($tenant->slug, function () use ($tenant, $callback): void {
                    $callback($tenant);
                });
            });
    }
}
