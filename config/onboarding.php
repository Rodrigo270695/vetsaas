<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Wizard de configuración inicial (clínicas)
    |--------------------------------------------------------------------------
    |
    | ONBOARDING_ENABLED=true activa el wizard para todos los tenants que
    | aún no completaron el onboarding.
    |
    | ONBOARDING_ENABLED_SLUGS permite probar solo en tenants concretos
    | (ej. demo,mi-prueba) sin activar el rollout global. Si la lista
    | tiene valores, solo esos slugs ven el wizard aunque ENABLED=false.
    |
    */
    'enabled' => filter_var(env('ONBOARDING_ENABLED', false), FILTER_VALIDATE_BOOL),

    'enabled_slugs' => array_values(array_filter(array_map(
        static fn (string $slug): string => strtolower(trim($slug)),
        explode(',', (string) env('ONBOARDING_ENABLED_SLUGS', ''))
    ))),

    /*
    | Muestra el panel siempre (solo visualización). No marca el onboarding
    | como completado ni bloquea módulos sin sede.
    | También puedes usar ?onboarding_preview=1 en /dashboard.
    */
    'preview' => filter_var(env('ONBOARDING_PREVIEW', false), FILTER_VALIDATE_BOOL),

];
