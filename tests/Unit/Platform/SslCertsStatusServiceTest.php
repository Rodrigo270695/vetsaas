<?php

declare(strict_types=1);

use App\Services\Platform\SslCertsStatusService;
use Illuminate\Support\Facades\File;

it('marca el wildcard de vetsaas como watch_ok', function (): void {
    $path = storage_path('app/ssl-test-'.uniqid().'.json');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode([
        'generated_at' => now()->toIso8601String(),
        'certs' => [
            [
                'name' => 'vetsaas.orvae.pe',
                'domains' => ['vetsaas.orvae.pe', '*.vetsaas.orvae.pe'],
                'expiry' => now()->addDays(80)->toIso8601String(),
                'valid' => true,
            ],
            [
                'name' => 'miboda.orvae.pe',
                'domains' => ['miboda.orvae.pe'],
                'expiry' => now()->addDays(10)->toIso8601String(),
                'valid' => true,
            ],
        ],
    ]));

    config(['ssl.manifest_path' => $path]);

    $status = app(SslCertsStatusService::class)->status();

    expect($status['missing'])->toBeFalse()
        ->and($status['watch_ok'])->toBeTrue()
        ->and($status['expiring'])->toBe(1)
        ->and($status['certs'][0]['name'])->toBe('miboda.orvae.pe');

    File::delete($path);
});
