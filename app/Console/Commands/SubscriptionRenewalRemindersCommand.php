<?php

namespace App\Console\Commands;

use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Subscriptions\SubscriptionRenewalReminderScanner;
use Illuminate\Console\Command;

class SubscriptionRenewalRemindersCommand extends Command
{
    protected $signature = 'vetsaas:subscription-renewal-reminders';

    protected $description = 'Envía WhatsApp de aviso de vencimiento de suscripción a los tenants (plataforma → clínica)';

    public function handle(
        SubscriptionRenewalReminderScanner $scanner,
        PlatformWhatsAppMessenger $messenger,
    ): int {
        if (! $messenger->isReady()) {
            $this->warn('Sesión OpenWA de plataforma no configurada o sin conectar. Nada enviado.');

            return self::SUCCESS;
        }

        $result = $scanner->run();

        $this->info(sprintf(
            'Recordatorios renovación: %d revisadas, %d enviados, %d omitidos, %d fallidos',
            $result['scanned'],
            $result['sent'],
            $result['skipped'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
