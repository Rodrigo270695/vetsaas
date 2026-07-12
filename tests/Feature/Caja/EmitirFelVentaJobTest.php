<?php

declare(strict_types=1);

use App\Jobs\EmitirFelVentaJob;
use App\Models\Tenant;
use App\Services\Fel\FelEmisionVentaService;
use App\Tenancy\Exceptions\TenantSuspendedException;
use App\Tenancy\Facades\Tenant as TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('EmitirFelVentaJob no reintenta cuando el tenant está cancelado', function (): void {
    $tenant = new Tenant([
        'slug' => 'cancelled-test',
        'estado' => 'cancelled',
    ]);

    TenantContext::shouldReceive('runForSlug')
        ->once()
        ->andThrow(new TenantSuspendedException($tenant, 'cancelled'));

    Log::shouldReceive('info')->once()->withArgs(function (string $message): bool {
        return str_contains($message, 'omitido');
    });

    $job = new EmitirFelVentaJob((string) Str::uuid(), 'cancelled-test');

    $job->handle(app(FelEmisionVentaService::class));
});
