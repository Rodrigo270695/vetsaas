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
    |   - latest.json        → metadatos leídos por Operaciones
    |
    | Solo aplica cuando DB_CONNECTION=pgsql. En sqlite/local se omite.
    |
    | Remoto (S3 / Cloudflare R2): si BACKUP_REMOTE_ENABLED=true, tras el
    | dump local se sube la carpeta al disco configurado (default: s3).
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

    /*
    |--------------------------------------------------------------------------
    | Copia fuera del VPS (S3 / R2)
    |--------------------------------------------------------------------------
    */
    'remote' => [
        'enabled' => (bool) env('BACKUP_REMOTE_ENABLED', false),

        /** Disco de filesystems.php (`backups` o `s3`; ambos sirven AWS y R2). */
        'disk' => env('BACKUP_REMOTE_DISK', 'backups'),

        /** Prefijo dentro del bucket, sin slash inicial. */
        'prefix' => trim((string) env('BACKUP_REMOTE_PREFIX', 'vetsaas/db'), '/'),

        /**
         * Si true, un fallo de subida marca el backup como fallido
         * (aunque el dump local exista).
         */
        'required' => (bool) env('BACKUP_REMOTE_REQUIRED', true),
    ],
];
