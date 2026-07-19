<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('CONSULTA_DICTATION_ENABLED', true),
    'openai_api_key' => env('OPENAI_API_KEY', ''),
    'openai_model' => env('CONSULTA_DICTATION_OPENAI_MODEL', env('IN_APP_ASSISTANT_OPENAI_MODEL', 'gpt-4o-mini')),
    'whisper_model' => env('CONSULTA_DICTATION_WHISPER_MODEL', 'whisper-1'),
    'whisper_lang' => env('CONSULTA_DICTATION_WHISPER_LANG', 'es'),
    'max_audio_kb' => (int) env('CONSULTA_DICTATION_MAX_AUDIO_KB', 12288),
];
