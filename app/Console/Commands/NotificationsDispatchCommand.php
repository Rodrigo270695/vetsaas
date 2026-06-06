<?php

namespace App\Console\Commands;

use App\Services\Notifications\WhatsAppNotificationDispatcher;
use App\Support\Tenancy\ActiveTenantIterator;
use Illuminate\Console\Command;

class NotificationsDispatchCommand extends Command
{
    protected $signature = 'vetsaas:notifications-dispatch {--limit=50 : Máximo de mensajes por tenant}';

    protected $description = 'Envía mensajes pendientes de la cola vía OpenWA';

    public function handle(
        ActiveTenantIterator $tenants,
        WhatsAppNotificationDispatcher $dispatcher,
    ): int {
        $limit = max(1, (int) $this->option('limit'));
        $sent = 0;
        $failed = 0;

        $tenants->each(function ($tenant) use ($dispatcher, $limit, &$sent, &$failed): void {
            $result = $dispatcher->dispatchPending($tenant, $limit);
            $sent += $result['sent'];
            $failed += $result['failed'];
        });

        $this->info("Enviados: {$sent}, fallidos definitivos: {$failed}");

        return self::SUCCESS;
    }
}
