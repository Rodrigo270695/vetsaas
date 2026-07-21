<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Asistente in-app (ayuda + consulta de solo lectura)
    |--------------------------------------------------------------------------
    |
    | Comparte OPENAI_API_KEY con Bot IA / SalesBot. Puedes sobreescribir
    | el modelo u opciones con las variables IN_APP_ASSISTANT_*.
    |
    */
    'enabled' => (bool) env('IN_APP_ASSISTANT_ENABLED', true),

    'openai_api_key' => env('OPENAI_API_KEY', ''),
    'openai_model' => env('IN_APP_ASSISTANT_OPENAI_MODEL', env('BOT_IA_OPENAI_MODEL', 'gpt-4o-mini')),
    'max_tokens' => (int) env('IN_APP_ASSISTANT_MAX_TOKENS', 900),
    'temperature' => (float) env('IN_APP_ASSISTANT_TEMPERATURE', 0.2),

    'knowledge' => [
        'max_entries' => (int) env('IN_APP_ASSISTANT_KNOWLEDGE_MAX_ENTRIES', 6),
        'max_characters' => (int) env('IN_APP_ASSISTANT_KNOWLEDGE_MAX_CHARACTERS', 10000),
        'cache_ttl_minutes' => (int) env('IN_APP_ASSISTANT_KNOWLEDGE_CACHE_TTL', 10),
    ],

    /** Mensajes por usuario y día (zona horaria de la app). */
    'daily_message_limit' => (int) env('IN_APP_ASSISTANT_DAILY_LIMIT', 40),
];
