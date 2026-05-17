<?php

namespace App\Providers;

use App\Tenancy\Resolvers\SubdomainResolver;
use App\Tenancy\TenantManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registra los componentes del sistema multi-tenant.
 *
 * Se carga vía bootstrap/providers.php junto a los demás providers
 * de la app. Se mantiene como provider regular (no deferrable) porque
 * el middleware `tenant` lo necesita en prácticamente todos los
 * requests del grupo `web`.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton: queremos UNA instancia por request/proceso para
        // que el contexto resuelto sea compartido por todo el código.
        $this->app->singleton(TenantManager::class);
        $this->app->singleton(SubdomainResolver::class);

        // Alias de conveniencia para tests / debugging.
        $this->app->alias(TenantManager::class, 'tenant.manager');
        $this->app->alias(SubdomainResolver::class, 'tenant.resolver.subdomain');
    }

    public function boot(): void
    {
        //
    }
}
