<?php

namespace App\Auth;

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use InvalidArgumentException;

use function Illuminate\Support\enum_value;

/**
 * Variante del PasswordBrokerManager de Laravel que usa el
 * {@see TenantAwarePasswordTokenRepository} en lugar del repositorio
 * por defecto.
 *
 * Se registra como singleton en el contenedor reemplazando a
 * `auth.password`. Toda llamada a `Password::sendResetLink()`,
 * `Password::reset()`, etc., termina pasando por aquí.
 */
class TenantAwarePasswordBrokerManager extends PasswordBrokerManager
{
    protected function resolve($name)
    {
        $name = is_string($name) ? $name : (string) enum_value($name);
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Password resetter [{$name}] is not defined.");
        }

        return new PasswordBroker(
            $this->createTokenRepository($config),
            $this->app['auth']->createUserProvider($config['provider'] ?? null),
            $this->app['events'] ?? null,
            timeboxDuration: $this->app['config']->get('auth.timebox_duration', 200000),
        );
    }

    protected function createTokenRepository(array $config)
    {
        $key = $this->app['config']['app.key'];

        if (is_string($key) && str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return new TenantAwarePasswordTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}
