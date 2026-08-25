<?php

declare(strict_types=1);

use App\Services\Platform\DatabaseBackupService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('poda backups locales y remotos fuera de la retención de 14 días', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-25 12:00:00'));

    $localRoot = storage_path('framework/testing/backup-prune-'.uniqid());
    File::ensureDirectoryExists($localRoot);

    $keepLocal = $localRoot.DIRECTORY_SEPARATOR.'2026-08-20_020000';
    $dropLocal = $localRoot.DIRECTORY_SEPARATOR.'2026-07-12_020008';
    $safety = $localRoot.DIRECTORY_SEPARATOR.'_safety';

    File::ensureDirectoryExists($keepLocal);
    File::ensureDirectoryExists($dropLocal);
    File::ensureDirectoryExists($safety);
    File::put($keepLocal.DIRECTORY_SEPARATOR.'full.dump', 'keep');
    File::put($dropLocal.DIRECTORY_SEPARATOR.'full.dump', 'drop');
    File::put($safety.DIRECTORY_SEPARATOR.'x.dump', 'safe');

    Storage::fake('backups');
    $disk = Storage::disk('backups');
    $disk->put('vetsaas/db/2026-08-20_020000/public.dump', 'keep');
    $disk->put('vetsaas/db/2026-07-12_020008/public.dump', 'drop');
    $disk->put('vetsaas/db/latest.json', '{"ok":true}');

    config([
        'backup.path' => $localRoot,
        'backup.retention_days' => 14,
        'backup.remote.enabled' => true,
        'backup.remote.disk' => 'backups',
        'backup.remote.prefix' => 'vetsaas/db',
        'backup.remote.include_full' => true,
        'filesystems.disks.backups.key' => 'test-key',
        'filesystems.disks.backups.secret' => 'test-secret',
        'filesystems.disks.backups.bucket' => 'vetsaas-backups',
    ]);

    $result = app(DatabaseBackupService::class)->prune();

    expect($result['local_deleted'])->toBe(1)
        ->and($result['remote_deleted'])->toBe(1)
        ->and($result['remote_skipped'])->toBeFalse()
        ->and(File::isDirectory($keepLocal))->toBeTrue()
        ->and(File::isDirectory($dropLocal))->toBeFalse()
        ->and(File::isDirectory($safety))->toBeTrue()
        ->and($disk->exists('vetsaas/db/2026-08-20_020000/public.dump'))->toBeTrue()
        ->and($disk->exists('vetsaas/db/2026-07-12_020008/public.dump'))->toBeFalse()
        ->and($disk->exists('vetsaas/db/latest.json'))->toBeTrue();

    File::deleteDirectory($localRoot);
    Carbon::setTestNow();
});

it('elimina full.dump ya subidos a R2 cuando include_full es false', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-25 12:00:00'));

    $localRoot = storage_path('framework/testing/backup-full-prune-'.uniqid());
    File::ensureDirectoryExists($localRoot);

    Storage::fake('backups');
    $disk = Storage::disk('backups');
    $disk->put('vetsaas/db/2026-08-20_020000/full.dump', 'full');
    $disk->put('vetsaas/db/2026-08-20_020000/public.dump', 'public');
    $disk->put('vetsaas/db/2026-08-20_020000/vet_demo.dump', 'tenant');

    config([
        'backup.path' => $localRoot,
        'backup.retention_days' => 14,
        'backup.remote.enabled' => true,
        'backup.remote.disk' => 'backups',
        'backup.remote.prefix' => 'vetsaas/db',
        'backup.remote.include_full' => false,
        'filesystems.disks.backups.key' => 'test-key',
        'filesystems.disks.backups.secret' => 'test-secret',
        'filesystems.disks.backups.bucket' => 'vetsaas-backups',
    ]);

    $result = app(DatabaseBackupService::class)->prune();

    expect($result['remote_full_deleted'])->toBe(1)
        ->and($disk->exists('vetsaas/db/2026-08-20_020000/full.dump'))->toBeFalse()
        ->and($disk->exists('vetsaas/db/2026-08-20_020000/public.dump'))->toBeTrue()
        ->and($disk->exists('vetsaas/db/2026-08-20_020000/vet_demo.dump'))->toBeTrue();

    File::deleteDirectory($localRoot);
    Carbon::setTestNow();
});
