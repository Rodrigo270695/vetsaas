<?php

/**
 * Helpers globales para acceder al tenant activo desde cualquier
 * archivo PHP sin necesidad de importar el manager.
 *
 * Se cargan vía `composer.json -> autoload.files`.
 */

use App\Tenancy\TenantContext;
use App\Tenancy\TenantManager;

if (! function_exists('current_tenant')) {
    /**
     * Devuelve el contexto del tenant activo, o `null` si estamos en el
     * dominio central (panel SaaS). Pensado para uso en views, jobs y
     * código que no quiere depender de la inyección de servicios.
     */
    function current_tenant(): ?TenantContext
    {
        return app(TenantManager::class)->current();
    }
}

if (! function_exists('tenant_id')) {
    /**
     * Atajo: UUID del tenant activo, o `null` si no hay tenant.
     *
     * Útil para sembrar columnas `created_by`, llaves de cache,
     * logs estructurados, etc.
     */
    function tenant_id(): ?string
    {
        return app(TenantManager::class)->id();
    }
}

if (! function_exists('is_public_demo_tenant')) {
    /**
     * Tenant público de demostración (slug fijo `demo`).
     * Ahí los roles Spatie son globales: no se deben editar desde la UI.
     */
    function is_public_demo_tenant(): bool
    {
        return current_tenant()?->slug === 'demo';
    }
}
