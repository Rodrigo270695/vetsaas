<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Backups diarios de PostgreSQL: dump completo + public + cada schema vet_*.
 *
 * Escribe un manifest en `{path}/latest.json` para que Operaciones muestre
 * si el último backup fue OK / atrasado / fallido.
 */
final class DatabaseBackupService
{
    public const MANIFEST_NAME = 'latest.json';

    /**
     * @return array{
     *     ok: bool,
     *     started_at: string,
     *     finished_at: string,
     *     duration_seconds: int,
     *     directory: string,
     *     full_size_bytes: int,
     *     schemas: list<string>,
     *     schema_count: int,
     *     error: string|null
     * }
     */
    public function run(): array
    {
        $started = Carbon::now();

        if (! (bool) config('backup.enabled', true)) {
            return $this->writeManifest([
                'ok' => false,
                'started_at' => $started->toIso8601String(),
                'finished_at' => Carbon::now()->toIso8601String(),
                'duration_seconds' => 0,
                'directory' => '',
                'full_size_bytes' => 0,
                'schemas' => [],
                'schema_count' => 0,
                'error' => 'Backups deshabilitados (BACKUP_ENABLED=false).',
            ]);
        }

        if (config('database.default') !== 'pgsql') {
            return $this->writeManifest([
                'ok' => false,
                'started_at' => $started->toIso8601String(),
                'finished_at' => Carbon::now()->toIso8601String(),
                'duration_seconds' => 0,
                'directory' => '',
                'full_size_bytes' => 0,
                'schemas' => [],
                'schema_count' => 0,
                'error' => 'Solo se soporta PostgreSQL (DB_CONNECTION=pgsql).',
            ]);
        }

        $basePath = rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
        $dayDir = $basePath.DIRECTORY_SEPARATOR.$started->format('Y-m-d_His');

        try {
            File::ensureDirectoryExists($dayDir);

            $schemas = $this->listTenantSchemas();
            $this->dump($dayDir.DIRECTORY_SEPARATOR.'full.dump');
            $this->dump($dayDir.DIRECTORY_SEPARATOR.'public.dump', 'public');

            foreach ($schemas as $schema) {
                $safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $schema) ?: 'schema';
                $this->dump($dayDir.DIRECTORY_SEPARATOR.$safe.'.dump', $schema);
            }

            $fullSize = File::size($dayDir.DIRECTORY_SEPARATOR.'full.dump');
            $finished = Carbon::now();

            $manifest = $this->writeManifest([
                'ok' => true,
                'started_at' => $started->toIso8601String(),
                'finished_at' => $finished->toIso8601String(),
                'duration_seconds' => $started->diffInSeconds($finished),
                'directory' => $dayDir,
                'full_size_bytes' => $fullSize,
                'schemas' => $schemas,
                'schema_count' => count($schemas),
                'error' => null,
            ]);

            $this->pruneOldBackups($basePath);

            return $manifest;
        } catch (Throwable $e) {
            report($e);

            return $this->writeManifest([
                'ok' => false,
                'started_at' => $started->toIso8601String(),
                'finished_at' => Carbon::now()->toIso8601String(),
                'duration_seconds' => $started->diffInSeconds(Carbon::now()),
                'directory' => $dayDir,
                'full_size_bytes' => 0,
                'schemas' => [],
                'schema_count' => 0,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{
     *     ok: bool|null,
     *     started_at: string|null,
     *     finished_at: string|null,
     *     duration_seconds: int|null,
     *     directory: string|null,
     *     full_size_bytes: int,
     *     schemas: list<string>,
     *     schema_count: int,
     *     error: string|null,
     *     age_hours: float|null,
     *     stale: bool,
     *     enabled: bool,
     *     retention_days: int,
     *     path: string
     * }
     */
    public function status(): array
    {
        $path = rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
        $manifestPath = $path.DIRECTORY_SEPARATOR.self::MANIFEST_NAME;
        $staleAfter = (int) config('backup.stale_after_hours', 30);
        $enabled = (bool) config('backup.enabled', true);

        $base = [
            'ok' => null,
            'started_at' => null,
            'finished_at' => null,
            'duration_seconds' => null,
            'directory' => null,
            'full_size_bytes' => 0,
            'schemas' => [],
            'schema_count' => 0,
            'error' => null,
            'age_hours' => null,
            'stale' => true,
            'enabled' => $enabled,
            'retention_days' => (int) config('backup.retention_days', 14),
            'path' => $path,
        ];

        if (! File::exists($manifestPath)) {
            $base['error'] = 'Aún no hay backups registrados.';

            return $base;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $base['error'] = 'Manifest de backup ilegible.';

            return $base;
        }

        $finishedAt = isset($data['finished_at']) && is_string($data['finished_at'])
            ? Carbon::parse($data['finished_at'])
            : null;

        $ageHours = $finishedAt?->diffInSeconds(Carbon::now()) / 3600;

        return [
            'ok' => (bool) ($data['ok'] ?? false),
            'started_at' => is_string($data['started_at'] ?? null) ? $data['started_at'] : null,
            'finished_at' => $finishedAt?->toIso8601String(),
            'duration_seconds' => isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            'directory' => is_string($data['directory'] ?? null) ? $data['directory'] : null,
            'full_size_bytes' => (int) ($data['full_size_bytes'] ?? 0),
            'schemas' => array_values(array_filter(
                is_array($data['schemas'] ?? null) ? $data['schemas'] : [],
                static fn ($s) => is_string($s),
            )),
            'schema_count' => (int) ($data['schema_count'] ?? 0),
            'error' => is_string($data['error'] ?? null) ? $data['error'] : null,
            'age_hours' => $ageHours !== null ? round($ageHours, 1) : null,
            'stale' => $ageHours === null || $ageHours > $staleAfter || ! ($data['ok'] ?? false),
            'enabled' => $enabled,
            'retention_days' => (int) config('backup.retention_days', 14),
            'path' => $path,
        ];
    }

    /**
     * @param  array{
     *     ok: bool,
     *     started_at: string,
     *     finished_at: string,
     *     duration_seconds: int,
     *     directory: string,
     *     full_size_bytes: int,
     *     schemas: list<string>,
     *     schema_count: int,
     *     error: string|null
     * }  $payload
     * @return array{
     *     ok: bool,
     *     started_at: string,
     *     finished_at: string,
     *     duration_seconds: int,
     *     directory: string,
     *     full_size_bytes: int,
     *     schemas: list<string>,
     *     schema_count: int,
     *     error: string|null
     * }
     */
    private function writeManifest(array $payload): array
    {
        $basePath = rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists($basePath);
        File::put(
            $basePath.DIRECTORY_SEPARATOR.self::MANIFEST_NAME,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return $payload;
    }

    /** @return list<string> */
    private function listTenantSchemas(): array
    {
        $prefix = (string) config('tenant.schema_prefix', 'vet_');

        $rows = DB::select(
            'select nspname from pg_namespace where nspname like ? order by nspname',
            [$prefix.'%'],
        );

        return array_values(array_map(
            static fn (object $row): string => (string) $row->nspname,
            $rows,
        ));
    }

    private function dump(string $outputFile, ?string $schema = null): void
    {
        $connection = config('database.connections.pgsql', []);
        $pgDump = (string) config('backup.pg_dump', 'pg_dump');
        $compression = max(0, min(9, (int) config('backup.compression', 6)));

        $command = [
            $pgDump,
            '--format=custom',
            '--compress='.$compression,
            '--no-owner',
            '--no-acl',
            '-h', (string) ($connection['host'] ?? '127.0.0.1'),
            '-p', (string) ($connection['port'] ?? 5432),
            '-U', (string) ($connection['username'] ?? 'postgres'),
            '-d', (string) ($connection['database'] ?? ''),
            '-f', $outputFile,
        ];

        if ($schema !== null) {
            $command[] = '--schema='.$schema;
        }

        $result = Process::timeout(3600)
            ->env([
                'PGPASSWORD' => (string) ($connection['password'] ?? ''),
            ])
            ->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'pg_dump falló sin mensaje.');
        }
    }

    private function pruneOldBackups(string $basePath): void
    {
        $retention = max(1, (int) config('backup.retention_days', 14));
        $cutoff = Carbon::now()->subDays($retention)->getTimestamp();

        foreach (File::directories($basePath) as $dir) {
            if (File::lastModified($dir) < $cutoff) {
                File::deleteDirectory($dir);
            }
        }
    }
}
