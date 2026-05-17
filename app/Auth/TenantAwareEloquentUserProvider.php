<?php

namespace App\Auth;

use App\Tenancy\TenantManager;
use Illuminate\Auth\EloquentUserProvider;

/**
 * User provider que inyecta automáticamente el `tenant_id` del host
 * actual en las credenciales antes de buscar al usuario.
 *
 * Por qué existe:
 *   En arquitectura single-login, el mismo email puede pertenecer a
 *   varios usuarios (uno por clínica + uno central). Cuando Laravel
 *   pregunta "¿hay un usuario con este email?", la respuesta solo es
 *   correcta si añadimos el filtro de host: "¿hay uno con este email
 *   QUE PERTENEZCA al host actual?".
 *
 * Dónde se usa:
 *   - **Login** vía Fortify (`Fortify::authenticateUsing` también lo
 *     usaba; ahora el provider hace lo mismo automáticamente para
 *     cualquier flujo de auth).
 *   - **Password reset**: cuando el broker llama a
 *     `retrieveByCredentials(['email' => $email])`, este provider lo
 *     amplía a `['email' => $email, 'tenant_id' => $hostTenantId]`.
 *
 * Comportamiento exacto:
 *   - Si el caller YA pasó `tenant_id` en las credenciales (caso de
 *     tests o jobs), lo respetamos tal cual.
 *   - Si NO pasó `tenant_id`, lo deducimos del `TenantManager`:
 *       · Host central → `tenant_id = null`.
 *       · Subdominio de tenant → `tenant_id = uuid(tenant)`.
 *
 * Registrado en {@see \App\Providers\AuthServiceProvider} como driver
 * `tenant-eloquent` y referenciado en `config/auth.php`.
 */
class TenantAwareEloquentUserProvider extends EloquentUserProvider
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        $credentials = $this->withHostTenantId($credentials);

        return parent::retrieveByCredentials($credentials);
    }

    /**
     * Inyecta el `tenant_id` del host actual si el caller no lo pasó.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    protected function withHostTenantId(array $credentials): array
    {
        if (array_key_exists('tenant_id', $credentials)) {
            return $credentials;
        }

        $manager = app(TenantManager::class);
        $credentials['tenant_id'] = $manager->check() ? $manager->current()?->id() : null;

        return $credentials;
    }
}
