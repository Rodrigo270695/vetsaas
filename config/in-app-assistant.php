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
];
