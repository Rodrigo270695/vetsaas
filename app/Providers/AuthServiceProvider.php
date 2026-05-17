<?php

namespace App\Providers;

use App\Auth\TenantAwareEloquentUserProvider;
use App\Auth\TenantAwarePasswordBrokerManager;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * Provider de autenticación con conciencia de tenant.
 *
 * Engancha dos puntos del stack de auth para que TODO el flujo
 * (login, password reset, validación contextual) respete el
 * subdominio del request:
 *
 *   1. Driver `tenant-eloquent` para `config/auth.php`.
 *      Filtra cualquier `retrieveByCredentials` por `tenant_id`
 *      derivado del host. Sin esto, un mismo email en dos clínicas
 *      colisionaría al hacer login o reset.
 *
 *   2. Reemplazo del singleton `auth.password` por nuestro manager
 *      que usa `TenantAwarePasswordTokenRepository`. Sin esto, la
 *      tabla `password_reset_tokens` (con email como PK) sobreescribe
 *      tokens cuando dos cuentas comparten email.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // `auth.password` lo registra Laravel desde un DeferrableProvider
        // (`PasswordResetServiceProvider`), así que no nos sirve un
        // `singleton()` directo: el deferred lo reemplaza más tarde.
        // Con `extend` envolvemos la resolución final y devolvemos
        // SIEMPRE nuestro broker manager, sin importar cuándo se
        // construya el original.
        $this->app->extend('auth.password', function ($original, $app): TenantAwarePasswordBrokerManager {
            return new TenantAwarePasswordBrokerManager($app);
        });

        $this->app->extend('auth.password.broker', function ($original, $app) {
            return $app->make('auth.password')->broker();
        });
    }

    public function boot(): void
    {
        Auth::provider('tenant-eloquent', function ($app, array $config): TenantAwareEloquentUserProvider {
            return new TenantAwareEloquentUserProvider(
                $app->make(Hasher::class),
                $config['model'],
            );
        });
    }
}
