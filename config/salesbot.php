<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bot de ventas IA — VetSaaS / Orvae
    |--------------------------------------------------------------------------
    |
    | Configuración del bot automático de WhatsApp que convierte prospectos
    | en clientes usando OpenAI como cerebro de conversación.
    |
    */

    'enabled' => (bool) env('SALESBOT_ENABLED', false),

    /*
    | Secreto que OpenWA envía en el header "X-Webhook-Secret" al llamar
    | al endpoint de webhook. Protege contra llamadas externas maliciosas.
    | Generar con: php artisan tinker --execute="echo Str::random(48);"
    */
    'webhook_secret' => env('SALESBOT_WEBHOOK_SECRET', ''),

    /*
    | OpenAI — API key y modelo a usar.
    | gpt-4o-mini: barato (~S/0.05 por conversación), rápido y suficientemente
    | inteligente para ventas consultivas.
    */
    'openai_api_key' => env('OPENAI_API_KEY', ''),
    'openai_model'   => env('SALESBOT_OPENAI_MODEL', 'gpt-4o-mini'),

    /*
    | Máximo de tokens en la respuesta del bot.
    | 300 tokens ≈ 4-5 líneas de WhatsApp. No queremos respuestas largas.
    */
    'max_tokens' => (int) env('SALESBOT_MAX_TOKENS', 300),

    /*
    | Temperatura de la IA (0 = determinista, 1 = creativo).
    | 0.7 da respuestas naturales sin volverse impredecible.
    */
    'temperature' => (float) env('SALESBOT_TEMPERATURE', 0.7),

    /*
    | URL y credenciales del demo que el bot comparte con los prospectos.
    */
    'demo_url'      => env('SALESBOT_DEMO_URL', 'demo.orvae.pe'),
    'demo_email'    => env('SALESBOT_DEMO_EMAIL', 'demo@vetsaas.pe'),
    'demo_password' => env('SALESBOT_DEMO_PASSWORD', 'demo1234'),

];
