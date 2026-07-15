<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Backups diarios de PostgreSQL: dump completo + public + cada schema vet_*.
 *
 * Escribe un manifest en `{path}/latest.json` para que Operaciones muestre
 * si el último backup fue OK / atrasado / fallido. Opcionalmente sube la
 * carpeta a S3/R2 (fuera del VPS).
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
     *     error: string|null,
     *     remote_enabled: bool,
     *     remote_ok: bool|null,
     *     remote_path: string|null,
     *     remote_error: string|null,
     *     remote_files: int
     * }
     */
    public function run(): array
    {
        $started = Carbon::now();

        if (! (bool) config('backup.enabled', true)) {
            return $this->writeManifest($this->emptyResult($started, 'Backups deshabilitados (BACKUP_ENABLED=false).'));
        }

        if (config('database.default') !== 'pgsql') {
            return $this->writeManifest($this->emptyResult($started, 'Solo se soporta PostgreSQL (DB_CONNECTION=pgsql).'));
        }

        $basePath = rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
        $folderName = $started->format('Y-m-d_His');
        $dayDir = $basePath.DIRECTORY_SEPARATOR.$folderName;

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
            $remote = $this->uploadRemote($dayDir, $folderName);

            $remoteRequired = (bool) config('backup.remote.required', true);
            $remoteEnabled = (bool) config('backup.remote.enabled', false);
            $ok = true;
            $error = null;

            if ($remoteEnabled && $remoteRequired && $remote['remote_ok'] !== true) {
                $ok = false;
                $error = $remote['remote_error'] ?? 'Falló la subida remota del backup.';
            }

            $finished = Carbon::now();

            $manifest = $this->writeManifest([
                'ok' => $ok,
                'started_at' => $started->toIso8601String(),
                'finished_at' => $finished->toIso8601String(),
                'duration_seconds' => $started->diffInSeconds($finished),
                'directory' => $dayDir,
                'full_size_bytes' => $fullSize,
                'schemas' => $schemas,
                'schema_count' => count($schemas),
                'error' => $error,
                ...$remote,
            ]);

            $this->pruneOldBackups($basePath);

            return $manifest;
        } catch (Throwable $e) {
            report($e);

            return $this->writeManifest([
                ...$this->emptyResult($started, $e->getMessage()),
                'directory' => $dayDir,
                'duration_seconds' => $started->diffInSeconds(Carbon::now()),
                'finished_at' => Carbon::now()->toIso8601String(),
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
     *     path: string,
     *     remote_enabled: bool,
     *     remote_ok: bool|null,
     *     remote_path: string|null,
     *     remote_error: string|null,
     *     remote_files: int,
     *     remote_configured: bool
     * }
     */
    public function status(): array
    {
        $path = rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
        $manifestPath = $path.DIRECTORY_SEPARATOR.self::MANIFEST_NAME;
        $staleAfter = (int) config('backup.stale_after_hours', 30);
        $enabled = (bool) config('backup.enabled', true);
        $remoteEnabled = (bool) config('backup.remote.enabled', false);

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
            'remote_enabled' => $remoteEnabled,
            'remote_ok' => null,
            'remote_path' => null,
            'remote_error' => null,
            'remote_files' => 0,
            'remote_configured' => $this->isRemoteConfigured(),
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
        $ok = (bool) ($data['ok'] ?? false);
        $remoteOk = array_key_exists('remote_ok', $data)
            ? (is_bool($data['remote_ok']) ? $data['remote_ok'] : null)
            : null;

        $stale = $ageHours === null || $ageHours > $staleAfter || ! $ok;
        if ($remoteEnabled && $remoteOk !== true) {
            $stale = true;
        }

        return [
            'ok' => $ok,
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
            'stale' => $stale,
            'enabled' => $enabled,
            'retention_days' => (int) config('backup.retention_days', 14),
            'path' => $path,
            'remote_enabled' => $remoteEnabled,
            'remote_ok' => $remoteOk,
            'remote_path' => is_string($data['remote_path'] ?? null) ? $data['remote_path'] : null,
            'remote_error' => is_string($data['remote_error'] ?? null) ? $data['remote_error'] : null,
            'remote_files' => (int) ($data['remote_files'] ?? 0),
            'remote_configured' => $this->isRemoteConfigured(),
        ];
    }

    /**
     * @return array{
     *     remote_enabled: bool,
     *     remote_ok: bool|null,
     *     remote_path: string|null,
     *     remote_error: string|null,
     *     remote_files: int
     * }
     */
    private function uploadRemote(string $dayDir, string $folderName): array
    {
        $enabled = (bool) config('backup.remote.enabled', false);

        if (! $enabled) {
            return [
                'remote_enabled' => false,
                'remote_ok' => null,
                'remote_path' => null,
                'remote_error' => null,
                'remote_files' => 0,
            ];
        }

        if (! $this->isRemoteConfigured()) {
            return [
                'remote_enabled' => true,
                'remote_ok' => false,
                'remote_path' => null,
                'remote_error' => 'Remoto activo pero faltan credenciales/bucket (AWS_* o BACKUP_AWS_*).',
                'remote_files' => 0,
            ];
        }

        $diskName = (string) config('backup.remote.disk', 'backups');
        $prefix = trim((string) config('backup.remote.prefix', 'vetsaas/db'), '/');
        $remoteBase = $prefix !== '' ? $prefix.'/'.$folderName : $folderName;

        try {
            $disk = Storage::disk($diskName);
            $uploaded = 0;

            foreach (File::files($dayDir) as $file) {
                $remoteKey = $remoteBase.'/'.$file->getFilename();
                $stream = fopen($file->getPathname(), 'r');
                if ($stream === false) {
                    throw new RuntimeException('No se pudo leer '.$file->getFilename());
                }

                try {
                    $disk->writeStream($remoteKey, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                $uploaded++;
            }

            // Copia también el latest.json (se escribe después; lo subimos al final del run).
            return [
                'remote_enabled' => true,
                'remote_ok' => true,
                'remote_path' => $remoteBase,
                'remote_error' => null,
                'remote_files' => $uploaded,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'remote_enabled' => true,
                'remote_ok' => false,
                'remote_path' => $remoteBase,
                'remote_error' => $e->getMessage(),
                'remote_files' => 0,
            ];
        }
    }

    private function isRemoteConfigured(): bool
    {
        $diskName = (string) config('backup.remote.disk', 'backups');
        $config = config('filesystems.disks.'.$diskName, []);

        $key = (string) ($config['key'] ?? '');
        $secret = (string) ($config['secret'] ?? '');
        $bucket = (string) ($config['bucket'] ?? '');

        return $key !== '' && $secret !== '' && $bucket !== '';
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
     *     error: string|null,
     *     remote_enabled: bool,
     *     remote_ok: bool|null,
     *     remote_path: string|null,
     *     remote_error: string|null,
     *     remote_files: int
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
     *     error: string|null,
     *     remote_enabled: bool,
     *     remote_ok: bool|null,
     *     remote_path: string|null,
     *     remote_error: string|null,
     *     remote_files: int
     * }
     */
    private function writeManifest(array $payload): array
    {
        $basePath = rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists($basePath);
        $manifestPath = $basePath.DIRECTORY_SEPARATOR.self::MANIFEST_NAME;
        File::put(
            $manifestPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        // Sube latest.json al remoto si el dump remoto ya fue OK.
        if (($payload['remote_ok'] ?? null) === true && ($payload['remote_path'] ?? null)) {
            try {
                $diskName = (string) config('backup.remote.disk', 'backups');
                $disk = Storage::disk($diskName);
                $remoteKey = rtrim((string) $payload['remote_path'], '/').'/'.self::MANIFEST_NAME;
                $disk->put($remoteKey, File::get($manifestPath));
                // También en la raíz del prefix para lectura rápida.
                $prefix = trim((string) config('backup.remote.prefix', 'vetsaas/db'), '/');
                if ($prefix !== '') {
                    $disk->put($prefix.'/'.self::MANIFEST_NAME, File::get($manifestPath));
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $payload;
    }

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
     *     error: string|null,
     *     remote_enabled: bool,
     *     remote_ok: bool|null,
     *     remote_path: string|null,
     *     remote_error: string|null,
     *     remote_files: int
     * }
     */
    private function emptyResult(Carbon $started, string $error): array
    {
        return [
            'ok' => false,
            'started_at' => $started->toIso8601String(),
            'finished_at' => Carbon::now()->toIso8601String(),
            'duration_seconds' => 0,
            'directory' => '',
            'full_size_bytes' => 0,
            'schemas' => [],
            'schema_count' => 0,
            'error' => $error,
            'remote_enabled' => (bool) config('backup.remote.enabled', false),
            'remote_ok' => null,
            'remote_path' => null,
            'remote_error' => null,
            'remote_files' => 0,
        ];
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

    /**
     * Restaura un schema tenant (`vet_*`) desde un dump custom de backup.
     *
     * @return array{
     *     schema: string,
     *     dump_path: string,
     *     folder: string,
     *     safety_dump: string|null
     * }
     */
    public function restoreTenantSchema(string $schema, ?string $folder = null): array
    {
        if (config('database.default') !== 'pgsql') {
            throw new RuntimeException('Solo se soporta PostgreSQL (DB_CONNECTION=pgsql).');
        }

        $prefix = (string) config('tenant.schema_prefix', 'vet_');
        $schema = strtolower(trim($schema));

        if ($schema === '' || ! str_starts_with($schema, $prefix) || ! preg_match('/^[a-z0-9_]+$/', $schema)) {
            throw new RuntimeException("Schema inválido: solo se permiten schemas {$prefix}*.");
        }

        if (in_array($schema, ['public', 'full'], true) || str_contains($schema, '..')) {
            throw new RuntimeException('No se puede restaurar public/full con este comando.');
        }

        $resolved = $this->resolveTenantDump($schema, $folder);
        $dumpPath = $resolved['dump_path'];
        $folderName = $resolved['folder'];

        $basePath = rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
        $safetyDir = $basePath.DIRECTORY_SEPARATOR.'_safety';
        File::ensureDirectoryExists($safetyDir);
        $safetyDump = $safetyDir.DIRECTORY_SEPARATOR.$schema.'_pre_restore_'.Carbon::now()->format('Ymd_His').'.dump';

        $schemaExists = (bool) DB::selectOne(
            'select exists(select 1 from pg_namespace where nspname = ?) as ok',
            [$schema],
        )?->ok;

        $safetyPath = null;
        if ($schemaExists) {
            $this->dump($safetyDump, $schema);
            $safetyPath = $safetyDump;
        }

        DB::statement('DROP SCHEMA IF EXISTS '.$this->quoteIdent($schema).' CASCADE');
        // NO pre-crear el schema: si existe vacío, el CREATE SCHEMA del dump
        // falla y pg_restore puede saltar todas las tablas dependientes.
        // El dump (pg_dump --schema=vet_*) ya trae CREATE SCHEMA + objetos.

        $stderr = $this->restoreDump($dumpPath, $schema);

        $tableCount = (int) (DB::selectOne(
            'select count(*)::int as c from information_schema.tables where table_schema = ?',
            [$schema],
        )->c ?? 0);

        if ($tableCount < 1) {
            throw new RuntimeException(
                "pg_restore dejó {$schema} sin tablas (dump vacío o restaurado mal)."
                .($stderr !== '' ? ' stderr: '.mb_substr($stderr, 0, 2000) : ''),
            );
        }

        return [
            'schema' => $schema,
            'dump_path' => $dumpPath,
            'folder' => $folderName,
            'safety_dump' => $safetyPath,
            'tables' => $tableCount,
        ];
    }

    /**
     * @return array{dump_path: string, folder: string}
     */
    public function resolveTenantDump(string $schema, ?string $folder = null): array
    {
        $basePath = rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
        $safeFile = preg_replace('/[^a-zA-Z0-9_]/', '_', $schema) ?: 'schema';
        $dumpName = $safeFile.'.dump';

        if ($folder !== null && trim($folder) !== '') {
            $folder = trim($folder);
            $dir = $basePath.DIRECTORY_SEPARATOR.$folder;
            $path = $dir.DIRECTORY_SEPARATOR.$dumpName;

            if (! File::isDirectory($dir) || ! File::isFile($path)) {
                throw new RuntimeException("No se encontró {$dumpName} en la carpeta de backup: {$folder}");
            }

            return ['dump_path' => $path, 'folder' => $folder];
        }

        $candidates = [];
        foreach (File::directories($basePath) as $dir) {
            $name = basename($dir);
            if (str_starts_with($name, '_')) {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$dumpName;
            if (File::isFile($path)) {
                $candidates[] = ['dump_path' => $path, 'folder' => $name, 'mtime' => File::lastModified($dir)];
            }
        }

        if ($candidates === []) {
            throw new RuntimeException("No hay dumps locales para {$schema} en {$basePath}");
        }

        usort($candidates, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return [
            'dump_path' => $candidates[0]['dump_path'],
            'folder' => $candidates[0]['folder'],
        ];
    }

    private function quoteIdent(string $ident): string
    {
        return '"'.str_replace('"', '""', $ident).'"';
    }

    /**
     * @return string stderr de pg_restore (para diagnóstico si queda vacío)
     */
    private function restoreDump(string $dumpFile, string $schema): string
    {
        $connection = config('database.connections.pgsql', []);
        $pgRestore = (string) config('backup.pg_restore', 'pg_restore');

        // El dump ya es de un solo schema (pg_dump --schema=vet_*).
        // NO usar --schema= aquí: con el schema pre-creado/inexistente
        // filtraba mal y dejaba 0 tablas. NO --clean: ya hicimos DROP SCHEMA.
        $command = [
            $pgRestore,
            '--no-owner',
            '--no-acl',
            '-h', (string) ($connection['host'] ?? '127.0.0.1'),
            '-p', (string) ($connection['port'] ?? 5432),
            '-U', (string) ($connection['username'] ?? 'postgres'),
            '-d', (string) ($connection['database'] ?? ''),
            $dumpFile,
        ];

        $result = Process::timeout(3600)
            ->env([
                'PGPASSWORD' => (string) ($connection['password'] ?? ''),
            ])
            ->run($command);

        $stderr = trim($result->errorOutput());

        // pg_restore usa exit 1 para warnings no fatales; >1 es error duro.
        if ($result->exitCode() > 1) {
            throw new RuntimeException($stderr !== '' ? $stderr : (trim($result->output()) ?: 'pg_restore falló sin mensaje.'));
        }

        if ($result->exitCode() === 1 && $stderr !== '') {
            Log::warning('pg_restore exit 1 (warnings)', [
                'schema' => $schema,
                'dump' => $dumpFile,
                'stderr' => mb_substr($stderr, 0, 4000),
            ]);
        }

        return $stderr;
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
