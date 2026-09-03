<?php

declare(strict_types=1);

return [
    'manifest_path' => env('SSL_CERTS_MANIFEST', storage_path('app/ssl/latest.json')),
    'warn_days' => (int) env('SSL_CERTS_WARN_DAYS', 21),
    'critical_days' => (int) env('SSL_CERTS_CRITICAL_DAYS', 7),
    'stale_after_hours' => (int) env('SSL_CERTS_STALE_AFTER_HOURS', 36),
    'watch_name' => env('SSL_CERTS_WATCH_NAME', 'vetsaas.orvae.pe'),
];
