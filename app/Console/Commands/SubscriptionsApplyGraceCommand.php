<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\SubscriptionGraceBackfillService;
use Illuminate\Console\Command;

class SubscriptionsApplyGraceCommand extends Command
{
    protected $signature = 'vetsaas:subscriptions-apply-grace
                            {--dry-run : Solo listar qué se actualizaría, sin escribir}
                            {--report : Mostrar detalle por suscripción}';

    protected $description = 'Inicializa grace_days (default 3) en suscripciones de pago y activa gracia si ya están vencidas/suspendidas. Excluye plan free.';

    public function handle(SubscriptionGraceBackfillService $backfill): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $report = (bool) $this->option('report');

        $result = $backfill->run(dryRun: $dryRun);

        $this->info(sprintf(
            '%s Default %d día(s): escaneadas=%d grace_days_set=%d gracia_activada=%d omitidas=%d',
            $dryRun ? '[dry-run]' : '[ok]',
            $result['default_grace_days'],
            $result['scanned'],
            $result['days_set'],
            $result['grace_applied'],
            $result['skipped'],
        ));

        if ($report && $result['items'] !== []) {
            $this->table(
                ['subscription_id', 'tenant_id', 'from', 'estado', 'grace_days', 'grace_ends_at'],
                array_map(
                    static fn (array $row): array => [
                        $row['id'],
                        $row['tenant_id'],
                        $row['from'],
                        $row['estado'],
                        (string) $row['grace_days'],
                        $row['grace_ends_at'] ?? '—',
                    ],
                    $result['items'],
                ),
            );
        }

        $this->comment('Puedes ajustar “Días de gracia” por suscripción en Plataforma → Suscripciones → Editar.');
        $this->comment('Las active vigentes solo reciben grace_days; al vencer el cobro el supervisor activa la gracia con ese valor.');

        return self::SUCCESS;
    }
}
