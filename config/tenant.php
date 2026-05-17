<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Schema PostgreSQL al migrar el tenant (uso interno de Artisan)
    |--------------------------------------------------------------------------
    |
    | Solo se usa al ejecutar:
    |
    |   php artisan vetsaas:tenant-migrate <schema>
    |   php artisan vetsaas:tenant-migrate-all   # pendientes en todos los tenants
    |
    | El comando setea esta clave en runtime y las migraciones de
    | `database/migrations/tenant/` la leen para aplicar
    | `SET search_path TO "<schema>", public` y crear las tablas
    | dentro del schema correcto. Mantener vacío fuera de ese comando
    | (evita que `php artisan migrate` por error toque schemas de tenant).
    |
    */
    'migration_schema' => env('TENANT_MIGRATION_SCHEMA'),

    /*
    |--------------------------------------------------------------------------
    | Dominios "centrales" del SaaS (panel del superadmin)
    |--------------------------------------------------------------------------
    |
    | Hosts que NUNCA se interpretan como subdominios de tenant. Aquí vive
    | el panel `/plataforma/*` y rutas internas del SaaS. Si un request
    | entra con uno de estos hosts, `ResolveTenant` no hace nada y
    | `EnsureNoTenant` permite el acceso al panel.
    |
    | En producción quedará algo así:
    |   TENANT_CENTRAL_DOMAINS="app.vetsaas.com,admin.vetsaas.com"
    |
    | En local con Herd suele bastar `vetsaas.test,localhost,127.0.0.1`
    | para que `php artisan serve` y los subdominios `.vetsaas.test`
    | convivan sin colisión.
    |
    */
    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TENANT_CENTRAL_DOMAINS', 'localhost,127.0.0.1,vetsaas.test'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Dominio raíz para los subdominios de tenant
    |--------------------------------------------------------------------------
    |
    | Define el "sufijo" desde el cual se extrae el slug del subdominio.
    | Por ejemplo, con `vetsaas.test` como root y un host como
    | `clinica-rivera.vetsaas.test`, el slug resuelto será `clinica-rivera`.
    |
    | Hosts que NO terminen en este sufijo nunca resuelven a un tenant
    | (independiente de que también figuren en `central_domains`).
    |
    */
    'root_domain' => env('TENANT_ROOT_DOMAIN', 'vetsaas.test'),

    /*
    |--------------------------------------------------------------------------
    | Prefijo aplicado al nombre del schema físico
    |--------------------------------------------------------------------------
    |
    | Solo se usa para *validar* el schema en runtime (no para construirlo:
    | el nombre real vive en `tenants.schema_name`). Si en algún momento
    | quieres restringir a "solo schemas que empiecen con vet_", lo pones
    | aquí. Vacío = sin restricción de prefijo.
    |
    */
    'schema_prefix' => env('TENANT_SCHEMA_PREFIX', 'vet_'),

    /*
    |--------------------------------------------------------------------------
    | Estados que SÍ pueden acceder al subdominio
    |--------------------------------------------------------------------------
    |
    | Si el `estado` del tenant no está en esta lista, el middleware
    | `ResolveTenant` lanza `TenantSuspendedException` y devuelve 403.
    | `grace` se incluye porque es un periodo de cortesía tras impago
    | en el que el cliente aún debe poder entrar (con banner de aviso).
    |
    */
    'allowed_states' => ['active', 'trial', 'grace'],

    /*
    |--------------------------------------------------------------------------
    | TTL del cache de resolución (segundos)
    |--------------------------------------------------------------------------
    |
    | Evita golpear `tenants` por cada request. 60s es un buen balance:
    | si se suspende o cancela un tenant desde el panel SaaS, el cambio
    | tarda como máximo 1 minuto en propagarse. El TenantController
    | invalida explícitamente el cache en suspend/resume/destroy/update
    | para que sea inmediato.
    |
    | 0 = sin cache (golpea BD en cada request).
    |
    */
    'cache_ttl' => (int) env('TENANT_CACHE_TTL', 60),

];
