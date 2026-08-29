<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\SubscriptionBillingSupervisor;
use Illuminate\Console\Command;

class BillingSupervisorCommand extends Command
{
    protected $signature = 'vetsaas:billing-supervisor';

    protected $description = 'Aplica grace/suspended a suscripciones con cobro o trial vencido sin pago';

    public function handle(SubscriptionBillingSupervisor $supervisor): int
    {
        $result = $supervisor->run();

        $this->info(sprintf(
            'Supervisor: %d trial→grace, %d active→grace, %d grace→suspended, %d periodos free sincronizados',
            $result['trials_to_grace'],
            $result['active_to_grace'],
            $result['grace_to_suspended'],
            $result['free_periods_synced'],
        ));

        return self::SUCCESS;
    }
}
