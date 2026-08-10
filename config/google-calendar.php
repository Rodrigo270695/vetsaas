<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Google Calendar + Meet (SalesBot tours)
    |--------------------------------------------------------------------------
    |
    | OAuth de una sola cuenta organizadora (la tuya). El refresh token se
    | guarda cifrado en storage tras conectar desde el panel.
    |
    */

    'enabled' => (bool) env('GOOGLE_CALENDAR_ENABLED', true),

    'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET', ''),

    /*
    | Debe coincidir exactamente con el URI en Google Cloud Console.
    | Default: {APP_URL}/google/oauth/callback
    */
    'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI', ''),

    'timezone' => env('GOOGLE_CALENDAR_TIMEZONE', env('APP_TIMEZONE', 'America/Lima')),

    'meeting_duration_minutes' => (int) env('GOOGLE_CALENDAR_MEETING_MINUTES', 20),

    'calendar_id' => env('GOOGLE_CALENDAR_ID', 'primary'),

    'scopes' => [
        'https://www.googleapis.com/auth/calendar.events',
    ],

    /*
    | Ruta relativa dentro de storage/app (archivo cifrado con APP_KEY).
    */
    'token_path' => 'google-calendar/oauth-token.json',

    /*
    | Fallback opcional: refresh token pegado a mano en .env (si no usas el panel).
    */
    'refresh_token' => env('GOOGLE_CALENDAR_REFRESH_TOKEN', ''),

];
