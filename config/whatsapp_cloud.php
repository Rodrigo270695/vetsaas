<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API (Meta) — plataforma Orvae
    |--------------------------------------------------------------------------
    |
    | Canal oficial sin Chromium para avisos de renovación. Si está habilitado
    | y configurado, PlatformWhatsAppMessenger lo usa antes que OpenWA.
    |
    | Plantilla Utility esperada (ej. vetsaas_renewal_reminder), body:
    | Hola {{1}}, tu plan VetSaaS ({{2}}) {{3}}. Total: S/ {{4}}. Paga aquí: {{5}}
    | Donde {{3}} es p.ej. "vence el 25/08/2026" o "venció el 20/08/2026".
    |
    */
    'enabled' => (bool) env('WHATSAPP_CLOUD_ENABLED', false),

    'api_version' => (string) env('WHATSAPP_CLOUD_API_VERSION', 'v21.0'),

    'token' => env('WHATSAPP_CLOUD_TOKEN'),

    'phone_number_id' => env('WHATSAPP_CLOUD_PHONE_NUMBER_ID'),

    'business_account_id' => env('WHATSAPP_CLOUD_BUSINESS_ACCOUNT_ID'),

    'template_renewal' => (string) env('WHATSAPP_CLOUD_TEMPLATE_RENEWAL', 'vetsaas_renewal_reminder'),

    'template_lang' => (string) env('WHATSAPP_CLOUD_TEMPLATE_LANG', 'es'),

    'timeout_seconds' => (int) env('WHATSAPP_CLOUD_TIMEOUT_SECONDS', 30),

];
