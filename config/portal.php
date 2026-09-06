<?php

declare(strict_types=1);

return [
    'cookie' => env('PORTAL_COOKIE', 'vetsaas_portal'),

    /** Sesión del celular del dueño (días). */
    'session_days' => (int) env('PORTAL_SESSION_DAYS', 90),

    /** Enlace mágico del WhatsApp (días). */
    'invite_days' => (int) env('PORTAL_INVITE_DAYS', 30),

    'pin_max_attempts' => 5,

    'pin_lock_minutes' => 15,

    'reset_code_minutes' => 10,

    'reset_max_per_hour' => 3,
];
