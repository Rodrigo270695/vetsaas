<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\SubscriptionGraceBackfillService;
use Illuminate\Console\Command;

class SubscriptionsApplyGraceCommand extends Command
{
    protected $signature = 'vetsaas:subscriptions-apply-grace
                            {--dry-run : Solo listar qué se actualizaría, sin escribir}
                            {--report : Mostrar detalle por suscripción}';

    protected $description = 'En suscripciones de pago: grace_ends_at = proximo_cobro_at + BILLING_GRACE_DAYS (default 3). Excluye free.';

    public function handle(SubscriptionGraceBackfillService $backfill): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $report = (bool) $this->option('report');

        $result = $backfill->run(dryRun: $dryRun);

        $this->info(sprintf(
            '%s +%d día(s) al próximo cobro → grace_ends_at: escaneadas=%d actualizadas=%d omitidas=%d',
            $dryRun ? '[dry-run]' : '[ok]',
            $result['grace_days'],
            $result['scanned'],
            $result['updated'],
            $result['skipped'],
        ));

        if ($report && $result['items'] !== []) {
            $this->table(
                ['subscription_id', 'tenant_id', 'proximo_cobro_at', 'grace_ends_at'],
                array_map(
                    static fn (array $row): array => [
                        $row['id'],
                        $row['tenant_id'],
                        $row['proximo_cobro_at'],
                        $row['grace_ends_at'],
                    ],
                    $result['items'],
                ),
            );
        }

        $this->comment('Si un tenant necesita más tiempo, edita “Fin del periodo de gracia” en esa suscripción.');

        return self::SUCCESS;
    }
}
