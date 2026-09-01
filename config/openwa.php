<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenWA (WhatsApp gateway self-hosted)
    |--------------------------------------------------------------------------
    |
    | API: https://wa.vetsaas.orvae.pe/api
    | Admin/dashboard: https://wa-admin.vetsaas.orvae.pe
    |
    | Cada tenant (clínica) tiene una sesión OpenWA nombrada con su slug.
    | VetSaaS envía mensajes vía X-API-Key hacia esa sesión.
    |
    */
    'enabled' => (bool) env('OPENWA_ENABLED', false),

    'api_url' => rtrim((string) env('OPENWA_API_URL', 'https://wa.vetsaas.orvae.pe'), '/'),

    'api_key' => env('OPENWA_API_KEY'),

    'admin_url' => rtrim((string) env('OPENWA_ADMIN_URL', 'https://wa-admin.vetsaas.orvae.pe'), '/'),

    'timeout_seconds' => (int) env('OPENWA_TIMEOUT_SECONDS', 12),

    'document_timeout_seconds' => (int) env('OPENWA_DOCUMENT_TIMEOUT_SECONDS', 90),

    'document_max_bytes' => (int) env('OPENWA_DOCUMENT_MAX_BYTES', 16 * 1024 * 1024),

    'max_attempts' => (int) env('OPENWA_QUEUE_MAX_ATTEMPTS', 3),

    /*
    | Sesión OpenWA para mensajes de plataforma (avisos de renovación a clínicas).
    | Crear y escanear QR en wa-admin con este nombre (ej. vetsaas-platform).
    */
    'platform_session_name' => env('OPENWA_PLATFORM_SESSION_NAME', 'vetsaas-platform'),

    /*
    | Segundos de espera entre reintentos al reconectar una sesión caída.
    | En tests se puede poner 0.
    */
    'reconnect_poll_seconds' => (int) env('OPENWA_RECONNECT_POLL_SECONDS', 3),

    /*
    | El cron de sync no debe listar/arrancar las ~50 clínicas de un golpe:
    | OpenWA responde 429 (ThrottlerException). Rotamos un lote por corrida,
    | cacheamos GET /api/sessions y pausamos si hay rate-limit.
    */
    'list_sessions_cache_seconds' => (int) env('OPENWA_LIST_SESSIONS_CACHE_SECONDS', 25),

    'sync_max_tenants_per_run' => (int) env('OPENWA_SYNC_MAX_TENANTS', 8),

    'sync_max_reconnects_per_run' => (int) env('OPENWA_SYNC_MAX_RECONNECTS', 2),

    'sync_pause_ms' => (int) env('OPENWA_SYNC_PAUSE_MS', 700),

    'rate_limit_cooldown_seconds' => (int) env('OPENWA_RATE_LIMIT_COOLDOWN', 240),

];
