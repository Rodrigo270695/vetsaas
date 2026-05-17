<?php

namespace App\Tenancy\Facades;

use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Facade;

/**
 * Facade de acceso al {@see TenantManager}.
 *
 * Uso típico:
 *
 *   use App\Tenancy\Facades\Tenant;
 *
 *   if (Tenant::check()) {
 *       $razonSocial = Tenant::current()->razonSocial();
 *   }
 *
 * En código nuevo se recomienda inyectar `TenantManager` por constructor
 * (más testeable). Esta facade existe para callbacks de rutas, vistas
 * Blade legacy y comandos artisan rápidos.
 *
 * @method static ?\App\Tenancy\TenantContext current()
 * @method static bool check()
 * @method static ?string id()
 * @method static ?string schema()
 * @method static ?string slug()
 * @method static \App\Tenancy\TenantContext resolveBySlug(string $slug, ?\Illuminate\Database\ConnectionInterface $connection = null)
 * @method static \App\Tenancy\TenantContext resolveById(string $id, ?\Illuminate\Database\ConnectionInterface $connection = null)
 * @method static void forget(?\Illuminate\Database\ConnectionInterface $connection = null)
 * @method static mixed runForSlug(string $slug, callable $callback)
 * @method static void flushCacheFor(\App\Models\Tenant $tenant)
 *
 * @see TenantManager
 */
class Tenant extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TenantManager::class;
    }
}
