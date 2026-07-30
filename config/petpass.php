<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AlmaPet ID / PetPass — bridge desde VetSaaS
    |--------------------------------------------------------------------------
    */

    'enabled' => (bool) env('PETPASS_ENABLED', env('ALMAPET_ENABLED', false)),

    'base_url' => rtrim((string) env('PETPASS_BASE_URL', env('ALMAPET_BASE_URL', 'https://almapetid.com')), '/'),

    // Si el .env deja PETPASS_HANDOFF_PATH vacío, env() NO usa el default de Laravel.
    'handoff_path' => (static function (): string {
        $path = trim((string) env('PETPASS_HANDOFF_PATH', '/api/v1/handoff'));

        return $path !== '' ? $path : '/api/v1/handoff';
    })(),

    // Alta directa sin cobro (dueño activa después).
    'register_path' => (static function (): string {
        $path = trim((string) env('PETPASS_REGISTER_PATH', '/api/v1/handoff/register'));

        return $path !== '' ? $path : '/api/v1/handoff/register';
    })(),

    'handoff_secret' => (string) env('PETPASS_HANDOFF_SECRET', env('ALMAPET_HANDOFF_SECRET', '')),

    'webhook_secret' => (string) env('PETPASS_WEBHOOK_SECRET', env('ALMAPET_WEBHOOK_SECRET', '')),

    'timeout_seconds' => (int) env('PETPASS_HTTP_TIMEOUT', 15),

    'support_phone_display' => env('PETPASS_SUPPORT_PHONE_DISPLAY', '976 809 804'),

];
