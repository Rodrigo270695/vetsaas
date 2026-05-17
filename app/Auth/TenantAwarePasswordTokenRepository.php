<?php

namespace App\Auth;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Support\Carbon;

/**
 * Repositorio de tokens de reset que segrega por tenant.
 *
 * La implementación de Laravel asume que el email es único globalmente
 * y por tanto usa SOLO `email` como clave en la tabla
 * `password_reset_tokens`. En VetSaaS el email se repite legalmente
 * (mismo correo puede ser admin en Clínica A y veterinario en Clínica
 * B). Sin este wrap, generar un reset en una clínica invalida los
 * tokens vigentes de cualquier otra cuenta con el mismo email.
 *
 * Solución: añadir `tenant_id` (nullable = central) a la tabla y
 * usarlo SIEMPRE como segunda dimensión de filtrado y de pk lógica.
 *
 * `App\Models\User` extiende `Authenticatable` y por tanto cumple la
 * interfaz `CanResetPassword`. Aquí leemos `$user->tenant_id`
 * directamente (la propiedad existe gracias a la migración de Fase
 * 2.5-bis).
 */
class TenantAwarePasswordTokenRepository extends DatabaseTokenRepository
{
    public function create(CanResetPasswordContract $user)
    {
        $email = $user->getEmailForPasswordReset();
        $tenantId = $this->tenantIdOf($user);

        $this->deleteExisting($user);

        $token = $this->createNewToken();

        $this->getTable()->insert([
            'email' => $email,
            'tenant_id' => $tenantId,
            'token' => $this->hasher->make($token),
            'created_at' => new Carbon,
        ]);

        return $token;
    }

    protected function deleteExisting(CanResetPasswordContract $user)
    {
        return $this->scopedQuery($user)->delete();
    }

    public function exists(CanResetPasswordContract $user, #[\SensitiveParameter] $token)
    {
        $record = (array) $this->scopedQuery($user)->first();

        return $record &&
            ! $this->tokenExpired($record['created_at']) &&
            $this->hasher->check($token, $record['token']);
    }

    public function recentlyCreatedToken(CanResetPasswordContract $user)
    {
        $record = (array) $this->scopedQuery($user)->first();

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    public function delete(CanResetPasswordContract $user)
    {
        $this->deleteExisting($user);
    }

    /**
     * Builder ya filtrado por (email, tenant_id) del usuario.
     *
     * Usar `whereNull` cuando el usuario es central (tenant_id null)
     * evita que `where('tenant_id', null)` se compile como `= NULL`
     * (que siempre da false).
     */
    protected function scopedQuery(CanResetPasswordContract $user)
    {
        $query = $this->getTable()
            ->where('email', $user->getEmailForPasswordReset());

        $tenantId = $this->tenantIdOf($user);

        return $tenantId === null
            ? $query->whereNull('tenant_id')
            : $query->where('tenant_id', $tenantId);
    }

    /**
     * Extrae el `tenant_id` del usuario de forma defensiva: si el
     * modelo no expone la propiedad (e.g. otro modelo que implemente
     * CanResetPassword) tratamos al usuario como central.
     */
    protected function tenantIdOf(CanResetPasswordContract $user): ?string
    {
        $tenantId = property_exists($user, 'tenant_id')
            ? $user->tenant_id
            : (method_exists($user, 'getAttribute') ? $user->getAttribute('tenant_id') : null);

        return $tenantId === null ? null : (string) $tenantId;
    }
}
