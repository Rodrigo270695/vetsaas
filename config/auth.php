<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Arquitectura "single-login + datos aislados":
    |
    | Un único modelo `App\Models\User` autentica a TODOS los usuarios:
    |   - Superadmin / staff central (tenant_id = NULL).
    |   - Empleados de cada clínica (tenant_id = uuid del tenant).
    |
    | El aislamiento ocurre a nivel de DATOS OPERATIVOS (pacientes,
    | citas, etc. siguen viviendo en schemas separados por tenant). La
    | identidad y permisos son globales: Spatie roles & permissions
    | decide qué puede ver y hacer cada usuario; el middleware
    | `MatchUserTenant` impide que un empleado entre al host equivocado.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            // Custom driver: ver `App\Providers\AuthServiceProvider`.
            // Equivale a `eloquent` pero inyecta `tenant_id` (deducido
            // del host) en cada `retrieveByCredentials`. Es lo que
            // permite que el mismo email pueda existir en varias
            // clínicas sin colisionar al hacer login / reset.
            'driver' => 'tenant-eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
