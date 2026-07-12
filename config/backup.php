<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Backups de PostgreSQL (plataforma + schemas tenant)
    |--------------------------------------------------------------------------
    |
    | El comando `vetsaas:backup-database` genera:
    |   - full.dump          → recuperación de desastre
    |   - public.dump        → catálogo SaaS
    |   - vet_*.dump         → un archivo por clínica
    |   - manifest.json      → metadatos leídos por Operaciones
    |
    | Solo aplica cuando DB_CONNECTION=pgsql. En sqlite/local se omite.
    */

    'enabled' => (bool) env('BACKUP_ENABLED', true),

    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    'pg_dump' => env('BACKUP_PG_DUMP', 'pg_dump'),

    'compression' => (int) env('BACKUP_COMPRESSION', 6),

    /*
    | Umbral en horas para marcar el último backup como "atrasado"
    | en el panel de Operaciones.
    */
    'stale_after_hours' => (int) env('BACKUP_STALE_AFTER_HOURS', 30),
];
