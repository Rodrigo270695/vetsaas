<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Database\PublicSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Agenda refresco de GPS 2×/día para tenants que ya dieron consentimiento.
 * La captura real ocurre en el navegador (TenantGeoAutoCapture) cuando
 * un usuario entra a la clínica.
 */
final class RequestTenantGeoRefreshCommand extends Command
{
    protected $signature = 'vetsaas:request-tenant-geo-refresh';

    protected $description = 'Marca tenants con consentimiento GPS para refrescar coordenadas (2×/día)';

    public function handle(): int
    {
        if (! PublicSchema::hasColumn('tenants', 'geo_consent_at')) {
            $this->warn('Columnas GPS no migradas aún.');

            return self::SUCCESS;
        }

        if (! PublicSchema::hasColumn('tenants', 'geo_refresh_requested_at')) {
            $this->warn('Falta geo_refresh_requested_at. Ejecuta la migración correspondiente.');

            return self::SUCCESS;
        }

        $now = Carbon::now();
        $updated = Tenant::query()
            ->whereNotNull('geo_consent_at')
            ->whereNull('geo_denied_at')
            ->update(['geo_refresh_requested_at' => $now]);

        $this->info("Refresco GPS solicitado para {$updated} tenant(s) a las {$now->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
