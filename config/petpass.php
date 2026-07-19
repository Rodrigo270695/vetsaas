<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AlmaPet ID / PetPass — bridge desde VetSaaS
    |--------------------------------------------------------------------------
    */

    'enabled' => (bool) env('PETPASS_ENABLED', env('ALMAPET_ENABLED', false)),

    'base_url' => rtrim((string) env('PETPASS_BASE_URL', env('ALMAPET_BASE_URL', 'https://almapetid.com')), '/'),

    'handoff_path' => (string) env('PETPASS_HANDOFF_PATH', '/api/v1/handoff'),

    'handoff_secret' => (string) env('PETPASS_HANDOFF_SECRET', env('ALMAPET_HANDOFF_SECRET', '')),

    'webhook_secret' => (string) env('PETPASS_WEBHOOK_SECRET', env('ALMAPET_WEBHOOK_SECRET', '')),

    'timeout_seconds' => (int) env('PETPASS_HTTP_TIMEOUT', 15),

];
