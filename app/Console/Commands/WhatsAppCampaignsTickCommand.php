<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppCampaignDispatcher;
use App\Support\Tenancy\ActiveTenantIterator;
use Illuminate\Console\Command;

class WhatsAppCampaignsTickCommand extends Command
{
    protected $signature = 'vetsaas:whatsapp-campaigns-tick';

    protected $description = 'Envía un mensaje de campaña WhatsApp por tenant (lotes lentos, tope diario)';

    public function handle(
        ActiveTenantIterator $tenants,
        WhatsAppCampaignDispatcher $dispatcher,
    ): int {
        $sent = 0;
        $failed = 0;

        $tenants->each(function ($tenant) use ($dispatcher, &$sent, &$failed): void {
            $result = $dispatcher->tick($tenant);
            $sent += $result['sent'];
            $failed += $result['failed'];
        });

        $this->info("Campañas: enviados {$sent}, fallidos {$failed}.");

        return self::SUCCESS;
    }
}
