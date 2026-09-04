<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Agenda\AgendaRsvpInboxPoller;
use Illuminate\Console\Command;

/**
 * Lee el historial OpenWA de chats con recordatorio reciente y aplica SI/NO.
 */
final class AgendaRsvpPollInboxCommand extends Command
{
    protected $signature = 'vetsaas:agenda-rsvp-poll-inbox
                            {--dry-run : Solo listar; no confirma ni responde}';

    protected $description = 'Aplica SI/NO de agenda leyendo el inbox OpenWA (si el webhook no dispara)';

    public function handle(AgendaRsvpInboxPoller $poller): int
    {
        $stats = $poller->poll((bool) $this->option('dry-run'));

        $this->info("Chats revisados: {$stats['chats']}");
        $this->info("Aplicados:       {$stats['applied']}");
        $this->info("Sin cambio:      {$stats['skipped']}");

        return self::SUCCESS;
    }
}
