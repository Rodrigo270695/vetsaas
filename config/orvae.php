<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Integración Orvae PE -> VetSaaS
    |--------------------------------------------------------------------------
    |
    | Mismo patrón usado entre Orvae PE y Aula Virtual: Orvae PE hace POST al
    | endpoint interno de VetSaaS firmado con HMAC-SHA256 sobre
    |     "{timestamp}.{raw_body}"
    | Cabeceras esperadas:
    |   X-Orvae-Timestamp:    epoch unix en segundos
    |   X-Orvae-Signature:    "sha256=<hex>"
    |   X-Idempotency-Key:    clave única por pedido Orvae (UUID o similar)
    |
    */

    'provision' => [
        'hmac_secret' => env('ORVAE_PROVISION_HMAC_SECRET'),
        'max_skew_seconds' => (int) env('ORVAE_PROVISION_MAX_SKEW_SECONDS', 300),
        'idempotency_ttl_days' => (int) env('ORVAE_PROVISION_IDEMPOTENCY_TTL_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Construcción de URL de ingreso del cliente
    |--------------------------------------------------------------------------
    |
    | El subdominio del tenant se forma como: {slug}.{tenant_domain}
    | y la URL final como: {tenant_scheme}://{slug}.{tenant_domain}{login_path}
    |
    */
    'tenant' => [
        'scheme' => env('VETSAAS_TENANT_SCHEME', 'https'),
        // Deprecado: usar config('tenant.root_domain'). Se mantiene por compatibilidad.
        'domain' => env('VETSAAS_TENANT_DOMAIN', env('TENANT_ROOT_DOMAIN', 'vetsaas.orvae.pe')),
        'login_path' => env('VETSAAS_TENANT_LOGIN_PATH', '/login'),
    ],

];
