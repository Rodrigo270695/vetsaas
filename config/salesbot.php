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
    | Tokens para respuestas del flujo Orvae páginas web (mensaje inicial con 3 planes).
    */
    'max_tokens_paginas_web' => (int) env('SALESBOT_MAX_TOKENS_PAGINAS_WEB', 550),

    /*
    | Temperatura de la IA (0 = determinista, 1 = creativo).
    | 0.7 da respuestas naturales sin volverse impredecible.
    */
    'temperature' => (float) env('SALESBOT_TEMPERATURE', 0.7),

    /*
    | URL y credenciales del demo que el bot comparte con los prospectos.
    */
    'demo_url'      => env('SALESBOT_DEMO_URL', 'https://demo.vetsaas.orvae.pe/login'),
    'demo_email'    => env('SALESBOT_DEMO_EMAIL', 'demo@vetsaas.pe'),
    'demo_password' => env('SALESBOT_DEMO_PASSWORD', 'demo1234'),

    // URL donde el prospecto se registra y elige su plan (Free o pago).
    'register_url'  => env('SALESBOT_REGISTER_URL', 'https://orvae.pe/software/VETSAAS'),

    /*
    |--------------------------------------------------------------------------
    | Soporte de mensajes de audio (Whisper)
    |--------------------------------------------------------------------------
    |
    | Cuando un prospecto envía un audio, Whisper lo transcribe a texto y
    | el bot responde normalmente como si hubiera escrito.
    |
    | Activar: SALESBOT_AUDIO_ENABLED=true en .env
    | Modelo Whisper: whisper-1 (único disponible actualmente).
    | Idiomas: auto-detecta, pero funciona mejor indicando "es" para español.
    |
    */
    'audio_enabled'  => (bool) env('SALESBOT_AUDIO_ENABLED', true),
    'whisper_model'  => env('SALESBOT_WHISPER_MODEL', 'whisper-1'),
    'whisper_lang'   => env('SALESBOT_WHISPER_LANG', 'es'),

    /*
    |--------------------------------------------------------------------------
    | Respuesta con voz (TTS — Text to Speech)
    |--------------------------------------------------------------------------
    |
    | Cuando el prospecto manda un audio, el bot también responde con audio.
    | Usa OpenAI TTS (tts-1) que genera voz natural en español.
    |
    | Voces disponibles: alloy, echo, fable, onyx, nova, shimmer
    | nova = femenina natural, onyx = masculino profundo
    |
    | Costo: ~S/0.05 por respuesta de ~200 palabras. Muy bajo.
    |
    */
    'tts_enabled' => (bool) env('SALESBOT_TTS_ENABLED', true),
    'tts_model'   => env('SALESBOT_TTS_MODEL', 'tts-1'),
    'tts_voice'   => env('SALESBOT_TTS_VOICE', 'nova'),

];
