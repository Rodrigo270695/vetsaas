<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\SubscriptionTenantSync;
use Illuminate\Console\Command;

class SyncTenantsFromSubscriptionsCommand extends Command
{
    protected $signature = 'vetsaas:sync-tenants-from-subscriptions';

    protected $description = 'Alinea estado y trial de tenants con su suscripción viva';

    public function handle(SubscriptionTenantSync $sync): int
    {
        $updated = $sync->syncAllLiving();

        $this->info("Tenants sincronizados: {$updated}");

        return self::SUCCESS;
    }
}
