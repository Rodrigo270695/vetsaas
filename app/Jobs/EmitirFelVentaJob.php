<?php

namespace App\Jobs;

use App\Services\Fel\FelEmisionVentaService;
use App\Tenancy\Facades\Tenant as TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmitirFelVentaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $ventaId,
        public readonly string $tenantSlug,
    ) {}

    public function handle(FelEmisionVentaService $emision): void
    {
        TenantContext::runForSlug($this->tenantSlug, function () use ($emision): void {
            $emision->emitirPorVentaId($this->ventaId);
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('EmitirFelVentaJob falló', [
            'venta_id' => $this->ventaId,
            'tenant_slug' => $this->tenantSlug,
            'error' => $exception?->getMessage(),
        ]);
    }
}
