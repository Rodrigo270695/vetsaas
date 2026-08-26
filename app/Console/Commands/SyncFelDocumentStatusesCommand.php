<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Fel\FelDocumentStatusSyncService;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Sincroniza estados Lucode/SUNAT (ACEPTADO/PENDIENTE/RECHAZADO) en todos los tenants.
 */
final class SyncFelDocumentStatusesCommand extends Command
{
    protected $signature = 'vetsaas:fel-sync-statuses
        {--tenant= : Solo este tenant (slug)}
        {--limit=80 : Máximo de comprobantes a consultar por tenant}';

    protected $description = 'Consulta APISUNAT /status y alinea fel_documents + ventas.fel_estado con SUNAT';

    public function handle(TenantManager $tenants, FelDocumentStatusSyncService $sync): int
    {
        $only = trim((string) $this->option('tenant'));
        $limit = max(1, (int) $this->option('limit'));

        $query = Tenant::query()
            ->whereIn('estado', ['active', 'trial', 'grace'])
            ->orderBy('slug');

        if ($only !== '') {
            $query->where('slug', $only);
        }

        $tenantsList = $query->get(['id', 'slug', 'schema_name', 'estado']);
        if ($tenantsList->isEmpty()) {
            $this->warn('No hay tenants que coincidan.');

            return self::SUCCESS;
        }

        $totalUpdated = 0;
        $totalChecked = 0;

        foreach ($tenantsList as $tenant) {
            try {
                $tenants->run($tenant, function () use ($sync, $limit, $tenant, &$totalUpdated, &$totalChecked): void {
                    if (! Schema::hasTable('fel_documents')) {
                        return;
                    }

                    $stats = $sync->syncClinic(limit: $limit);
                    $totalChecked += $stats['checked'];
                    $totalUpdated += $stats['updated'];

                    if ($stats['checked'] === 0) {
                        return;
                    }

                    $this->line(sprintf(
                        '[%s] checked=%d updated=%d aceptados=%d pendientes=%d rechazados=%d fallos=%d',
                        $tenant->slug,
                        $stats['checked'],
                        $stats['updated'],
                        $stats['accepted'],
                        $stats['pending'],
                        $stats['rejected'],
                        $stats['failed'],
                    ));

                    foreach (array_slice($stats['errors'], 0, 5) as $error) {
                        $this->warn('  · '.$error);
                    }
                });
            } catch (Throwable $e) {
                $this->error("[{$tenant->slug}] ".$e->getMessage());
            }
        }

        $this->info("Fin. checked={$totalChecked} updated={$totalUpdated}");

        return self::SUCCESS;
    }
}
