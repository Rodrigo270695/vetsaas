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

    'timeout_seconds' => (int) env('OPENWA_TIMEOUT_SECONDS', 30),

    'max_attempts' => (int) env('OPENWA_QUEUE_MAX_ATTEMPTS', 3),

    /*
    | Sesión OpenWA para mensajes de plataforma (avisos de renovación a clínicas).
    | Crear y escanear QR en wa-admin con este nombre (ej. vetsaas-platform).
    */
    'platform_session_name' => env('OPENWA_PLATFORM_SESSION_NAME', 'vetsaas-platform'),

];
