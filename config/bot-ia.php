<?php

declare(strict_types=1);

return [
    'precio_mensual' => (float) env('BOT_IA_PRECIO_MENSUAL', 15),

    'enabled' => (bool) env('BOT_IA_ENABLED', true),

    /*
    | Secreto del webhook OpenWA (header X-Webhook-Secret o HMAC).
    | Generar: php artisan tinker --execute="echo Str::random(48);"
    */
    'webhook_secret' => env('BOT_IA_WEBHOOK_SECRET', ''),

    'openai_api_key' => env('OPENAI_API_KEY', ''),
    'openai_model' => env('BOT_IA_OPENAI_MODEL', env('SALESBOT_OPENAI_MODEL', 'gpt-4o-mini')),
    'max_tokens' => (int) env('BOT_IA_MAX_TOKENS', 500),
    'temperature' => (float) env('BOT_IA_TEMPERATURE', 0.5),

    /*
    | Anti-eco / anti-spam del webhook clinic-bot (segundos).
    */
    'dedupe_ttl_seconds' => (int) env('BOT_IA_DEDUPE_TTL_SECONDS', 120),
    'outbound_echo_ttl_seconds' => (int) env('BOT_IA_OUTBOUND_ECHO_TTL_SECONDS', 180),
    'reply_cooldown_seconds' => (int) env('BOT_IA_REPLY_COOLDOWN_SECONDS', 15),

    /*
    | URL pública del webhook (para registrar en OpenWA por sesión de clínica).
    | Ej: https://app.vetsaas.orvae.pe/api/webhooks/clinic-bot
    */
    'webhook_url' => env('BOT_IA_WEBHOOK_URL', ''),

    /*
    | WhatsApp de soporte para activar el add-on (mensaje prellenado en la landing).
    | Formato E.164 sin +: 51976809804
    */
    'activation_whatsapp_phone' => env('BOT_IA_ACTIVATION_WHATSAPP_PHONE', '51976809804'),
    'activation_whatsapp_display' => env('BOT_IA_ACTIVATION_WHATSAPP_DISPLAY', '976 809 804'),
];
