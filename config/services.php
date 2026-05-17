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
    | Cada clínica aporta su token en cfg_clinic_settings; la URL base es
    | común: POST {base_url}/{token} con JSON operacion=generar_comprobante.
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

];
