<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nubefact (facturación electrónica SUNAT)
    |--------------------------------------------------------------------------
    |
    | Cada clínica guarda RUTA + TOKEN en cfg_clinic_settings. POST a la
    | RUTA del panel con header Authorization: Token token="...".
    |
    */
    'nubefact' => [
        'base_url' => env('NUBEFACT_API_URL', 'https://api.nubefact.com/api/v1'),
        'timeout' => (int) env('NUBEFACT_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Perú (consulta RUC SUNAT vía apiperu.dev)
    |--------------------------------------------------------------------------
    |
    | Credenciales solo en servidor. El panel del tenant llama a un endpoint
    | Laravel que a su vez consulta la API (nunca expongas el token al browser).
    |
    */
    'apiperu' => [
        'base_url' => env('APIPERU_BASE_URL', 'https://apiperu.dev/api'),
        'token' => env('APIPERU_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | APISUNAT / Lucode (consulta DNI y RUC — APIs de apoyo)
    |--------------------------------------------------------------------------
    |
    | Respaldo cuando apiperu.dev agota cuota o no responde. Si no se define
    | APISUNAT_LOOKUP_TOKEN, se intenta el token APISUNAT de la clínica (FEL).
    |
    */
    'apisunat_lookup' => [
        'base_url' => env('APISUNAT_LOOKUP_BASE_URL', 'https://dev.apisunat.pe/api/v1'),
        'token' => env('APISUNAT_LOOKUP_TOKEN'),
    ],

];
