<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\SubscriptionGraceBackfillService;
use Illuminate\Console\Command;

class SubscriptionsApplyGraceCommand extends Command
{
    protected $signature = 'vetsaas:subscriptions-apply-grace
                            {--dry-run : Solo listar qué se actualizaría, sin escribir}
                            {--report : Mostrar detalle por suscripción}';

    protected $description = 'Aplica periodo de gracia (billing.grace_days) a suscripciones de pago actuales vencidas/en gracia/suspendidas. Excluye plan free.';

    public function handle(SubscriptionGraceBackfillService $backfill): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $report = (bool) $this->option('report');

        $result = $backfill->run(dryRun: $dryRun);

        $this->info(sprintf(
            '%s Gracia %d día(s): escaneadas=%d aplicadas=%d omitidas=%d',
            $dryRun ? '[dry-run]' : '[ok]',
            $result['grace_days'],
            $result['scanned'],
            $result['applied'],
            $result['skipped'],
        ));

        if ($report && $result['items'] !== []) {
            $this->table(
                ['subscription_id', 'tenant_id', 'from', 'to', 'grace_ends_at'],
                array_map(
                    static fn (array $row): array => [
                        $row['id'],
                        $row['tenant_id'],
                        $row['from'],
                        $row['to'],
                        $row['grace_ends_at'],
                    ],
                    $result['items'],
                ),
            );
        }

        $this->comment('Las active aún vigentes no se tocan: al vencer el cobro, `vetsaas:billing-supervisor` las pasa a grace.');

        return self::SUCCESS;
    }
}
